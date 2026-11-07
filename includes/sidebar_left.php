<?php
/**
 * DEBI - Sol Navigasyon Sütunu (Masaüstü) & Alt Sabit Menü (Mobil)
 */
$activeNav = $activeNav ?? 'feed';
$sideLevel = !empty($currentUser) ? get_level_info((int) ($currentUser['total_points'] ?? 50)) : null;
?>
<aside class="sidebar-left">
    <?php if (!empty($currentUser) && $sideLevel): ?>
        <!-- Sevimli Seviye & Damla Puanı Kartı -->
        <div class="panel-card level-hero-card">
            <div class="level-card-top">
                <span class="level-emoji-badge"><?= $sideLevel['emoji'] ?></span>
                <div class="level-card-info">
                    <span class="level-kicker">SEVİYE <?= (int) $sideLevel['level'] ?></span>
                    <strong class="level-title"><?= e($sideLevel['title']) ?></strong>
                </div>
                <span class="level-dp-count">💧 <?= number_format((int) $sideLevel['points'], 0, ',', '.') ?> DP</span>
            </div>

            <div class="level-progress-wrap">
                <div class="level-progress-track">
                    <div class="level-progress-fill" style="width: <?= (int) $sideLevel['progress_pct'] ?>%;"></div>
                </div>
                <div class="level-progress-caption">
                    <?php if ($sideLevel['remaining_points'] > 0): ?>
                        <span>Sonraki seviyeye <strong><?= (int) $sideLevel['remaining_points'] ?> DP</strong> kaldı!</span>
                    <?php else: ?>
                        <span>En üst seviyedesin, gerçek bir efsanesin! 👑</span>
                    <?php endif; ?>
                    <a href="profile.php#badges" class="link-subtle">Rozetlerim &rarr;</a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <nav class="side-nav" aria-label="Ana Menü">
        <a href="index.php" class="side-nav-link <?= $activeNav === 'feed' ? 'active' : '' ?>">
            <span class="nav-emoji">💬</span>
            <span>Komşu Akışı</span>
        </a>

        <a href="neighborhood.php" class="side-nav-link <?= $activeNav === 'neighborhood' ? 'active' : '' ?>">
            <span class="nav-emoji">🏘️</span>
            <span>Mahallem</span>
        </a>

        <a href="apartment.php" class="side-nav-link <?= $activeNav === 'apartment' ? 'active' : '' ?>">
            <span class="nav-emoji">🏢</span>
            <span>
                <?= !empty($currentUser['apartment_id']) ? 'Apartmanım' : 'Apartmana Katıl' ?>
            </span>
        </a>

        <a href="water.php" class="side-nav-link <?= $activeNav === 'water' ? 'active' : '' ?>">
            <span class="nav-emoji">💧</span>
            <span>Sayaç &amp; Görevler</span>
            <span class="nav-bonus-pill">+100 DP</span>
        </a>

        <a href="profile.php" class="side-nav-link <?= $activeNav === 'profile' ? 'active' : '' ?>">
            <span class="nav-emoji">🏅</span>
            <span>Profil &amp; Rozetler</span>
        </a>
    </nav>

    <?php if (!empty($currentUser)): ?>
        <div class="panel-card household-card">
            <div class="panel-card-header">
                <span class="panel-title">🏡 Hane Kimliği</span>
                <?php if ($currentUser['role'] === 'manager'): ?>
                    <span class="tag tag-manager">Yönetici</span>
                <?php endif; ?>
            </div>
            <div class="household-meta-list">
                <div class="meta-row">
                    <span class="meta-label">Mahalle:</span>
                    <span class="meta-val"><?= e($currentUser['neighborhood_name']) ?></span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">İlçe / İl:</span>
                    <span class="meta-val"><?= e($currentUser['neighborhood_district'] . ' / ' . $currentUser['neighborhood_city']) ?></span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Konut:</span>
                    <span class="meta-val">
                        <?php if (!empty($currentUser['apartment_id'])): ?>
                            <?= e($currentUser['apartment_name']) ?>
                        <?php else: ?>
                            <?= e(housing_type_label($currentUser['housing_type'])) ?>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Birim / Ev:</span>
                    <span class="meta-val"><?= e($currentUser['unit_label']) ?> (<?= (int) $currentUser['resident_count'] ?> kişi)</span>
                </div>
            </div>

            <?php if (!empty($currentUser['apartment_invite_code'])): ?>
                <div class="invite-mini-box">
                    <span class="invite-mini-label">🔑 Apartman Davet Kodu:</span>
                    <div class="invite-code-row">
                        <code id="sidebarInviteCode"><?= e($currentUser['apartment_invite_code']) ?></code>
                        <button type="button" class="btn-copy" data-copy="<?= e($currentUser['apartment_invite_code']) ?>">Kopyala</button>
                    </div>
                </div>
            <?php else: ?>
                <div class="invite-mini-box">
                    <span class="invite-mini-label">🌟 Mahallede Bireysel Üyesin</span>
                    <a href="apartment.php" class="link-subtle">Apartman davet kodun var mı? (+50 DP) &rarr;</a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</aside>

<!-- Mobil Alt Sabit Menü -->
<nav class="mobile-bottom-nav" aria-label="Mobil Menü">
    <a href="index.php" class="mobile-nav-item <?= $activeNav === 'feed' ? 'active' : '' ?>">
        <span class="mobile-nav-emoji">💬</span>
        <span>Akış</span>
    </a>
    <a href="neighborhood.php" class="mobile-nav-item <?= $activeNav === 'neighborhood' ? 'active' : '' ?>">
        <span class="mobile-nav-emoji">🏘️</span>
        <span>Mahalle</span>
    </a>
    <a href="water.php" class="mobile-nav-item <?= $activeNav === 'water' ? 'active' : '' ?>">
        <span class="mobile-nav-emoji">💧</span>
        <span>Sayaç &amp; DP</span>
    </a>
    <a href="apartment.php" class="mobile-nav-item <?= $activeNav === 'apartment' ? 'active' : '' ?>">
        <span class="mobile-nav-emoji">🏢</span>
        <span>Apartman</span>
    </a>
    <a href="profile.php" class="mobile-nav-item <?= $activeNav === 'profile' ? 'active' : '' ?>">
        <span class="mobile-nav-emoji">🏅</span>
        <span>Rozetler</span>
    </a>
</nav>
