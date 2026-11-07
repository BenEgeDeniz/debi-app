<?php
/**
 * DEBI - Migration çalıştırıcısı
 *
 * Bu dosya uygulama tarafından otomatik çağrılmaz. Migration uygulamak için
 * yalnızca yönetici tarafından açıkça açılmalıdır.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/modules/database/migration_service.php';

if (PHP_SAPI !== 'cli') {
    header('Location: migration.php');
    exit;
}

function parse_migration_cli_options(array $argv): array
{
    $options = ['from' => null, 'to' => null, 'force' => false];
    foreach (array_slice($argv, 2) as $argument) {
        if ($argument === '--force') {
            $options['force'] = true;
        } elseif (preg_match('/^--from=(\d+)$/', $argument, $matches)) {
            $options['from'] = (int) $matches[1];
        } elseif (preg_match('/^--to=(\d+)$/', $argument, $matches)) {
            $options['to'] = (int) $matches[1];
        } else {
            throw new InvalidArgumentException(
                'Kullanım: php migrate.php migrate [--from=2] [--to=5] [--force]'
            );
        }
    }
    if ($options['from'] !== null && $options['to'] !== null && $options['from'] > $options['to']) {
        throw new InvalidArgumentException('--from değeri --to değerinden büyük olamaz.');
    }
    return $options;
}

try {
    $pdo = db();
    $action = PHP_SAPI === 'cli' ? ($argv[1] ?? 'migrate') : ($_GET['action'] ?? 'status');
    $options = PHP_SAPI === 'cli' ? parse_migration_cli_options($argv) : [];

    header('Content-Type: text/plain; charset=utf-8');
    if ($action === 'status') {
        foreach (get_database_migration_status($pdo) as $result) {
            printf(
                "%s: %s%s\n",
                $result['file'],
                $result['status'],
                $result['checksum_changed'] ? ' (checksum changed)' : ''
            );
        }
    } elseif ($action === 'migrate') {
        foreach (run_database_migrations(
            $pdo,
            $options['from'],
            $options['to'],
            $options['force']
        ) as $result) {
            printf("%s: %s\n", $result['file'], $result['status']);
        }
        echo "Migration tamamlandı.\n";
    } else {
        http_response_code(400);
        echo "Kullanım: migrate.php?action=status veya migrate.php?action=migrate\n";
        exit(1);
    }

} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Migration başarısız: " . $e->getMessage() . "\n";
    exit(1);
}
