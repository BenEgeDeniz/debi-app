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
            <span>Sosyal Akış</span>
        </a>

        <a href="profile.php" class="side-nav-link <?= $activeNav === 'profile' ? 'active' : '' ?>">
            <span>Zaman Tünelim</span>
        </a>

        <a href="friends.php" class="side-nav-link <?= $activeNav === 'friends' ? 'active' : '' ?>">
            <span>Arkadaşlar</span>
            <?php if ($pendingFriendCnt > 0): ?>
                <span class="nav-badge-count"><?= $pendingFriendCnt ?></span>
            <?php endif; ?>
        </a>

        <a href="water.php" class="side-nav-link <?= $activeNav === 'water' ? 'active' : '' ?>">
            <span>Fatura Yükle &amp; Takip</span>
        </a>

        <?php if (!$isDetachedHome): ?>
            <a href="apartment.php" class="side-nav-link <?= $activeNav === 'apartment' ? 'active' : '' ?>">
                <span>
                    <?= !empty($currentUser['apartment_id']) ? 'Apartmanım' : 'Apartmana Katıl' ?>
                </span>
            </a>
        <?php endif; ?>
    </nav>

    <?php if (!empty($currentUser)): ?>
        <div class="panel-card household-card">
            <div class="panel-card-header">
                <span class="panel-title">Hane Özeti</span>
                <?php if ($currentUser['role'] === 'manager'): ?>
                    <span class="tag tag-manager">Yönetici</span>
                <?php endif; ?>
            </div>
            <div class="household-meta-list">
                <div class="meta-row">
                    <span class="meta-label">Konum</span>
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
                    <span class="meta-label"><?= $isDetachedHome ? 'Hane / Kişi' : 'Daire / Kişi' ?></span>
                    <span class="meta-val"><?= e($currentUser['unit_label']) ?> (<?= (int) $currentUser['resident_count'] ?> kişi)</span>
                </div>
            </div>

            <?php if (!empty($currentUser['apartment_invite_code'])): ?>
                <div class="invite-mini-box">
                    <span class="invite-mini-label">Apartman Davet Kodu</span>
                    <div class="invite-code-row">
                        <code id="sidebarInviteCode"><?= e($currentUser['apartment_invite_code']) ?></code>
                        <button type="button" class="btn-copy" data-copy="<?= e($currentUser['apartment_invite_code']) ?>">Kopyala</button>
                    </div>
                </div>
            <?php elseif (!$isDetachedHome): ?>
                <div class="invite-mini-box">
                    <span class="invite-mini-label">Bireysel Daire Katılımcısı</span>
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
        <span>Zaman Tüneli</span>
    </a>
</nav>
