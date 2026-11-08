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
$startedEventCnt = 0;
if (!empty($currentUser)) {
    $eventServicePath = __DIR__ . '/../modules/events/event_service.php';
    if (is_file($eventServicePath)) {
        require_once $eventServicePath;
        try {
            $startedEventCnt = count_started_attending_events((int) $currentUser['id']);
        } catch (Throwable $exception) {
            $startedEventCnt = 0;
        }
    }
}
?>
<aside class="sidebar-left">
    <nav class="side-nav" aria-label="Ana Menü">
        <a href="feed.php" class="side-nav-link <?= $activeNav === 'feed' ? 'active' : '' ?>">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <span>Akış</span>
        </a>

        <a href="profile.php" class="side-nav-link <?= $activeNav === 'profile' ? 'active' : '' ?>">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span>Profilim</span>
        </a>

        <a href="friends.php" class="side-nav-link <?= $activeNav === 'friends' ? 'active' : '' ?>">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <span>Arkadaşlar</span>
            <?php if ($pendingFriendCnt > 0): ?>
                <span class="nav-badge-count"><?= $pendingFriendCnt ?></span>
            <?php endif; ?>
        </a>

        <a href="events.php" class="side-nav-link <?= $activeNav === 'events' ? 'active' : '' ?>">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="14" x2="8" y2="14"/><line x1="12" y1="14" x2="12" y2="14"/><line x1="16" y1="14" x2="16" y2="14"/><line x1="8" y1="18" x2="8" y2="18"/><line x1="12" y1="18" x2="12" y2="18"/></svg>
            <span>Etkinlikler</span>
            <?php if ($startedEventCnt > 0): ?><span class="nav-alert-dot" aria-label="Başlayan etkinlik var"></span><?php endif; ?>
        </a>

        <a href="water.php" class="side-nav-link <?= $activeNav === 'water' ? 'active' : '' ?>">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>
            <span>Faturalar</span>
        </a>
        <?php if (!empty($currentUser['is_admin'])): ?>
            <a href="admin_water_reviews.php" class="side-nav-link <?= $activeNav === 'admin_water_reviews' ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4 7v5c0 5 3.5 8 8 9 4.5-1 8-4 8-9V7z"/><path d="m9 12 2 2 4-4"/></svg>
                <span>Fatura incelemeleri</span>
            </a>
        <?php endif; ?>

        <a href="analytics.php" class="side-nav-link <?= $activeNav === 'analytics' ? 'active' : '' ?>">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            <span>Analiz</span>
        </a>

        <a href="store.php" class="side-nav-link <?= $activeNav === 'store' ? 'active' : '' ?>">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
            <span>Mağaza</span>
        </a>
        <a href="leaderboard.php" class="side-nav-link <?= $activeNav === 'leaderboard' ? 'active' : '' ?>">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 21h8"/><path d="M12 17v4"/><path d="M7 4h10v5a5 5 0 0 1-10 0z"/><path d="M7 7H4a3 3 0 0 0 3 3"/><path d="M17 7h3a3 3 0 0 1-3 3"/></svg>
            <span>Sıralama</span>
        </a>

        <?php if (!$isDetachedHome): ?>
            <a href="apartment.php" class="side-nav-link <?= $activeNav === 'apartment' ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/></svg>
                <span>Apartman</span>
            </a>
        <?php endif; ?>
    </nav>
</aside>

<!-- Mobil Alt Sabit Menü -->
<nav class="mobile-bottom-nav <?= $isDetachedHome ? 'mobile-nav-4' : '' ?>" aria-label="Mobil Menü">
    <a href="feed.php" class="mobile-nav-item <?= $activeNav === 'feed' ? 'active' : '' ?>">
        <span>Akış</span>
    </a>
    <a href="friends.php" class="mobile-nav-item <?= $activeNav === 'friends' ? 'active' : '' ?>">
        <span>Arkadaşlar<?= $pendingFriendCnt > 0 ? ' (' . $pendingFriendCnt . ')' : '' ?></span>
    </a>
    <a href="events.php" class="mobile-nav-item <?= $activeNav === 'events' ? 'active' : '' ?>">
        <span>Etkinlikler</span>
    </a>
    <a href="water.php" class="mobile-nav-item <?= $activeNav === 'water' ? 'active' : '' ?>">
        <span>Fatura</span>
    </a>
    <?php if (!empty($currentUser['is_admin'])): ?>
        <a href="admin_water_reviews.php" class="mobile-nav-item <?= $activeNav === 'admin_water_reviews' ? 'active' : '' ?>">
            <span>İnceleme</span>
        </a>
    <?php endif; ?>
    <a href="analytics.php" class="mobile-nav-item <?= $activeNav === 'analytics' ? 'active' : '' ?>">
        <span>Analiz</span>
    </a>
    <a href="store.php" class="mobile-nav-item <?= $activeNav === 'store' ? 'active' : '' ?>">
        <span>Mağaza</span>
    </a>
    <a href="leaderboard.php" class="mobile-nav-item <?= $activeNav === 'leaderboard' ? 'active' : '' ?>">
        <span>Sıralama</span>
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
