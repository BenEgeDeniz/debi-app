<?php
/**
 * DEBI - Üst Başlık (Header)
 * $pageTitle ve $activeNav değişkenleri sayfalardan gönderilebilir.
 */
$pageTitle        = $pageTitle ?? 'Debi — Su Tasarrufu Sosyal Ağı';
$activeNav        = $activeNav ?? 'feed';
$flash            = get_flash();
if (!function_exists('count_unread_notifications')) {
    $feedServicePath = __DIR__ . '/../modules/feed/feed_service.php';
    if (is_file($feedServicePath)) {
        require_once $feedServicePath;
    }
}
$eventServicePath = __DIR__ . '/../modules/events/event_service.php';
if (is_file($eventServicePath)) {
    require_once $eventServicePath;
}
$pendingFriendCnt = !empty($currentUser) && function_exists('count_incoming_friend_requests')
    ? count_incoming_friend_requests((int) $currentUser['id'])
    : 0;
$unreadNotificationCnt = 0;
$headerNotifications = [];
$eventStartModal = null;
if (!empty($currentUser)) {
    try {
        if (function_exists('count_unread_notifications')) {
            $unreadNotificationCnt = count_unread_notifications((int) $currentUser['id']);
        }
        $notificationStmt = db()->prepare("
            SELECT n.id, n.post_id, n.comment_id, n.message, n.created_at,
                   u.full_name AS actor_name, u.nickname AS actor_nickname
            FROM notifications n
            INNER JOIN users u ON u.id = n.actor_user_id
            WHERE n.user_id = :uid
            ORDER BY n.created_at DESC
            LIMIT 3
        ");
        $notificationStmt->execute([':uid' => (int) $currentUser['id']]);
        $headerNotifications = $notificationStmt->fetchAll();
        $seenEventStarts = $_SESSION['seen_event_start_modal'] ?? [];
        $startedEvent = function_exists('get_started_created_event')
            ? get_started_created_event((int) $currentUser['id'])
            : null;
        if ($startedEvent && empty($seenEventStarts[(string) $startedEvent['id']])) {
            $eventStartModal = $startedEvent;
            $seenEventStarts[(string) $startedEvent['id']] = time();
            $_SESSION['seen_event_start_modal'] = $seenEventStarts;
        }
    } catch (Throwable $e) {
        $unreadNotificationCnt = 0;
        $headerNotifications = [];
    }
}
$headerPondDrops  = 0;
$headerPondPct    = 0.0;
if (!empty($currentUser)) {
    if (!function_exists('get_household_pond_state')) {
        require_once __DIR__ . '/../modules/water/water_service.php';
    }
    $headerPond      = get_household_pond_state((int) ($currentUser['household_id'] ?? 0));
    $headerPondDrops = (int) ($headerPond['total_drops'] ?? 0);
    $headerPondPct   = (float) ($headerPond['doluluk_yuzdesi'] ?? 0.0);
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" type="image/png" href="<?= e(brand_asset_path('logo')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: 7 ?>">
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <div class="brand-group">
                <a href="index.php" class="brand-logo">
                    <?= render_brand_logo('brand-mascot') ?>
                    <div class="brand-text">
                        <span class="brand-mark">debi</span>
                        <span class="brand-sub">sosyal su ağı</span>
                    </div>
                </a>
            </div>

            <?php if (!empty($currentUser)): ?>
                <div class="topbar-user">
                    <a href="store.php" class="topbar-drop-pill" id="navbarDpPill" title="Mağazalar ve Kampanyalar sayfasına git">
                        <span class="topbar-drop-icon">💧</span>
                        <span class="topbar-drop-count" id="navbarDpCount" data-dp="<?= (int) $headerPondDrops ?>"><?= number_format($headerPondDrops, 0, ',', '.') ?> DP</span>
                    </a>

                    <?php if ($pendingFriendCnt > 0): ?>
                        <a href="friends.php" class="topbar-notif-pill" title="Gelen Arkadaşlık İstekleri">
                            <span><?= $pendingFriendCnt ?> yeni istek</span>
                        </a>
                    <?php endif; ?>
                    <div class="notification-menu">
                        <button type="button" class="topbar-notif-pill notification-toggle" aria-expanded="false">
                            <svg class="notification-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>
                                <path d="M10 21h4"/>
                            </svg>
                            <?php if ($unreadNotificationCnt > 0): ?><b><?= $unreadNotificationCnt ?></b><?php endif; ?>
                        </button>
                        <div class="notification-dropdown" hidden>
                            <div class="notification-dropdown-header">
                                <strong>Bildirimler</strong>
                                <a href="notifications.php">Tümü</a>
                            </div>
                            <?php if (!$headerNotifications): ?>
                                <div class="notification-empty">Henüz bildirimin yok.</div>
                            <?php else: ?>
                                <?php foreach ($headerNotifications as $notification): ?>
                                    <?php $notificationTarget = !empty($notification['post_id'])
                                        ? 'post.php?id=' . (int) $notification['post_id'] . '&notification=' . (int) $notification['id']
                                        : 'notifications.php'; ?>
                                    <a class="notification-dropdown-item <?= $notification['post_id'] ? '' : 'is-static' ?>"
                                       href="<?= e($notificationTarget) ?>">
                                        <strong>@<?= e($notification['actor_nickname']) ?></strong>
                                        <span><?= e($notification['message']) ?></span>
                                        <time><?= e(time_ago($notification['created_at'])) ?></time>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <a class="notification-dropdown-all" href="notifications.php">Tümünü gör</a>
                        </div>
                    </div>

                    <a href="profile.php" class="user-chip" title="Zaman Tünelim">
                        <?= render_avatar($currentUser, 'avatar-sm') ?>
                        <div class="user-chip-meta">
                            <span class="user-chip-name"><?= e($currentUser['full_name']) ?></span>
                            <span class="user-chip-sub">@<?= e($currentUser['nickname'] ?? '') ?></span>
                        </div>
                    </a>
                    <a href="logout.php" class="btn btn-outline btn-sm">Çıkış</a>
                </div>
            <?php else: ?>
                <div class="topbar-user">
                    <a href="login.php" class="btn btn-outline btn-sm">Giriş Yap</a>
                    <a href="register.php" class="btn btn-primary btn-sm">Katıl</a>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($flash): ?>
        <div class="container-narrow flash-wrapper">
            <div class="alert alert-<?= e($flash['type']) ?>">
                <span><?= e($flash['message']) ?></span>
            </div>
        </div>
    <?php endif; ?>
    <?php if ($eventStartModal): ?>
        <div class="event-start-modal" id="eventStartModal" role="dialog" aria-modal="true" aria-labelledby="eventStartModalTitle">
            <div class="event-start-modal-card">
                <button type="button" class="event-start-modal-close" data-close-event-modal aria-label="Kapat">×</button>
                <span class="event-scope-label">Etkinlik başladı</span>
                <h2 id="eventStartModalTitle"><?= e($eventStartModal['title']) ?></h2>
                <p>Etkinliğiniz başladı. Katılımcılar geldiğinde yoklamayı etkinlikler sayfasından işaretleyebilirsiniz.</p>
                <a class="btn btn-primary" href="events.php">Etkinliğe git</a>
            </div>
        </div>
    <?php endif; ?>
