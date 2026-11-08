<?php
require_once __DIR__ . '/includes/init.php';

$currentUser = require_login();
header('Content-Type: application/json; charset=utf-8');

try {
    $stmt = db()->prepare("
        SELECT n.id, n.post_id, n.comment_id, n.message, n.created_at,
               u.nickname AS actor_nickname
        FROM notifications n
        INNER JOIN users u ON u.id = n.actor_user_id
        WHERE n.user_id = :uid
        ORDER BY n.created_at DESC
        LIMIT 3
    ");
    $stmt->execute([':uid' => (int) $currentUser['id']]);
    $notifications = array_map(static function (array $notification): array {
        return [
            'id' => (int) $notification['id'],
            'post_id' => $notification['post_id'] !== null ? (int) $notification['post_id'] : null,
            'message' => (string) $notification['message'],
            'actor_nickname' => (string) $notification['actor_nickname'],
            'created_at' => time_ago($notification['created_at']),
        ];
    }, $stmt->fetchAll());

    $unreadStmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0');
    $unreadStmt->execute([':uid' => (int) $currentUser['id']]);

    echo json_encode([
        'ok' => true,
        'unread_count' => (int) $unreadStmt->fetchColumn(),
        'notifications' => $notifications,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    error_log('Notification polling failed: ' . $exception->getMessage());
    echo json_encode(['ok' => false, 'message' => 'Bildirimler alınamadı.'], JSON_UNESCAPED_UNICODE);
}
