<?php
/**
 * DEBI - Arkadaşlarım, Gelen İstekler ve Kişi Keşfet Sayfası
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
    // Yalnızca yerel sayfa yönlendirmesine izin ver
    if (!preg_match('/^(friends\.php|profile\.php|index\.php)(\?[a-zA-Z0-9_=&-]*)?$/', $returnUrl)) {
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
$incomingRequests = get_incoming_friend_requests((int) $currentUser['id']);
$myFriends        = get_user_friends((int) $currentUser['id']);
$discoverUsers    = search_users_for_friendship((int) $currentUser['id'], $searchQuery, 20);

$pageTitle = 'Arkadaşlar — Debi';
$activeNav = 'friends';
require_once __DIR__ . '/includes/header.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/includes/sidebar_left.php'; ?>

    <main class="main-column">
        <!-- Gelen Arkadaşlık İstekleri -->
        <?php if (!empty($incomingRequests)): ?>
            <section class="panel-card">
                <div class="panel-card-header">
                    <h2 class="section-title">Gelen Arkadaşlık İstekleri (<?= count($incomingRequests) ?>)</h2>
                </div>

                <div class="user-cards-list">
                    <?php foreach ($incomingRequests as $req): ?>
                        <div class="user-row-card">
                            <a href="profile.php?id=<?= (int) $req['user_id'] ?>" class="user-row-main">
                                <span class="avatar-circle" style="background-color: <?= e($req['avatar_color']) ?>;">
                                    <?= e(user_initials($req['full_name'])) ?>
                                </span>
                                <div class="user-row-info">
                                    <strong><?= e($req['full_name']) ?></strong>
                                    <span>
                                        <?= e($req['district'] . ' / ' . $req['city']) ?>
                                        <?= !empty($req['apartment_name']) ? ' · ' . e($req['apartment_name']) : ' · ' . e(housing_type_label($req['housing_type'])) ?>
                                    </span>
                                </div>
                            </a>

                            <div class="user-row-actions">
                                <form method="post" action="friends.php">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="accept_request">
                                    <input type="hidden" name="target_user_id" value="<?= (int) $req['user_id'] ?>">
                                    <button type="submit" class="btn btn-primary btn-sm">Kabul Et</button>
                                </form>

                                <form method="post" action="friends.php">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="remove_friend">
                                    <input type="hidden" name="target_user_id" value="<?= (int) $req['user_id'] ?>">
                                    <button type="submit" class="btn btn-outline btn-sm">Reddet</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- Arkadaşlarım Listesi -->
        <section class="panel-card">
            <div class="panel-card-header">
                <h1 class="section-title">Arkadaşlarım (<?= count($myFriends) ?>)</h1>
                <a href="index.php?scope=friends" class="link-subtle">Arkadaş Akışına Git &rarr;</a>
            </div>

            <?php if (empty($myFriends)): ?>
                <div class="empty-inline">
                    Henüz ekli arkadaşınız bulunmuyor. Aşağıdaki listeden kullanıcıları arayıp arkadaş ekleyebilirsiniz.
                </div>
            <?php else: ?>
                <div class="user-cards-list">
                    <?php foreach ($myFriends as $fr): ?>
                        <div class="user-row-card">
                            <a href="profile.php?id=<?= (int) $fr['id'] ?>" class="user-row-main">
                                <span class="avatar-circle" style="background-color: <?= e($fr['avatar_color']) ?>;">
                                    <?= e(user_initials($fr['full_name'])) ?>
                                </span>
                                <div class="user-row-info">
                                    <strong><?= e($fr['full_name']) ?></strong>
                                    <span>
                                        <?= e($fr['district'] . ' / ' . $fr['city']) ?>
                                        <?= !empty($fr['apartment_name']) ? ' · ' . e($fr['apartment_name']) : ' · ' . e(housing_type_label($fr['housing_type'])) ?>
                                    </span>
                                </div>
                            </a>

                            <div class="user-row-actions">
                                <?php if ($fr['daily_liters_per_person'] !== null): ?>
                                    <span class="metric-pill <?= (float) $fr['daily_liters_per_person'] <= 110 ? 'pill-good' : 'pill-neutral' ?>">
                                        <?= number_format((float) $fr['daily_liters_per_person'], 1, ',', '.') ?> L / gün
                                    </span>
                                <?php endif; ?>
                                <a href="profile.php?id=<?= (int) $fr['id'] ?>" class="btn btn-outline btn-sm">Zaman Tüneli</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Kişi Ara & Arkadaş Ekle -->
        <section class="panel-card">
            <div class="panel-card-header">
                <h2 class="section-title">Kişi Ara &amp; Arkadaş Ekle</h2>
            </div>

            <form method="get" action="friends.php" class="inline-action-form" style="margin-bottom: 14px;">
                <input type="text" name="q" value="<?= e($searchQuery) ?>" placeholder="İsim, apartman veya ilçe ara...">
                <button type="submit" class="btn btn-outline">Ara</button>
                <?php if ($searchQuery !== ''): ?>
                    <a href="friends.php" class="btn btn-outline">Temizle</a>
                <?php endif; ?>
            </form>

            <?php if (empty($discoverUsers)): ?>
                <div class="empty-inline">Aramanızla eşleşen kullanıcı bulunamadı.</div>
            <?php else: ?>
                <div class="user-cards-list">
                    <?php foreach ($discoverUsers as $u): ?>
                        <?php $relState = $u['relation']['state'] ?? 'none'; ?>
                        <div class="user-row-card">
                            <a href="profile.php?id=<?= (int) $u['id'] ?>" class="user-row-main">
                                <span class="avatar-circle" style="background-color: <?= e($u['avatar_color']) ?>;">
                                    <?= e(user_initials($u['full_name'])) ?>
                                </span>
                                <div class="user-row-info">
                                    <strong><?= e($u['full_name']) ?></strong>
                                    <span>
                                        <?= e($u['district'] . ' / ' . $u['city']) ?>
                                        <?= !empty($u['apartment_name']) ? ' · ' . e($u['apartment_name']) : ' · ' . e(housing_type_label($u['housing_type'])) ?>
                                    </span>
                                </div>
                            </a>

                            <div class="user-row-actions">
                                <?php if ($relState === 'accepted'): ?>
                                    <a href="profile.php?id=<?= (int) $u['id'] ?>" class="btn btn-outline btn-sm">✓ Arkadaşsınız</a>
                                <?php elseif ($relState === 'pending_sent'): ?>
                                    <form method="post" action="friends.php">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="remove_friend">
                                        <input type="hidden" name="target_user_id" value="<?= (int) $u['id'] ?>">
                                        <button type="submit" class="btn btn-outline btn-sm">İstek Gönderildi (İptal)</button>
                                    </form>
                                <?php elseif ($relState === 'pending_received'): ?>
                                    <form method="post" action="friends.php">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="accept_request">
                                        <input type="hidden" name="target_user_id" value="<?= (int) $u['id'] ?>">
                                        <button type="submit" class="btn btn-primary btn-sm">İsteği Kabul Et</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="friends.php">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="send_request">
                                        <input type="hidden" name="target_user_id" value="<?= (int) $u['id'] ?>">
                                        <button type="submit" class="btn btn-primary btn-sm">+ Arkadaş Ekle</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php require_once __DIR__ . '/includes/sidebar_right.php'; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
