<?php
/**
 * DEBI - Üst Başlık (Header)
 * $pageTitle ve $activeNav değişkenleri sayfalardan gönderilebilir.
 */
$pageTitle = $pageTitle ?? 'Debi — Su Tasarrufu Sosyal Ağı';
$activeNav = $activeNav ?? 'feed';
$flash     = get_flash();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: 3 ?>">
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <div class="brand-group">
                <a href="index.php" class="brand-logo">
                    <span class="brand-mark">debi</span>
                    <span class="brand-sub">mahalle &amp; apartman su ağı</span>
                </a>
            </div>

            <?php if (!empty($currentUser)): ?>
                <div class="topbar-context">
                    <a href="neighborhood.php" class="context-pill" title="Mahalle Sayfası">
                        <strong><?= e($currentUser['neighborhood_name']) ?></strong>
                        <span class="context-sep">/</span>
                        <span><?= e($currentUser['neighborhood_district'] . ', ' . $currentUser['neighborhood_city']) ?></span>
                    </a>

                    <?php if (!empty($currentUser['apartment_id'])): ?>
                        <a href="apartment.php" class="context-pill context-pill-apt" title="Apartman Sayfası">
                            <?= e($currentUser['apartment_name']) ?> (<?= e($currentUser['unit_label']) ?>)
                        </a>
                    <?php else: ?>
                        <span class="context-pill context-pill-ind">
                            <?= e(housing_type_label($currentUser['housing_type'])) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="topbar-user">
                    <a href="profile.php" class="user-chip">
                        <span class="avatar-circle avatar-sm" style="background-color: <?= e($currentUser['avatar_color']) ?>;">
                            <?= e(user_initials($currentUser['full_name'])) ?>
                        </span>
                        <span class="user-chip-name"><?= e($currentUser['full_name']) ?></span>
                    </a>
                    <a href="logout.php" class="btn btn-outline btn-sm">Çıkış</a>
                </div>
            <?php else: ?>
                <div class="topbar-user">
                    <a href="login.php" class="btn btn-outline btn-sm">Giriş Yap</a>
                    <a href="register.php" class="btn btn-primary btn-sm">Kayıt Ol</a>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($flash): ?>
        <div class="container-narrow flash-wrapper">
            <div class="alert alert-<?= e($flash['type']) ?>">
                <?= e($flash['message']) ?>
            </div>
        </div>
    <?php endif; ?>

