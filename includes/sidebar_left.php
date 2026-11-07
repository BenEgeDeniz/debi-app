<?php
/**
 * DEBI - Sol Navigasyon Sütunu (Masaüstü) & Alt Sabit Menü (Mobil)
 */
$activeNav        = $activeNav ?? 'feed';
$isDetachedHome   = !empty($currentUser) && ($currentUser['housing_type'] ?? '') === 'detached_house' && empty($currentUser['apartment_id']);
$pendingFriendCnt = !empty($currentUser) && function_exists('count_incoming_friend_requests')
    ? count_incoming_friend_requests((int) $currentUser['id'])
    : 0;
?>
<aside class="sidebar-left">
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
            <span>Fatura Yükle &amp; Takip</span>
        </a>

        <?php if (!$isDetachedHome): ?>
            <a href="apartment.php" class="side-nav-link <?= $activeNav === 'apartment' ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/></svg>
                <span>
                    <?= !empty($currentUser['apartment_id']) ? 'Apartmanım' : 'Apartmana Katıl' ?>
                </span>
            </a>
        <?php endif; ?>
    </nav>

    <?php if (!empty($currentUser)): ?>
        <div class="panel-card household-card">
            <div class="panel-card-header">
                <span class="panel-title">Hane Bilgisi</span>
                <?php if ($currentUser['role'] === 'manager'): ?>
                    <span class="tag tag-manager">Yönetici</span>
                <?php endif; ?>
            </div>
            <div class="household-meta-list">
                <div class="meta-row">
                    <span class="meta-label">İlçe / İl</span>
                    <span class="meta-val"><?= e($currentUser['neighborhood_district'] . ' / ' . $currentUser['neighborhood_city']) ?></span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Konut</span>
                    <span class="meta-val">
                        <?php if (!empty($currentUser['apartment_id'])): ?>
                            <?= e($currentUser['apartment_name']) ?>
                        <?php else: ?>
                            <?= e(housing_type_label($currentUser['housing_type'])) ?>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="meta-row">
                    <span class="meta-label"><?= $isDetachedHome ? 'Hane' : 'Daire' ?></span>
                    <span class="meta-val"><?= e($currentUser['unit_label']) ?> · <?= (int) $currentUser['resident_count'] ?> kişi</span>
                </div>
            </div>

            <?php if (!empty($currentUser['apartment_invite_code']) && $activeNav !== 'apartment'): ?>
                <div class="invite-mini-box">
                    <span class="invite-mini-label">Apartman Davet Kodu</span>
                    <div class="invite-code-row">
                        <code id="sidebarInviteCode"><?= e($currentUser['apartment_invite_code']) ?></code>
                        <button type="button" class="btn-copy" data-copy="<?= e($currentUser['apartment_invite_code']) ?>">Kopyala</button>
                    </div>
                </div>
            <?php elseif (empty($currentUser['apartment_id']) && !$isDetachedHome && $activeNav !== 'apartment'): ?>
                <div class="invite-mini-box">
                    <a href="apartment.php" class="link-subtle">Apartman davet kodunuz var mı? &rarr;</a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
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
    <?php if (!$isDetachedHome): ?>
        <a href="apartment.php" class="mobile-nav-item <?= $activeNav === 'apartment' ? 'active' : '' ?>">
            <span>Apartman</span>
        </a>
    <?php endif; ?>
    <a href="profile.php" class="mobile-nav-item <?= $activeNav === 'profile' ? 'active' : '' ?>">
        <span>Profil</span>
    </a>
</nav>
