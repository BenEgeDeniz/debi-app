<?php
/**
 * DEBI - Elle çalıştırılan veritabanı migration servisi
 */

require_once __DIR__ . '/../../includes/functions.php';

/**
 * Tüm migration dosyalarını sırayla çalıştırır.
 *
 * Migration'lar uygulama bootstrap'inden bilerek ayrıdır. Bu fonksiyon yalnızca
 * migrate.php üzerinden veya bir CLI komutuyla açıkça çağrılmalıdır.
 */
function run_database_migrations(PDO $pdo): array
{
    $migrationDir = __DIR__ . '/../../sql-dum';
    $files = [
        '005_post_images_and_alerts.sql',
        '006_gamification_points.sql',
        '007_bill_images.sql',
        '008_friendships_and_timeline.sql',
        '009_profile_and_cover_photos.sql',
        '010_household_unit_manager_and_residents.sql',
        '101_collective_pond_and_bill_points.sql',
        '102_household_pond_and_ai_drops.sql',
        '103_household_only_points.sql',
        '104_invoice_only_points.sql',
        '105_remove_retired_gamification_tables.sql',
    ];

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS debi_schema_versions (
            version VARCHAR(80) NOT NULL,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $results = [];
    foreach ($files as $file) {
        $version = pathinfo($file, PATHINFO_FILENAME);
        $check = $pdo->prepare('SELECT 1 FROM debi_schema_versions WHERE version = :version LIMIT 1');
        $check->execute([':version' => $version]);
        if ($check->fetchColumn()) {
            $results[] = ['file' => $file, 'status' => 'skipped'];
            continue;
        }

        if (migration_schema_is_present($pdo, $version)) {
            $insert = $pdo->prepare('INSERT IGNORE INTO debi_schema_versions (version) VALUES (:version)');
            $insert->execute([':version' => $version]);
            $results[] = ['file' => $file, 'status' => 'legacy-skipped'];
            continue;
        }

        $path = $migrationDir . '/' . $file;
        if (!is_file($path)) {
            throw new RuntimeException("Migration dosyası bulunamadı: {$file}");
        }

        run_sql_migration_file($pdo, $path);
        $insert = $pdo->prepare('INSERT IGNORE INTO debi_schema_versions (version) VALUES (:version)');
        $insert->execute([':version' => $version]);
        $results[] = ['file' => $file, 'status' => 'applied'];
    }

    $pdo->exec("
        UPDATE households h
        SET h.owner_user_id = (
            SELECT MIN(u.id) FROM users u WHERE u.household_id = h.id
        )
        WHERE h.owner_user_id IS NULL
    ");

    $missingCodes = $pdo->query("
        SELECT id FROM households
        WHERE unit_invite_code IS NULL OR unit_invite_code = ''
    ")->fetchAll();
    if ($missingCodes) {
        $updateCode = $pdo->prepare('UPDATE households SET unit_invite_code = :code WHERE id = :id');
        foreach ($missingCodes as $row) {
            $updateCode->execute([
                ':code' => generate_invite_code('DAIRE'),
                ':id' => (int) $row['id'],
            ]);
        }
    }

    return $results;
}

/**
 * Eski otomatik bootstrap sürüm kaydı bırakmadığı için mevcut şemayı tanır.
 */
function migration_schema_is_present(PDO $pdo, string $version): bool
{
    $checks = [
        '005_post_images_and_alerts' => "SHOW COLUMNS FROM posts LIKE 'image_path'",
        '007_bill_images' => "SHOW COLUMNS FROM water_logs LIKE 'bill_image_path'",
        '008_friendships_and_timeline' => "SHOW TABLES LIKE 'friendships'",
        '009_profile_and_cover_photos' => "SHOW COLUMNS FROM users LIKE 'avatar_photo'",
        '010_household_unit_manager_and_residents' => "SHOW COLUMNS FROM households LIKE 'unit_invite_code'",
        '101_collective_pond_and_bill_points' => "SHOW COLUMNS FROM water_logs LIKE 'saved_m3'",
        '102_household_pond_and_ai_drops' => "SHOW COLUMNS FROM water_logs LIKE 'ai_verified'",
    ];

    if (!isset($checks[$version])) {
        if ($version !== '006_gamification_points') {
            return false;
        }

        $hasPointLogs = (bool) $pdo->query("SHOW TABLES LIKE 'point_logs'")->fetchColumn();
        $hasHouseholdPoints = (bool) $pdo->query(
            "SHOW COLUMNS FROM households LIKE 'total_points'"
        )->fetchColumn();
        return $hasPointLogs && $hasHouseholdPoints;
    }

    return (bool) $pdo->query($checks[$version])->fetchColumn();
}

/**
 * SQL dosyasını noktalı virgülle ayrılmış ifadeler halinde çalıştırır.
 */
function run_sql_migration_file(PDO $pdo, string $sqlFile): void
{
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        throw new RuntimeException("Migration okunamadı: {$sqlFile}");
    }

    $lines = array_filter(
        explode("\n", $sql),
        static function (string $line): bool {
            $trimmed = trim($line);
            return $trimmed !== '' && strpos($trimmed, '--') !== 0;
        }
    );

    foreach (explode(';', implode("\n", $lines)) as $statement) {
        $statement = trim($statement);
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }
}
