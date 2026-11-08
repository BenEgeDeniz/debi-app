<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../modules/feed/feed_service.php';

try {
    $processed = debbie_process_queue_batch(2);
    fwrite(STDOUT, "Debbie queue: {$processed} job(s) processed.\n");
} catch (Throwable $exception) {
    error_log('Debbie worker failed: ' . $exception->getMessage());
    fwrite(STDERR, "Debbie worker failed.\n");
    exit(1);
}
