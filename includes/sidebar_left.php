<?php
/**
 * DEBI - Sol Navigasyon Sütunu (Masaüstü) & Alt Sabit Menü (Mobil)
 */
$activeNav        = $activeNav ?? 'feed';
$isDetachedHome   = !empty($currentUser) && ($currentUser['housing_type'] ?? '') === 'detached_house' && empty($currentUser['apartment_id']);
$pendingFriendCnt = !empty($currentUser) && function_exists('count_incoming_friend_requests')
    ? count_incoming_friend_requests((int) $currentUser['id'])
    : 0;
$unitFormatted    = !empty($currentUser)
    ? format_unit_label($currentUser['unit_label'] ?? '', $currentUser['housing_type'] ?? 'apartment_unit')
    : '';
?>
<aside class="sidebar-left">
    <?php if (!empty($currentUser)): ?>
        <div class="sidebar-role-card">
            <div class="sidebar-role-user">
                <?= render_avatar($currentUser, 'avatar-sm') ?>
                <div class="sidebar-role-text">
                    <strong><?= e($currentUser['full_name']) ?></strong>
                    <span>
                        <?php if (!empty($currentUser['apartment_id'])): ?>
                            <?= e($currentUser['apartment_name']) ?> &middot; <?= e($unitFormatted) ?>
                        <?php else: ?>
                            <?= e($currentUser['neighborhood_district']) ?> &middot; <?= e($unitFormatted) ?>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <div class="sidebar-role-badges">
                <?php if ($currentUser['role'] === 'manager'): ?>
                    <span class="role-badge role-badge-apt">🏢 Apartman Yöneticisi</span>
                <?php endif; ?>

                <?php if (is_unit_manager($currentUser)): ?>
                    <span class="role-badge role-badge-unit">
                        🏠 <?= $isDetachedHome ? 'Hane Yöneticisi' : e($unitFormatted) . ' Yöneticisi' ?>
                    </span>
                <?php else: ?>
                    <span class="role-badge role-badge-resident">
                        👤 <?= e($unitFormatted) ?> Sakini
                    </span>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <nav class="side-nav" aria-label="Ana Menü">
        <a href="index.php" class="side-nav-link <?= $activeNav === 'feed' ? 'active' : '' ?>">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <span>Sosyal Akış</span>
        </a>

        <a href="profile.php" class="side-nav-link <?= $activeNav === 'profile' ? 'active' : '' ?>">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span>Zaman Tünelim</span>
        </a>

        <a href="friends.php" class="side-nav-link <?= $activeNav === 'friends' ? 'active' : '' ?>">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <span>Arkadaşlar</span>
            <?php if ($pendingFriendCnt > 0): ?>
                <span class="nav-badge-count"><?= $pendingFriendCnt ?></span>
            <?php endif; ?>
        </a>

        <a href="water.php" class="side-nav-link <?= $activeNav === 'water' ? 'active' : '' ?>">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>
            <span>Su Faturalarım</span>
        </a>

        <a href="analytics.php" class="side-nav-link <?= $activeNav === 'analytics' ? 'active' : '' ?>">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            <span>Su Analizi &amp; Puan Göleti</span>
        </a>

        <?php if (!$isDetachedHome): ?>
            <a href="apartment.php" class="side-nav-link <?= $activeNav === 'apartment' ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/></svg>
                <span>
                    <?= !empty($currentUser['apartment_id']) ? 'Apartman & Dairem' : 'Apartmana Katıl' ?>
                </span>
            </a>
        <?php endif; ?>
    </nav>
</aside>

<!-- Mobil Alt Sabit Menü -->
<nav class="mobile-bottom-nav <?= $isDetachedHome ? 'mobile-nav-4' : '' ?>" aria-label="Mobil Menü">
    <a href="index.php" class="mobile-nav-item <?= $activeNav === 'feed' ? 'active' : '' ?>">
        <span>Akış</span>
    </a>
    <a href="friends.php" class="mobile-nav-item <?= $activeNav === 'friends' ? 'active' : '' ?>">
        <span>Arkadaşlar<?= $pendingFriendCnt > 0 ? ' (' . $pendingFriendCnt . ')' : '' ?></span>
    </a>
    <a href="water.php" class="mobile-nav-item <?= $activeNav === 'water' ? 'active' : '' ?>">
        <span>Fatura</span>
    </a>
    <a href="analytics.php" class="mobile-nav-item <?= $activeNav === 'analytics' ? 'active' : '' ?>">
        <span>Analiz</span>
    </a>
    <?php if (!$isDetachedHome): ?>
        <a href="apartment.php" class="mobile-nav-item <?= $activeNav === 'apartment' ? 'active' : '' ?>">
            <span>Apartman</span>
        </a>
    <?php endif; ?>
    <a href="profile.php" class="mobile-nav-item <?= $activeNav === 'profile' ? 'active' : '' ?>">
        <span>Profil</span>
    </a>
</nav>
