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
    $files = ['001_initial_schema.sql'];

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
 * Live dump daha önce tarihsel migration kayıtlarıyla kurulmuş veritabanını tanır.
 */
function migration_schema_is_present(PDO $pdo, string $version): bool
{
    if ($version !== '001_initial_schema') {
        return false;
    }

    $requiredTables = [
        'apartments',
        'comments',
        'friendships',
        'households',
        'invitations',
        'neighborhoods',
        'neighborhood_alerts',
        'point_logs',
        'posts',
        'post_reactions',
        'saving_habits',
        'users',
        'water_logs',
    ];

    $tableCheck = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = :table_name
    ");
    $existingTables = 0;
    foreach ($requiredTables as $table) {
        $tableCheck->execute([':table_name' => $table]);
        $existingTables += (int) $tableCheck->fetchColumn();
    }

    if ($existingTables !== 0 && $existingTables !== count($requiredTables)) {
        throw new RuntimeException(
            '001_initial_schema için veritabanı kısmen kurulu. Eksik tabloları elle inceleyin; otomatik tekrar çalıştırılmadı.'
        );
    }
    if ($existingTables === 0) {
        return false;
    }

    return (bool) $pdo->query("SHOW COLUMNS FROM water_logs LIKE 'ai_verified'")->fetchColumn()
        && (bool) $pdo->query("SHOW COLUMNS FROM households LIKE 'total_points'")->fetchColumn();
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
