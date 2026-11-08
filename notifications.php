<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/modules/feed/feed_service.php';

$currentUser = require_login();
$stmt = db()->prepare("
    SELECT n.*, u.full_name AS actor_name, u.nickname AS actor_nickname
    FROM notifications n
    INNER JOIN users u ON u.id = n.actor_user_id
    WHERE n.user_id = :uid
    ORDER BY n.created_at DESC
    LIMIT 50
");
$stmt->execute([':uid' => (int) $currentUser['id']]);
$notifications = $stmt->fetchAll();
db()->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid")->execute([':uid' => (int) $currentUser['id']]);

$pageTitle = 'Bildirimler — Debi';
$activeNav = 'notifications';
require_once __DIR__ . '/includes/header.php';
?>
<div class="app-shell app-shell-2col">
    <?php require_once __DIR__ . '/includes/sidebar_left.php'; ?>
    <main class="main-column">
        <section class="panel-card">
            <div class="panel-card-header">
                <h1 class="section-title">Bildirimler</h1>
            </div>
            <?php if (!$notifications): ?>
                <div class="empty-inline">Henüz bildirimin yok.</div>
            <?php else: ?>
                <div class="notification-list">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item">
                            <strong>@<?= e($notification['actor_nickname']) ?></strong>
                            <span><?= e($notification['message']) ?></span>
                            <time><?= e(time_ago($notification['created_at'])) ?></time>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
