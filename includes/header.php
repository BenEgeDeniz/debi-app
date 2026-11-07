<?php
/**
 * DEBI - Üst Başlık (Header)
 * $pageTitle ve $activeNav değişkenleri sayfalardan gönderilebilir.
 */
$pageTitle        = $pageTitle ?? 'Debi — Su Tasarrufu Sosyal Ağı';
$activeNav        = $activeNav ?? 'feed';
$flash            = get_flash();
$pendingFriendCnt = !empty($currentUser) && function_exists('count_incoming_friend_requests')
    ? count_incoming_friend_requests((int) $currentUser['id'])
    : 0;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: 6 ?>">
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <div class="brand-group">
                <a href="index.php" class="brand-logo">
                    <svg class="brand-mascot" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M22 3C22 3 7 18.2 7 28.5C7 36.5 13.7 42 22 42C30.3 42 37 36.5 37 28.5C37 18.2 22 3 22 3Z" fill="#1CB0F6" stroke="#118AB2" stroke-width="2.5"/>
                        <path d="M13 24C12 19 16 12 20 8" stroke="#72D6FF" stroke-width="2.5" stroke-linecap="round"/>
                        <circle cx="16.5" cy="27" r="2.3" fill="#1D3557"/>
                        <circle cx="27.5" cy="27" r="2.3" fill="#1D3557"/>
                        <circle cx="15.8" cy="26.2" r="0.8" fill="#FFFFFF"/>
                        <circle cx="26.8" cy="26.2" r="0.8" fill="#FFFFFF"/>
                        <ellipse cx="13.5" cy="30" rx="2.2" ry="1.2" fill="#FF8FA3"/>
                        <ellipse cx="30.5" cy="30" rx="2.2" ry="1.2" fill="#FF8FA3"/>
                        <path d="M18.5 31.2C19.6 33 24.4 33 25.5 31.2" stroke="#1D3557" stroke-width="2.2" stroke-linecap="round"/>
                    </svg>
                    <div class="brand-text">
                        <span class="brand-mark">debi</span>
                        <span class="brand-sub">sosyal su ağı</span>
                    </div>
                </a>
            </div>

            <?php if (!empty($currentUser)): ?>
                <div class="topbar-context">
                    <?php if (!empty($currentUser['apartment_id'])): ?>
                        <a href="apartment.php" class="context-pill context-pill-apt" title="Apartman Sayfası">
                            <span><?= e($currentUser['apartment_name']) ?> (<?= e($currentUser['unit_label']) ?>)</span>
                        </a>
                    <?php else: ?>
                        <span class="context-pill context-pill-ind">
                            <span><?= e(housing_type_label($currentUser['housing_type'])) ?></span>
                        </span>
                    <?php endif; ?>

                    <a href="friends.php" class="context-pill" title="Arkadaşlar">
                        <span>Arkadaşlar</span>
                        <?php if ($pendingFriendCnt > 0): ?>
                            <span class="nav-badge-count"><?= $pendingFriendCnt ?></span>
                        <?php endif; ?>
                    </a>
                </div>

                <div class="topbar-user">
                    <a href="profile.php" class="user-chip" title="Zaman Tünelim">
                        <?= render_avatar($currentUser, 'avatar-sm') ?>
                        <span class="user-chip-name"><?= e($currentUser['full_name']) ?></span>
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
