<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../modules/events/event_service.php';

try {
    $cancelled = cancel_events_without_attendance();
    fwrite(STDOUT, "Events queue: {$cancelled} event(s) cancelled.\n");
} catch (Throwable $exception) {
    error_log('Events worker failed: ' . $exception->getMessage());
    fwrite(STDERR, "Events worker failed.\n");
    exit(1);
}
