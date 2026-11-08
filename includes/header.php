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
$pendingFriendCnt = !empty($currentUser) && function_exists('count_incoming_friend_requests')
    ? count_incoming_friend_requests((int) $currentUser['id'])
    : 0;
$unreadNotificationCnt = 0;
if (!empty($currentUser)) {
    try {
        if (function_exists('count_unread_notifications')) {
            $unreadNotificationCnt = count_unread_notifications((int) $currentUser['id']);
        }
    } catch (Throwable $e) {
        $unreadNotificationCnt = 0;
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
                    <a href="index.php#daireGoleti" class="topbar-drop-pill" title="Daire Göletinizdeki toplam damla">
                        <span class="topbar-drop-icon">💧</span>
                        <span class="topbar-drop-count"><?= number_format($headerPondDrops, 0, ',', '.') ?> Damla</span>
                    </a>

                    <?php if ($pendingFriendCnt > 0): ?>
                        <a href="friends.php" class="topbar-notif-pill" title="Gelen Arkadaşlık İstekleri">
                            <span><?= $pendingFriendCnt ?> yeni istek</span>
                        </a>
                    <?php endif; ?>
                    <?php if ($unreadNotificationCnt > 0): ?>
                        <a href="notifications.php" class="topbar-notif-pill" title="Bildirimler"><?= $unreadNotificationCnt ?> bildirim</a>
                    <?php endif; ?>

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
