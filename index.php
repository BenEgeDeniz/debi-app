<?php
/**
 * DEBI - Ana Sosyal Medya Akışı (Mahallem / Apartmanım / Tüm Debi)
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

    if ($action === 'complete_quest') {
        $questId   = (int) ($_POST['quest_id'] ?? 0);
        $returnUrl = basename($_POST['return_url'] ?? 'index.php');
        if ($questId > 0) {
            $res = complete_daily_quest($currentUser, $questId);
            if ($res['ok']) {
                set_flash('success', $res['emoji'] . ' Harika! "' . $res['title'] . '" görevini tamamladın: +' . $res['points'] . ' DP kazandın ve ~' . $res['liters'] . ' L su kurtardın!');
            } else {
                set_flash('error', $res['error']);
            }
        }
        redirect($returnUrl ?: 'index.php');
    }

    if ($action === 'create_post') {
        $file = $_FILES['post_image'] ?? null;
        $res  = create_post($currentUser, $_POST, $file);
        if ($res['ok']) {
            $ptsMsg = !empty($res['points_awarded']) ? ' (+' . (int) $res['points_awarded'] . ' DP kazandın!)' : '';
            set_flash('success', 'Paylaşımın akışa eklendi!' . $ptsMsg);
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
            if ($res['ok']) {
                if (!empty($res['points_awarded'])) {
                    set_flash('success', 'Yorumun eklendi! 💬 +' . (int) $res['points_awarded'] . ' DP kazandın!');
                }
            } else {
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

$categoryFilter = $_GET['category'] ?? null;
$posts          = get_feed_posts($currentUser, $scope, $categoryFilter);

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

                <textarea name="content" rows="3" required
                          placeholder="Mahalleniz veya apartmanınızla bir su tasarrufu yöntemi, sayaç düşüşü ya da tesisat uyarısı paylaşın..."></textarea>

                <div class="composer-controls">
                    <div class="composer-selects">
                        <label class="inline-select">
                            <span>Konu:</span>
                            <select name="category">
                                <option value="tip">Tasarruf İpucu</option>
                                <option value="achievement">Sayaç / Fatura Başarısı</option>
                                <option value="question">Komşuya Sor</option>
                                <option value="alert">Su Kesintisi / Tesisat Uyarısı</option>
                                <?php if ($currentUser['role'] === 'manager'): ?>
                                    <option value="announcement">Yönetici Duyurusu</option>
                                <?php endif; ?>
                            </select>
                        </label>

                        <label class="inline-select">
                            <span>Hedef Kitle:</span>
                            <select name="visibility">
                                <option value="neighborhood" <?= $scope === 'neighborhood' ? 'selected' : '' ?>>
                                    Mahallem (<?= e($currentUser['neighborhood_name']) ?>)
                                </option>
                                <?php if (!empty($currentUser['apartment_id'])): ?>
                                    <option value="apartment" <?= $scope === 'apartment' ? 'selected' : '' ?>>
                                        Sadece Apartmanım (<?= e($currentUser['apartment_name']) ?>)
                                    </option>
                                <?php endif; ?>
                                <option value="public" <?= $scope === 'public' ? 'selected' : '' ?>>
                                    Tüm Debi (Genel)
                                </option>
                            </select>
                        </label>

                        <label class="inline-select">
                            <span>Tahmini Tasarruf (L):</span>
                            <input type="number" name="water_saved_liters" min="1" max="50000" placeholder="Opsiyonel (Örn: 150)">
                        </label>
                    </div>

                    <div class="composer-image-row">
                        <label class="file-attach-label">
                            <svg class="nav-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75">
                                <rect x="3" y="3" width="14" height="14" rx="2"/>
                                <circle cx="8" cy="8" r="1.5"/>
                                <path d="M17 13l-4.5-4.5L4 17"/>
                            </svg>
                            <span>Sayaç / Tesisat Görseli Ekle</span>
                            <input type="file" name="post_image" accept="image/jpeg,image/png,image/webp">
                        </label>
                    </div>

                    <div class="composer-submit-row">
                        <?php if ($currentUser['role'] === 'manager' && !empty($currentUser['apartment_id'])): ?>
                            <label class="checkbox-inline">
                                <input type="checkbox" name="is_pinned" value="1">
                                <span>Akışın üstüne sabitle</span>
                            </label>
                        <?php else: ?>
                            <span></span>
                        <?php endif; ?>

                        <button type="submit" class="btn btn-primary">Paylaş (+25 DP)</button>
                    </div>
                </div>
            </form>
        </section>

        <!-- Akış Sekmeleri (Mahallem / Apartmanım / Tüm Debi) -->
        <div class="feed-tabs-bar">
            <div class="feed-tabs">
                <a href="index.php?scope=neighborhood" class="feed-tab <?= $scope === 'neighborhood' ? 'active' : '' ?>">
                    <span>🏘️ Mahallem</span>
                    <small><?= e($currentUser['neighborhood_name']) ?></small>
                </a>
                <a href="index.php?scope=apartment" class="feed-tab <?= $scope === 'apartment' ? 'active' : '' ?>">
                    <span>🏢 Apartmanım</span>
                    <small><?= !empty($currentUser['apartment_name']) ? e($currentUser['apartment_name']) : 'Bireysel' ?></small>
                </a>
                <a href="index.php?scope=public" class="feed-tab <?= $scope === 'public' ? 'active' : '' ?>">
                    <span>🌍 Genel Akış</span>
                    <small>Tüm Debi</small>
                </a>
            </div>

            <!-- Kategori Filtresi -->
            <div class="category-filter-row">
                <a href="index.php?scope=<?= e($scope) ?>" class="filter-chip <?= !$categoryFilter ? 'active' : '' ?>">✨ Tümü</a>
                <a href="index.php?scope=<?= e($scope) ?>&amp;category=tip" class="filter-chip <?= $categoryFilter === 'tip' ? 'active' : '' ?>">💡 İpuçları</a>
                <a href="index.php?scope=<?= e($scope) ?>&amp;category=achievement" class="filter-chip <?= $categoryFilter === 'achievement' ? 'active' : '' ?>">🏆 Sayaç Başarıları</a>
                <a href="index.php?scope=<?= e($scope) ?>&amp;category=question" class="filter-chip <?= $categoryFilter === 'question' ? 'active' : '' ?>">❓ Sorular</a>
                <a href="index.php?scope=<?= e($scope) ?>&amp;category=alert" class="filter-chip <?= $categoryFilter === 'alert' ? 'active' : '' ?>">🚨 Uyarılar</a>
            </div>
        </div>

        <!-- Eğer "Apartmanım" sekmesindeyse ve kullanıcı bireysel/müstakil üyeyse bilgi kutusu -->
        <?php if ($scope === 'apartment' && empty($currentUser['apartment_id'])): ?>
            <div class="panel-card empty-state-card">
                <h3>🏡 Şu anda mahallenizde bireysel katılımcı olarak yer alıyorsunuz</h3>
                <p>
                    <strong><?= e($currentUser['neighborhood_name']) ?></strong> mahallesinde bireysel veya müstakil hane olarak tüm mahalle akışına ve sıralamasına dahilsiniz.
                    Eğer bir apartmanda oturuyorsanız davet kodunuzla apartmanınıza bağlanabilir (<strong>+50 DP</strong>) veya apartmanınızı kendiniz oluşturabilirsiniz (<strong>+100 DP</strong>).
                </p>
                <div class="action-row">
                    <a href="apartment.php" class="btn btn-primary">🏢 Apartmana Katıl veya Oluştur (+100 DP)</a>
                    <a href="index.php?scope=neighborhood" class="btn btn-outline">🏘️ Mahalle Akışına Dön</a>
                </div>
            </div>
        <?php else: ?>
            <!-- Gönderi Listesi -->
            <?php if (empty($posts)): ?>
                <div class="panel-card empty-state-card">
                    <h3>🌱 Bu görünümde henüz paylaşım yok</h3>
                    <p>İlk su tasarrufu ipucunu, fatura değişimini veya komşu sorusunu yukarıdaki kutudan paylaşarak <strong>+25 DP</strong> kazanabilirsin!</p>
                </div>
            <?php else: ?>
                <div class="post-list">
                    <?php foreach ($posts as $post): ?>
                        <?php
                        $catMeta    = post_category_meta($post['category']);
                        $authorPts  = (int) ($post['user_points'] ?? 50);
                        $authorLvl  = get_level_info($authorPts);
                        ?>
                        <article class="panel-card post-card <?= $post['is_pinned'] ? 'post-pinned' : '' ?>" id="post-<?= (int) $post['id'] ?>">
                            <?php if ($post['is_pinned']): ?>
                                <div class="pinned-banner">📌 Sabitlenmiş Topluluk Paylaşımı</div>
                            <?php endif; ?>

                            <div class="post-header">
                                <div class="post-author-group">
                                    <span class="avatar-circle" style="background-color: <?= e($post['avatar_color']) ?>;">
                                        <?= e(user_initials($post['full_name'])) ?>
                                    </span>
                                    <div>
                                        <div class="post-author-line">
                                            <strong><?= e($post['full_name']) ?></strong>
                                            <span class="author-lvl-chip" title="Seviye <?= (int) $authorLvl['level'] ?>: <?= e($authorLvl['title']) ?>">
                                                <?= $authorLvl['emoji'] ?> <?= number_format($authorPts, 0, ',', '.') ?> DP
                                            </span>
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

                                <div class="post-badges">
                                    <span class="badge <?= e($catMeta['class']) ?>"><?= e($catMeta['label']) ?></span>
                                    <?php if ($post['visibility'] === 'apartment'): ?>
                                        <span class="badge badge-scope">Apartman İçi</span>
                                    <?php elseif ($post['visibility'] === 'neighborhood'): ?>
                                        <span class="badge badge-scope">Mahalle</span>
                                    <?php else: ?>
                                        <span class="badge badge-scope">Genel</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="post-body">
                                <p><?= nl2br(e($post['content'])) ?></p>

                                <?php if (!empty($post['image_path'])): ?>
                                    <div class="post-media-frame">
                                        <img src="<?= e($post['image_path']) ?>" alt="Paylaşım görseli" loading="lazy">
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($post['water_saved_liters'])): ?>
                                    <div class="saved-water-pill">
                                        Tahmini Su Kazanımı: <strong><?= number_format((int) $post['water_saved_liters'], 0, ',', '.') ?> Litre</strong>
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
                                    💬 Yorumlar (<?= (int) $post['comment_count'] ?>)
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
                                    <input type="text" name="comment_content" required placeholder="Komşunuza yanıt yazın...">
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

