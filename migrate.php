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
    $results = run_database_migrations(db());
    header('Content-Type: text/plain; charset=utf-8');
    foreach ($results as $result) {
        printf("%s: %s\n", $result['file'], $result['status']);
    }
    echo "Migration tamamlandı.\n";
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Migration başarısız: " . $e->getMessage() . "\n";
    exit(1);
}
