<?php
/**
 * DEBI - Elle çalıştırılan veritabanı migration servisi
 */

const DEBI_MIGRATION_TABLE = 'debi_migrations';

/**
 * Migration klasöründeki SQL dosyalarını artan dosya adına göre uygular.
 */
function run_database_migrations(
    PDO $pdo,
    ?int $from = null,
    ?int $to = null,
    bool $force = false
): array
{
    if (!$force) {
        throw new RuntimeException(
            'Migration çalıştırmak için açıkça force seçeneği gereklidir.'
        );
    }
    $migrationDir = __DIR__ . '/../../sql-dum';
    $migrations = discover_database_migrations($migrationDir);
    ensure_migration_repository($pdo);

    $applied = load_applied_migrations($pdo);
    if ($from !== null) {
        foreach ($migrations as $migration) {
            if ($migration['number'] >= $from || isset($applied[$migration['name']])) {
                continue;
            }
            throw new RuntimeException(
                "Sıra atlanamaz: {$migration['name']} önce uygulanmalı."
            );
        }
    }
    $nextBatch = next_migration_batch($pdo);
    $results = [];
    $batchStarted = false;

    foreach ($migrations as $migration) {
        $name = $migration['name'];
        $checksum = $migration['checksum'];
        $number = $migration['number'];

        if (($from !== null && $number < $from) || ($to !== null && $number > $to)) {
            $results[] = ['file' => $name, 'status' => 'out-of-range'];
            continue;
        }

        if (isset($applied[$name])) {
            if (!hash_equals($applied[$name]['checksum'], $checksum)) {
                throw new RuntimeException(
                    "Uygulanmış migration değiştirilemez: {$name}. Yeni sıra numarasıyla yeni dosya oluşturun."
                );
            }
            $results[] = ['file' => $name, 'status' => 'skipped'];
            continue;
        }

        if ($name === '001_initial_schema.sql' && migration_schema_is_present($pdo)) {
            record_applied_migration($pdo, $name, $checksum, $nextBatch);
            $results[] = ['file' => $name, 'status' => 'baseline-recorded'];
            $nextBatch++;
            continue;
        }

        if (!$batchStarted) {
            $nextBatch = next_migration_batch($pdo);
            $batchStarted = true;
        }

        run_sql_migration_file($pdo, $migration['path']);
        record_applied_migration($pdo, $name, $checksum, $nextBatch);
        $results[] = ['file' => $name, 'status' => 'applied'];
    }

    return $results;
}

/**
 * Migration durumunu, dosya checksum uyuşmazlıklarını da göstererek döndürür.
 */
function get_database_migration_status(PDO $pdo): array
{
    $migrationDir = __DIR__ . '/../../sql-dum';
    ensure_migration_repository($pdo);
    $applied = load_applied_migrations($pdo);
    $status = [];

    foreach (discover_database_migrations($migrationDir) as $migration) {
        $name = $migration['name'];
        $status[] = [
            'file' => $name,
            'number' => $migration['number'],
            'status' => isset($applied[$name]) ? 'applied' : 'pending',
            'checksum_changed' => isset($applied[$name])
                && !hash_equals($applied[$name]['checksum'], $migration['checksum']),
            'batch' => $applied[$name]['batch'] ?? null,
        ];
    }

    return $status;
}

/**
 * Yalnızca .sql migration dosyalarını dosya adına göre sıralar.
 */
function discover_database_migrations(string $migrationDir): array
{
    $paths = glob($migrationDir . '/*.sql');
    if ($paths === false) {
        throw new RuntimeException("Migration klasörü okunamadı: {$migrationDir}");
    }

    sort($paths, SORT_STRING);
    $migrations = [];
    $numbers = [];
    foreach ($paths as $path) {
        $name = basename($path);
        $number = migration_number($name);
        if (isset($numbers[$number])) {
            throw new RuntimeException(
                "Aynı sıra numarasını kullanan migration'lar var: {$numbers[$number]} ve {$name}"
            );
        }
        $numbers[$number] = $name;
        $migrations[] = [
            'name' => $name,
            'number' => $number,
            'path' => $path,
            'checksum' => hash_file('sha256', $path),
        ];
    }

    return $migrations;
}

function migration_number(string $name): int
{
    if (!preg_match('/^(\d+)_.*\.sql$/', $name, $matches)) {
        throw new RuntimeException(
            "Migration dosya adı sıra_no_aciklama.sql biçiminde olmalı: {$name}"
        );
    }
    return (int) $matches[1];
}

function ensure_migration_repository(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `" . DEBI_MIGRATION_TABLE . "` (
            `migration` VARCHAR(190) NOT NULL,
            `batch` INT UNSIGNED NOT NULL,
            `checksum` CHAR(64) NOT NULL,
            `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`migration`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec('DROP TABLE IF EXISTS `debi_schema_versions`');
}

function load_applied_migrations(PDO $pdo): array
{
    $rows = $pdo->query("
        SELECT migration, batch, checksum
        FROM `" . DEBI_MIGRATION_TABLE . "`
    ")->fetchAll();

    $applied = [];
    foreach ($rows as $row) {
        $applied[$row['migration']] = [
            'batch' => (int) $row['batch'],
            'checksum' => (string) $row['checksum'],
        ];
    }
    return $applied;
}

function next_migration_batch(PDO $pdo): int
{
    $batch = $pdo->query("
        SELECT COALESCE(MAX(batch), 0) + 1
        FROM `" . DEBI_MIGRATION_TABLE . "`
    ")->fetchColumn();
    return max(1, (int) $batch);
}

function record_applied_migration(PDO $pdo, string $name, string $checksum, int $batch): void
{
    $stmt = $pdo->prepare("
        INSERT INTO `" . DEBI_MIGRATION_TABLE . "` (migration, batch, checksum)
        VALUES (:migration, :batch, :checksum)
    ");
    $stmt->execute([
        ':migration' => $name,
        ':batch' => $batch,
        ':checksum' => $checksum,
    ]);
}

function migration_schema_is_present(PDO $pdo): bool
{
    $requiredTables = [
        'apartments', 'comments', 'friendships', 'households', 'invitations',
        'neighborhoods', 'neighborhood_alerts', 'point_logs', 'posts',
        'post_reactions', 'saving_habits', 'users', 'water_logs',
    ];
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = :table_name
    ");
    $existingTables = 0;
    foreach ($requiredTables as $table) {
        $stmt->execute([':table_name' => $table]);
        $existingTables += (int) $stmt->fetchColumn();
    }

    if ($existingTables !== 0 && $existingTables !== count($requiredTables)) {
        throw new RuntimeException(
            '001_initial_schema için veritabanı kısmen kurulu; otomatik kurulum durduruldu.'
        );
    }
    return $existingTables === count($requiredTables);
}

/**
 * SQL dump içindeki tırnaklı metinleri koruyarak ifadeleri çalıştırır.
 */
function run_sql_migration_file(PDO $pdo, string $sqlFile): void
{
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        throw new RuntimeException("Migration okunamadı: {$sqlFile}");
    }

    $statement = '';
    $quote = null;
    $length = strlen($sql);
    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $sql[$i + 1] ?? '';

        if ($quote !== null) {
            $statement .= $char;
            if ($char === '\\' && $next !== '') {
                $statement .= $next;
                $i++;
            } elseif ($char === $quote) {
                $quote = null;
            }
            continue;
        }

        if ($char === "'" || $char === '"' || $char === '`') {
            $quote = $char;
            $statement .= $char;
        } elseif ($char === ';') {
            execute_migration_statement($pdo, $statement);
            $statement = '';
        } else {
            $statement .= $char;
        }
    }
    execute_migration_statement($pdo, $statement);
}

function execute_migration_statement(PDO $pdo, string $statement): void
{
    $statement = trim(preg_replace('/^\s*(--[^\n]*\n|\/\*.*?\*\/\s*)+/s', '', $statement) ?? '');
    if ($statement !== '') {
        $pdo->exec($statement);
    }
}
