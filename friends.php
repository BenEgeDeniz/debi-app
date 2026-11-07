<?php
/**
 * DEBI - Arkadaşlarım ( Kompakt 2 Sütunlu Sayfalı Liste & Kişi Arama )
 */
require_once __DIR__ . '/includes/init.php';

$currentUser = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('error', 'Güvenlik doğrulaması başarısız oldu.');
        redirect('friends.php');
    }

    $action       = $_POST['action'] ?? '';
    $targetUserId = (int) ($_POST['target_user_id'] ?? 0);
    $returnUrl    = $_POST['return_url'] ?? 'friends.php';
    if (!preg_match('/^(friends\.php|profile\.php|index\.php)(\?[a-zA-Z0-9_=&%-]*)?$/', $returnUrl)) {
        $returnUrl = 'friends.php';
    }

    if ($action === 'send_request' && $targetUserId > 0) {
        $res = send_friend_request((int) $currentUser['id'], $targetUserId);
        set_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? $res['message'] : $res['error']);
        redirect($returnUrl);
    }

    if ($action === 'accept_request' && $targetUserId > 0) {
        $res = accept_friend_request((int) $currentUser['id'], $targetUserId);
        set_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? $res['message'] : $res['error']);
        redirect($returnUrl);
    }

    if ($action === 'remove_friend' && $targetUserId > 0) {
        $res = remove_friendship((int) $currentUser['id'], $targetUserId);
        set_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? $res['message'] : $res['error']);
        redirect($returnUrl);
    }
}

$searchQuery      = trim($_GET['q'] ?? '');
$perPage          = 12;
$page             = max(1, (int) ($_GET['page'] ?? 1));
$totalFriends     = count_user_friends((int) $currentUser['id']);
$totalPages       = max(1, (int) ceil($totalFriends / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset           = ($page - 1) * $perPage;

$incomingRequests = get_incoming_friend_requests((int) $currentUser['id']);
$myFriends        = get_user_friends((int) $currentUser['id'], $perPage, $offset);
$searchResults    = $searchQuery !== '' ? search_users_for_friendship((int) $currentUser['id'], $searchQuery, 8) : [];

$pageTitle = 'Arkadaşlar — Debi';
$activeNav = 'friends';
require_once __DIR__ . '/includes/header.php';
?>

<div class="app-shell app-shell-2col">
    <?php require_once __DIR__ . '/includes/sidebar_left.php'; ?>

    <main class="main-column">
        <!-- 1. Kişi Arama Çubuğu -->
        <section class="panel-card">
            <form method="get" action="friends.php" class="inline-action-form">
                <input type="text" name="q" value="<?= e($searchQuery) ?>" placeholder="Kişi veya apartman ara...">
                <button type="submit" class="btn btn-primary btn-sm">Ara</button>
                <?php if ($searchQuery !== ''): ?>
                    <a href="friends.php" class="btn btn-outline btn-sm">Temizle</a>
                <?php endif; ?>
            </form>

            <?php if ($searchQuery !== ''): ?>
                <div class="search-results-wrap">
                    <?php if (empty($searchResults)): ?>
                        <div class="empty-inline" style="margin-top: 12px; margin-bottom: 0;">
                            Sonuç bulunamadı.
                        </div>
                    <?php else: ?>
                        <div class="friends-compact-grid" style="margin-top: 12px;">
                            <?php foreach ($searchResults as $u): ?>
                                <?php $relState = $u['relation']['state'] ?? 'none'; ?>
                                <div class="friend-compact-card">
                                    <a href="profile.php?id=<?= (int) $u['id'] ?>" class="friend-compact-user">
                                        <?= render_avatar($u) ?>
                                        <div class="friend-compact-meta">
                                            <strong><?= e($u['full_name']) ?></strong>
                                            <span>
                                                <?= !empty($u['apartment_name']) ? e($u['apartment_name']) : e($u['district']) ?>
                                            </span>
                                        </div>
                                    </a>

                                    <?php if ($relState === 'accepted'): ?>
                                        <span class="tag">Arkadaş</span>
                                    <?php elseif ($relState === 'pending_sent'): ?>
                                        <form method="post" action="friends.php">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="remove_friend">
                                            <input type="hidden" name="target_user_id" value="<?= (int) $u['id'] ?>">
                                            <input type="hidden" name="return_url" value="friends.php?q=<?= urlencode($searchQuery) ?>">
                                            <button type="submit" class="btn btn-outline btn-sm">İptal</button>
                                        </form>
                                    <?php elseif ($relState === 'pending_received'): ?>
                                        <form method="post" action="friends.php">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="accept_request">
                                            <input type="hidden" name="target_user_id" value="<?= (int) $u['id'] ?>">
                                            <button type="submit" class="btn btn-primary btn-sm">Kabul Et</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" action="friends.php">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="send_request">
                                            <input type="hidden" name="target_user_id" value="<?= (int) $u['id'] ?>">
                                            <input type="hidden" name="return_url" value="friends.php?q=<?= urlencode($searchQuery) ?>">
                                            <button type="submit" class="btn btn-primary btn-sm">+ Ekle</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- 2. Gelen Arkadaşlık İstekleri (Varsa) -->
        <?php if (!empty($incomingRequests)): ?>
            <section class="panel-card">
                <div class="panel-card-header">
                    <h2 class="section-title">Gelen İstekler (<?= count($incomingRequests) ?>)</h2>
                </div>

                <div class="friends-compact-grid">
                    <?php foreach ($incomingRequests as $req): ?>
                        <div class="friend-compact-card">
                            <a href="profile.php?id=<?= (int) $req['user_id'] ?>" class="friend-compact-user">
                                <?= render_avatar($req) ?>
                                <div class="friend-compact-meta">
                                    <strong><?= e($req['full_name']) ?></strong>
                                    <span><?= e($req['district'] . ' / ' . $req['city']) ?></span>
                                </div>
                            </a>

                            <div class="user-row-actions">
                                <form method="post" action="friends.php">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="accept_request">
                                    <input type="hidden" name="target_user_id" value="<?= (int) $req['user_id'] ?>">
                                    <button type="submit" class="btn btn-primary btn-sm">Onayla</button>
                                </form>
                                <form method="post" action="friends.php">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="remove_friend">
                                    <input type="hidden" name="target_user_id" value="<?= (int) $req['user_id'] ?>">
                                    <button type="submit" class="btn btn-outline btn-sm">Sil</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- 3. Arkadaşlarım (Kompakt 2 Sütunlu Grid + Sayfalama) -->
        <section class="panel-card">
            <div class="panel-card-header">
                <h1 class="section-title">Arkadaşlarım (<?= $totalFriends ?>)</h1>
            </div>

            <?php if (empty($myFriends)): ?>
                <div class="empty-inline">
                    Henüz arkadaşınız yok. Yukarıdan arkadaş ekleyebilirsiniz.
                </div>
            <?php else: ?>
                <div class="friends-compact-grid">
                    <?php foreach ($myFriends as $fr): ?>
                        <a href="profile.php?id=<?= (int) $fr['id'] ?>" class="friend-compact-card friend-card-link">
                            <div class="friend-compact-user">
                                <?= render_avatar($fr) ?>
                                <div class="friend-compact-meta">
                                    <strong><?= e($fr['full_name']) ?></strong>
                                    <span>
                                        <?= !empty($fr['apartment_name']) ? e($fr['apartment_name']) : e($fr['district']) ?>
                                    </span>
                                </div>
                            </div>
                            <?php if ($fr['daily_liters_per_person'] !== null): ?>
                                <span class="metric-pill <?= (float) $fr['daily_liters_per_person'] <= 110 ? 'pill-good' : 'pill-neutral' ?>">
                                    <?= number_format((float) $fr['daily_liters_per_person'], 0, ',', '.') ?> L
                                </span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="pagination-row">
                        <?php if ($page > 1): ?>
                            <a href="friends.php?page=<?= $page - 1 ?>" class="btn btn-outline btn-sm">&larr; Önceki</a>
                        <?php else: ?>
                            <span></span>
                        <?php endif; ?>

                        <span class="pagination-info">Sayfa <?= $page ?> / <?= $totalPages ?></span>

                        <?php if ($page < $totalPages): ?>
                            <a href="friends.php?page=<?= $page + 1 ?>" class="btn btn-outline btn-sm">Sonraki &rarr;</a>
                        <?php else: ?>
                            <span></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
