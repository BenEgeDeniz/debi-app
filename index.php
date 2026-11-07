<?php
/**
 * DEBI - Ana Sosyal Medya Akışı (Mahallem / Apartmanım / Genel)
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/modules/feed/feed_service.php';

$currentUser = require_login();

// POST İşlemleri (Gönderi, Damla Tepkisi, Yorum, Silme)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('error', 'Güvenlik doğrulaması başarısız oldu.');
        redirect('index.php');
    }

    $action    = $_POST['action'] ?? '';
    $returnTab = $_POST['return_scope'] ?? 'neighborhood';
    if (!in_array($returnTab, ['neighborhood', 'apartment', 'public'], true)) {
        $returnTab = 'neighborhood';
    }

    if ($action === 'create_post') {
        $file = $_FILES['post_image'] ?? null;
        $res  = create_post($currentUser, $_POST, $file);
        if ($res['ok']) {
            set_flash('success', 'Paylaşımınız akışa eklendi.');
            $vis = $_POST['visibility'] ?? 'neighborhood';
            if (in_array($vis, ['neighborhood', 'apartment', 'public'], true)) {
                $returnTab = $vis;
            }
        } else {
            set_flash('error', $res['error']);
        }
        redirect('index.php?scope=' . urlencode($returnTab));
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
        redirect('index.php?scope=' . urlencode($returnTab) . '#post-' . $postId);
    }

    if ($action === 'add_comment') {
        $postId  = (int) ($_POST['post_id'] ?? 0);
        $content = $_POST['comment_content'] ?? '';
        if ($postId > 0) {
            $res = add_post_comment($postId, (int) $currentUser['id'], $content);
            if (!$res['ok']) {
                set_flash('error', $res['error']);
            }
        }
        redirect('index.php?scope=' . urlencode($returnTab) . '#post-' . $postId);
    }

    if ($action === 'delete_post') {
        $postId = (int) ($_POST['post_id'] ?? 0);
        if ($postId > 0 && delete_post($postId, $currentUser)) {
            set_flash('success', 'Paylaşım silindi.');
        }
        redirect('index.php?scope=' . urlencode($returnTab));
    }
}

$scope = $_GET['scope'] ?? 'neighborhood';
if (!in_array($scope, ['neighborhood', 'apartment', 'public'], true)) {
    $scope = 'neighborhood';
}

$posts = get_feed_posts($currentUser, $scope, null);

$pageTitle = 'Sosyal Akış — Debi';
$activeNav = 'feed';
require_once __DIR__ . '/includes/header.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/includes/sidebar_left.php'; ?>

    <main class="main-column">
        <!-- Sadeleştirilmiş Gönderi Oluşturma Kutusu -->
        <section class="panel-card composer-card">
            <div class="composer-header">
                <span class="avatar-circle" style="background-color: <?= e($currentUser['avatar_color']) ?>;">
                    <?= e(user_initials($currentUser['full_name'])) ?>
                </span>
                <div class="composer-author">
                    <strong><?= e($currentUser['full_name']) ?></strong>
                    <span>
                        <?= e($currentUser['neighborhood_name']) ?>
                        <?php if (!empty($currentUser['apartment_id'])): ?>
                            · <?= e($currentUser['apartment_name']) ?> (<?= e($currentUser['unit_label']) ?>)
                        <?php else: ?>
                            · <?= e(housing_type_label($currentUser['housing_type'])) ?>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <form method="post" action="index.php" class="composer-form" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_post">
                <input type="hidden" name="return_scope" value="<?= e($scope) ?>">
                <input type="hidden" name="category" value="tip">

                <textarea name="content" rows="3" required
                          placeholder="Komşularınızla su tasarrufu deneyiminizi, fatura değişiminizi veya bir sorunuzu paylaşın..."></textarea>

                <div class="composer-bar-clean">
                    <div class="composer-bar-left">
                        <select name="visibility" class="composer-scope-select" aria-label="Görünürlük">
                            <option value="neighborhood" <?= $scope === 'neighborhood' ? 'selected' : '' ?>>
                                Mahallem (<?= e($currentUser['neighborhood_name']) ?>)
                            </option>
                            <?php if (!empty($currentUser['apartment_id'])): ?>
                                <option value="apartment" <?= $scope === 'apartment' ? 'selected' : '' ?>>
                                    Apartmanım (<?= e($currentUser['apartment_name']) ?>)
                                </option>
                            <?php endif; ?>
                            <option value="public" <?= $scope === 'public' ? 'selected' : '' ?>>
                                Tüm Debi
                            </option>
                        </select>

                        <label class="file-attach-label">
                            <span>Fotoğraf Ekle</span>
                            <input type="file" name="post_image" accept="image/jpeg,image/png,image/webp">
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm">Paylaş</button>
                </div>
            </form>
        </section>

        <!-- Akış Sekmeleri -->
        <div class="feed-tabs-bar">
            <div class="feed-tabs">
                <a href="index.php?scope=neighborhood" class="feed-tab <?= $scope === 'neighborhood' ? 'active' : '' ?>">
                    <span>Mahallem</span>
                    <small><?= e($currentUser['neighborhood_name']) ?></small>
                </a>
                <a href="index.php?scope=apartment" class="feed-tab <?= $scope === 'apartment' ? 'active' : '' ?>">
                    <span>Apartmanım</span>
                    <small><?= !empty($currentUser['apartment_name']) ? e($currentUser['apartment_name']) : 'Bireysel' ?></small>
                </a>
                <a href="index.php?scope=public" class="feed-tab <?= $scope === 'public' ? 'active' : '' ?>">
                    <span>Genel Akış</span>
                    <small>Tüm Debi</small>
                </a>
            </div>
        </div>

        <?php if ($scope === 'apartment' && empty($currentUser['apartment_id'])): ?>
            <div class="panel-card empty-state-card">
                <h3>Şu anda mahallenizde bireysel katılımcı olarak yer alıyorsunuz</h3>
                <p>
                    <strong><?= e($currentUser['neighborhood_name']) ?></strong> mahallesinde bireysel veya müstakil hane olarak mahalle akışına ve sıralamasına dahilsiniz.
                    Bir apartmanda oturuyorsanız davet kodunuzla apartmanınıza bağlanabilir veya apartmanınızı oluşturabilirsiniz.
                </p>
                <div class="action-row">
                    <a href="apartment.php" class="btn btn-primary">Apartmana Katıl veya Oluştur</a>
                    <a href="index.php?scope=neighborhood" class="btn btn-outline">Mahalle Akışına Dön</a>
                </div>
            </div>
        <?php else: ?>
            <?php if (empty($posts)): ?>
                <div class="panel-card empty-state-card">
                    <h3>Bu görünümde henüz paylaşım yok</h3>
                    <p>İlk su tasarrufu notunu veya fatura değişimini yukarıdaki kutudan paylaşabilirsiniz.</p>
                </div>
            <?php else: ?>
                <div class="post-list">
                    <?php foreach ($posts as $post): ?>
                        <article class="panel-card post-card <?= $post['is_pinned'] ? 'post-pinned' : '' ?>" id="post-<?= (int) $post['id'] ?>">
                            <div class="post-header">
                                <div class="post-author-group">
                                    <span class="avatar-circle" style="background-color: <?= e($post['avatar_color']) ?>;">
                                        <?= e(user_initials($post['full_name'])) ?>
                                    </span>
                                    <div>
                                        <div class="post-author-line">
                                            <strong><?= e($post['full_name']) ?></strong>
                                            <?php if ($post['user_role'] === 'manager'): ?>
                                                <span class="tag tag-manager">Yönetici</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="post-location-line">
                                            <?= e($post['post_neighborhood'] . ', ' . $post['post_district']) ?>
                                            <?php if (!empty($post['post_apartment'])): ?>
                                                · <strong><?= e($post['post_apartment']) ?></strong> (<?= e($post['unit_label']) ?>)
                                            <?php else: ?>
                                                · <span><?= e(housing_type_label($post['housing_type'])) ?> (<?= e($post['unit_label']) ?>)</span>
                                            <?php endif; ?>
                                            · <time><?= e(time_ago($post['created_at'])) ?></time>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="post-body">
                                <p><?= nl2br(e($post['content'])) ?></p>

                                <?php if (!empty($post['image_path'])): ?>
                                    <div class="post-media-frame">
                                        <img src="<?= e($post['image_path']) ?>" alt="Paylaşım görseli" loading="lazy">
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="post-footer">
                                <form method="post" action="index.php" class="reaction-form" data-post-id="<?= (int) $post['id'] ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_reaction">
                                    <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
                                    <input type="hidden" name="return_scope" value="<?= e($scope) ?>">
                                    <button type="submit" class="btn-action <?= (int) $post['user_reacted'] > 0 ? 'reacted' : '' ?>">
                                        <span class="drop-symbol">💧</span>
                                        <span class="reaction-label">Damla Desteği</span>
                                        <span class="reaction-count">(<?= (int) $post['reaction_count'] ?>)</span>
                                    </button>
                                </form>

                                <button type="button" class="btn-action js-toggle-comments" data-target="comments-<?= (int) $post['id'] ?>">
                                    Yorumlar (<?= (int) $post['comment_count'] ?>)
                                </button>

                                <?php if ((int) $post['user_id'] === (int) $currentUser['id'] || ($currentUser['role'] === 'manager' && !empty($currentUser['apartment_id']) && (int) $post['apartment_id'] === (int) $currentUser['apartment_id'])): ?>
                                    <form method="post" action="index.php" class="delete-post-form" onsubmit="return confirm('Bu paylaşımı silmek istediğinize emin misiniz?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_post">
                                        <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
                                        <input type="hidden" name="return_scope" value="<?= e($scope) ?>">
                                        <button type="submit" class="btn-action btn-action-danger">Sil</button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <!-- Yorumlar Bölümü -->
                            <div class="comments-box" id="comments-<?= (int) $post['id'] ?>">
                                <?php if (!empty($post['comments'])): ?>
                                    <ul class="comment-list">
                                        <?php foreach ($post['comments'] as $comment): ?>
                                            <li class="comment-item">
                                                <span class="avatar-circle avatar-xs" style="background-color: <?= e($comment['avatar_color']) ?>;">
                                                    <?= e(user_initials($comment['full_name'])) ?>
                                                </span>
                                                <div class="comment-content">
                                                    <div class="comment-meta">
                                                        <strong><?= e($comment['full_name']) ?></strong>
                                                        <span>
                                                            <?= !empty($comment['apartment_name']) ? e($comment['apartment_name'] . ' · ' . $comment['unit_label']) : e($comment['unit_label']) ?>
                                                        </span>
                                                        <time><?= e(time_ago($comment['created_at'])) ?></time>
                                                    </div>
                                                    <p><?= nl2br(e($comment['content'])) ?></p>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>

                                <form method="post" action="index.php" class="comment-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="add_comment">
                                    <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
                                    <input type="hidden" name="return_scope" value="<?= e($scope) ?>">
                                    <input type="text" name="comment_content" required placeholder="Yorum yazın...">
                                    <button type="submit" class="btn btn-outline btn-sm">Gönder</button>
                                </form>
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
