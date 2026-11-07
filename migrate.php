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

try {
    $pdo = db();
    $action = PHP_SAPI === 'cli'
        ? ($argv[1] ?? 'migrate')
        : ($_GET['action'] ?? 'migrate');

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
        foreach (run_database_migrations($pdo) as $result) {
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
