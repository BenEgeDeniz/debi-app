<?php
/**
 * DEBI - Ana Sosyal Medya Akışı (Arkadaşlar / Apartman / Genel Debi)
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/modules/feed/feed_service.php';

$currentUser    = require_login();
$isDetachedHome = ($currentUser['housing_type'] ?? '') === 'detached_house' && empty($currentUser['apartment_id']);

// POST İşlemleri (Gönderi, Damla Tepkisi, Yorum, Silme)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('error', 'Güvenlik doğrulaması başarısız oldu.');
        redirect('feed.php');
    }

    $action    = $_POST['action'] ?? '';
    $returnTab = $_POST['return_scope'] ?? 'friends';
    if (!in_array($returnTab, ['friends', 'apartment', 'public'], true)) {
        $returnTab = 'friends';
    }

    if ($action === 'create_post') {
        $file = $_FILES['post_image'] ?? null;
        $res  = create_post($currentUser, $_POST, $file);
        if ($res['ok']) {
            set_flash('success', 'Paylaşımınız akışa eklendi.');
            $vis = $_POST['visibility'] ?? 'friends';
            if (in_array($vis, ['friends', 'apartment', 'public'], true)) {
                $returnTab = $vis;
            }
        } else {
            set_flash('error', $res['error']);
        }
        redirect('feed.php?scope=' . urlencode($returnTab));
    }

    if ($action === 'toggle_reaction') {
        $postId = (int) ($_POST['post_id'] ?? 0);
        if ($postId > 0) {
            $res = toggle_post_reaction($postId, (int) $currentUser['id']);
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($res);
                exit;
            }
        }
        redirect('feed.php?scope=' . urlencode($returnTab) . '#post-' . $postId);
    }

    if ($action === 'add_comment') {
        $postId  = (int) ($_POST['post_id'] ?? 0);
        $content = $_POST['comment_content'] ?? '';
        if ($postId > 0) {
            $res = add_post_comment($currentUser, $postId, $content, (array) ($_POST['tagged_user_ids'] ?? []), (int) ($_POST['parent_comment_id'] ?? 0));
            if (!$res['ok']) {
                set_flash('error', $res['error']);
            }
        }
        redirect('feed.php?scope=' . urlencode($returnTab) . '#post-' . $postId);
    }

    if ($action === 'delete_post') {
        $postId = (int) ($_POST['post_id'] ?? 0);
        if ($postId > 0 && delete_post($postId, $currentUser)) {
            set_flash('success', 'Paylaşım silindi.');
        }
        redirect('feed.php?scope=' . urlencode($returnTab));
    }
}

$scope = $_GET['scope'] ?? 'friends';
if (!in_array($scope, ['friends', 'apartment', 'public'], true)) {
    $scope = 'friends';
}
$taggableUsers = get_taggable_users($currentUser, $scope);
$taggableByVisibility = [];
foreach (['friends', 'apartment', 'public'] as $tagScope) {
    $taggableByVisibility[$tagScope] = get_taggable_users($currentUser, $tagScope);
}
// Müstakil ev kullanıcılarının apartman akışı yoktur; yalnızca Arkadaşlar ve Genel Debi vardır
if ($isDetachedHome && $scope === 'apartment') {
    $scope = 'friends';
}

$posts = get_feed_posts($currentUser, $scope, null);

$pageTitle = 'Sosyal Akış — Debi';
$activeNav = 'feed';
require_once __DIR__ . '/includes/header.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/includes/sidebar_left.php'; ?>

    <main class="main-column">
        <!-- Gönderi Oluşturma Kutusu -->
        <section class="panel-card composer-card">
            <div class="composer-header">
                <a href="profile.php">
                    <?= render_avatar($currentUser) ?>
                </a>
                <div class="composer-author">
                    <a href="profile.php" class="author-link">
                        <strong><?= e($currentUser['full_name']) ?></strong>
                    </a>
                    <span>
                        <?php if (!empty($currentUser['apartment_id'])): ?>
                            <?= e($currentUser['apartment_name']) ?> &middot; <?= e(format_unit_label($currentUser['unit_label'])) ?>
                        <?php else: ?>
                            <?= e(format_unit_label($currentUser['unit_label'], $currentUser['housing_type'])) ?>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <form method="post" action="feed.php" class="composer-form" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_post">
                <input type="hidden" name="return_scope" value="<?= e($scope) ?>">
                <input type="hidden" name="category" value="tip">

                <div class="mention-field" data-mention-users-by-scope="<?= e(json_encode($taggableByVisibility, JSON_UNESCAPED_UNICODE)) ?>">
                    <textarea name="content" rows="2" required
                              placeholder="Neler oluyor? Tasarruf ipucunu veya faturanı paylaş..."></textarea>
                    <div class="mention-suggestions" hidden></div>
                </div>

                <div class="composer-bar-clean">
                    <div class="composer-bar-left">
                        <select name="visibility" class="composer-scope-select" aria-label="Görünürlük">
                            <option value="friends" <?= $scope === 'friends' ? 'selected' : '' ?>>
                                Arkadaşlar
                            </option>
                            <?php if (!empty($currentUser['apartment_id'])): ?>
                                <option value="apartment" <?= $scope === 'apartment' ? 'selected' : '' ?>>
                                    <?= e($currentUser['apartment_name']) ?>
                                </option>
                            <?php endif; ?>
                            <option value="public" <?= $scope === 'public' ? 'selected' : '' ?>>
                                Genel
                            </option>
                        </select>

                        <label class="file-attach-label">
                            <span>📷 Fotoğraf</span>
                            <input type="file" name="post_image" accept="image/jpeg,image/png,image/webp">
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm">Paylaş</button>
                </div>
            </form>
        </section>

        <!-- Akış Sekmeleri (Arkadaşlar / Apartmanım / Genel Debi) -->
        <div class="feed-tabs-bar">
            <div class="feed-tabs <?= $isDetachedHome ? 'feed-tabs-2' : '' ?>">
                <a href="feed.php?scope=friends" class="feed-tab <?= $scope === 'friends' ? 'active' : '' ?>">
                    <span>Arkadaşlar</span>
                </a>
                <?php if (!$isDetachedHome): ?>
                    <a href="feed.php?scope=apartment" class="feed-tab <?= $scope === 'apartment' ? 'active' : '' ?>">
                        <span><?= !empty($currentUser['apartment_name']) ? e($currentUser['apartment_name']) : 'Apartman' ?></span>
                    </a>
                <?php endif; ?>
                <a href="feed.php?scope=public" class="feed-tab <?= $scope === 'public' ? 'active' : '' ?>">
                    <span>Genel</span>
                </a>
            </div>
        </div>

        <?php if ($scope === 'apartment' && empty($currentUser['apartment_id'])): ?>
            <div class="panel-card empty-state-card">
                <h3>Apartmana bağlı değilsiniz</h3>
                <div class="action-row" style="margin-top: 10px;">
                    <a href="apartment.php" class="btn btn-primary btn-sm">Apartmana Katıl / Kur</a>
                </div>
            </div>
        <?php else: ?>
            <?php if (empty($posts)): ?>
                <div class="panel-card empty-state-card">
                    <?php if ($scope === 'friends'): ?>
                        <h3>Henüz paylaşım yok</h3>
                        <div class="action-row" style="margin-top: 10px;">
                            <a href="friends.php" class="btn btn-primary btn-sm">+ Arkadaş Ekle</a>
                            <a href="feed.php?scope=public" class="btn btn-outline btn-sm">Genel Akış</a>
                        </div>
                    <?php else: ?>
                        <h3>Henüz paylaşım yok</h3>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="post-list">
                    <?php foreach ($posts as $post): ?>
                        <article class="panel-card post-card post-clickable <?= $post['is_pinned'] ? 'post-pinned' : '' ?>" data-post-url="post.php?id=<?= (int) $post['id'] ?>" id="post-<?= (int) $post['id'] ?>">
                            <div class="post-header">
                                <div class="post-author-group">
                                    <a href="profile.php?id=<?= (int) $post['user_id'] ?>">
                                        <?= render_avatar($post) ?>
                                    </a>
                                    <div>
                                        <div class="post-author-line">
                                            <a href="profile.php?id=<?= (int) $post['user_id'] ?>" class="author-link">
                                                <strong><?= e($post['full_name']) ?> · @<?= e($post['nickname'] ?? '') ?></strong>
                                            </a>
                                        </div>
                                        <div class="post-location-line">
                                            <?php if (!empty($post['post_apartment'])): ?>
                                                <strong><?= e($post['post_apartment']) ?></strong> &middot; <?= e(format_unit_label($post['unit_label'])) ?>
                                            <?php else: ?>
                                                <span><?= e(format_unit_label($post['unit_label'], $post['housing_type'])) ?></span>
                                            <?php endif; ?>
                                            · <time><?= e(time_ago($post['created_at'])) ?></time>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="post-body">
                                <a class="post-permalink" href="post.php?id=<?= (int) $post['id'] ?>">Paylaşımı aç</a>
                                <p><?= render_mention_content($post['content']) ?></p>

                                <?php if (!empty($post['image_path'])): ?>
                                    <div class="post-media-frame">
                                        <img src="<?= e($post['image_path']) ?>" alt="Paylaşım görseli" loading="lazy">
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="post-footer">
                                <form method="post" action="feed.php" class="reaction-form" data-post-id="<?= (int) $post['id'] ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_reaction">
                                    <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
                                    <input type="hidden" name="return_scope" value="<?= e($scope) ?>">
                                    <button type="submit" class="btn-action <?= (int) $post['user_reacted'] > 0 ? 'reacted' : '' ?>">
                                        <span class="drop-symbol">💧</span>
                                        <span class="reaction-count"><?= (int) $post['reaction_count'] ?></span>
                                    </button>
                                </form>

                                <a class="btn-action" href="post.php?id=<?= (int) $post['id'] ?>">
                                    💬 <?= (int) $post['comment_count'] ?>
                                </a>

                                <?php if ((int) $post['user_id'] === (int) $currentUser['id'] || ($currentUser['role'] === 'manager' && !empty($currentUser['apartment_id']) && (int) $post['apartment_id'] === (int) $currentUser['apartment_id'])): ?>
                                    <form method="post" action="feed.php" class="delete-post-form" onsubmit="return confirm('Bu paylaşımı silmek istediğinize emin misiniz?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_post">
                                        <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
                                        <input type="hidden" name="return_scope" value="<?= e($scope) ?>">
                                        <button type="submit" class="btn-action btn-action-danger">Sil</button>
                                    </form>
                                <?php endif; ?>
                            </div>

                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <?php require_once __DIR__ . '/includes/sidebar_right.php'; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
