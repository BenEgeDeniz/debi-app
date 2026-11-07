<?php
/**
 * DEBI - Kullanıcı Profili & Zaman Tüneli (Timeline)
 * profile.php -> Giriş yapan kullanıcının kendi zaman tüneli
 * profile.php?id=X -> X ID'li kullanıcının zaman tüneli
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/modules/profile/profile_service.php';
require_once __DIR__ . '/modules/feed/feed_service.php';

$currentUser = require_login();

$requestedId = isset($_GET['id']) ? (int) $_GET['id'] : (int) $currentUser['id'];
if ($requestedId <= 0) {
    $requestedId = (int) $currentUser['id'];
}

$profileUser = get_user_public_profile($requestedId);
if (!$profileUser) {
    set_flash('error', 'Aradığınız kullanıcı profili bulunamadı.');
    redirect('index.php');
}

$isOwnProfile = ((int) $profileUser['id'] === (int) $currentUser['id']);
$showEditForm = $isOwnProfile && !empty($_GET['edit']);

// POST İşlemleri (Profil güncelleme, Zaman Tüneli paylaşımı, Damla Tepkisi, Yorum)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('error', 'Güvenlik doğrulaması başarısız oldu.');
        redirect('profile.php?id=' . (int) $profileUser['id']);
    }

    $action = $_POST['action'] ?? 'update_profile';

    if ($action === 'update_profile' && $isOwnProfile) {
        $res = update_user_and_household_profile($currentUser, $_POST);
        if ($res['ok']) {
            set_flash('success', 'Profil ve hane bilgileriniz güncellendi.');
        } else {
            set_flash('error', $res['error']);
        }
        redirect('profile.php');
    }

    if ($action === 'create_post' && $isOwnProfile) {
        $file = $_FILES['post_image'] ?? null;
        $res  = create_post($currentUser, $_POST, $file);
        if ($res['ok']) {
            set_flash('success', 'Paylaşımınız zaman tünelinize eklendi.');
        } else {
            set_flash('error', $res['error']);
        }
        redirect('profile.php');
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
        redirect('profile.php?id=' . (int) $profileUser['id'] . '#post-' . $postId);
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
        redirect('profile.php?id=' . (int) $profileUser['id'] . '#post-' . $postId);
    }

    if ($action === 'delete_post') {
        $postId = (int) ($_POST['post_id'] ?? 0);
        if ($postId > 0 && delete_post($postId, $currentUser)) {
            set_flash('success', 'Paylaşım silindi.');
        }
        redirect('profile.php?id=' . (int) $profileUser['id']);
    }
}

$relation      = get_friendship_relation((int) $currentUser['id'], (int) $profileUser['id']);
$userFriends   = get_user_friends((int) $profileUser['id']);
$timelinePosts = get_user_timeline_posts($profileUser, $currentUser);

$pageTitle = $profileUser['full_name'] . ' — Zaman Tüneli — Debi';
$activeNav = $isOwnProfile ? 'profile' : 'friends';
require_once __DIR__ . '/includes/header.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/includes/sidebar_left.php'; ?>

    <main class="main-column">
        <!-- Zaman Tüneli (Timeline) Üst Profil Kartı -->
        <section class="panel-card timeline-hero-card">
            <div class="timeline-hero-top">
                <div class="profile-header-block">
                    <span class="avatar-circle avatar-lg" style="background-color: <?= e($profileUser['avatar_color']) ?>;">
                        <?= e(user_initials($profileUser['full_name'])) ?>
                    </span>
                    <div class="profile-hero-info">
                        <div class="profile-title-row">
                            <h1 class="page-title"><?= e($profileUser['full_name']) ?></h1>
                            <?php if ($profileUser['role'] === 'manager'): ?>
                                <span class="tag tag-manager">Apartman Yöneticisi</span>
                            <?php endif; ?>
                        </div>
                        <p class="page-subtitle">
                            <?= e($profileUser['neighborhood_district'] . ' / ' . $profileUser['neighborhood_city']) ?>
                            ·
                            <?php if (!empty($profileUser['apartment_id'])): ?>
                                <strong><?= e($profileUser['apartment_name']) ?></strong> (<?= e($profileUser['unit_label']) ?>)
                            <?php else: ?>
                                <strong><?= e(housing_type_label($profileUser['housing_type'])) ?></strong> (<?= e($profileUser['unit_label']) ?>)
                            <?php endif; ?>
                        </p>
                        <?php if (!empty($profileUser['bio'])): ?>
                            <p class="timeline-bio"><?= e($profileUser['bio']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sağ Aksiyon Butonları (Arkadaş Ekle / Düzenle) -->
                <div class="timeline-hero-actions">
                    <?php if ($isOwnProfile): ?>
                        <button type="button" class="btn btn-outline btn-sm" id="toggleEditProfileBtn">
                            Profili &amp; Haneyi Düzenle
                        </button>
                    <?php else: ?>
                        <?php $relState = $relation['state'] ?? 'none'; ?>
                        <?php if ($relState === 'accepted'): ?>
                            <form method="post" action="friends.php" onsubmit="return confirm('Arkadaşlıktan çıkarmak istediğinize emin misiniz?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="remove_friend">
                                <input type="hidden" name="target_user_id" value="<?= (int) $profileUser['id'] ?>">
                                <input type="hidden" name="return_url" value="profile.php?id=<?= (int) $profileUser['id'] ?>">
                                <button type="submit" class="btn btn-outline btn-sm">✓ Arkadaşsınız · Çıkar</button>
                            </form>
                        <?php elseif ($relState === 'pending_sent'): ?>
                            <form method="post" action="friends.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="remove_friend">
                                <input type="hidden" name="target_user_id" value="<?= (int) $profileUser['id'] ?>">
                                <input type="hidden" name="return_url" value="profile.php?id=<?= (int) $profileUser['id'] ?>">
                                <button type="submit" class="btn btn-outline btn-sm">İstek Gönderildi (İptal)</button>
                            </form>
                        <?php elseif ($relState === 'pending_received'): ?>
                            <form method="post" action="friends.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="accept_request">
                                <input type="hidden" name="target_user_id" value="<?= (int) $profileUser['id'] ?>">
                                <input type="hidden" name="return_url" value="profile.php?id=<?= (int) $profileUser['id'] ?>">
                                <button type="submit" class="btn btn-primary btn-sm">✓ İsteği Kabul Et</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="friends.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="send_request">
                                <input type="hidden" name="target_user_id" value="<?= (int) $profileUser['id'] ?>">
                                <input type="hidden" name="return_url" value="profile.php?id=<?= (int) $profileUser['id'] ?>">
                                <button type="submit" class="btn btn-primary btn-sm">+ Arkadaş Ekle</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Profil İstatistik Şeridi -->
            <div class="timeline-stats-strip">
                <div class="timeline-stat-pill">
                    <span>Arkadaşlar:</span>
                    <strong><?= count($userFriends) ?> kişi</strong>
                </div>
                <div class="timeline-stat-pill">
                    <span>Paylaşımlar:</span>
                    <strong><?= count($timelinePosts) ?> gönderi</strong>
                </div>
                <div class="timeline-stat-pill">
                    <span>Bu Ayki Tüketim:</span>
                    <strong>
                        <?php if ($profileUser['current_daily_liters'] !== null): ?>
                            <?= number_format((float) $profileUser['current_daily_liters'], 1, ',', '.') ?> L / kişi·gün
                        <?php else: ?>
                            Henüz fatura girilmedi
                        <?php endif; ?>
                    </strong>
                </div>
            </div>

            <!-- Kullanıcının Arkadaşları Önizleme Şeridi -->
            <?php if (!empty($userFriends)): ?>
                <div class="timeline-friends-strip">
                    <span class="timeline-friends-label">Arkadaşları:</span>
                    <div class="timeline-friends-avatars">
                        <?php foreach (array_slice($userFriends, 0, 8) as $fr): ?>
                            <a href="profile.php?id=<?= (int) $fr['id'] ?>" class="friend-chip-mini" title="<?= e($fr['full_name']) ?>">
                                <span class="avatar-circle avatar-xs" style="background-color: <?= e($fr['avatar_color']) ?>;">
                                    <?= e(user_initials($fr['full_name'])) ?>
                                </span>
                                <span><?= e($fr['full_name']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($isOwnProfile): ?>
            <!-- Kendi Profilimizse: Gizlenebilir Profil & Hane Düzenleme Kutusu -->
            <section class="panel-card" id="editProfileSection" <?= $showEditForm ? '' : 'hidden' ?>>
                <h2 class="section-title">Kişisel ve Hane Ayarları</h2>
                <p class="panel-desc">
                    Hanede yaşayan kişi sayısını güncel tutmanız, su faturalarınızdan kişi başı günlük litre hesabının doğru yapılmasını sağlar.
                </p>

                <form method="post" action="profile.php" class="form-stack">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_profile">

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="full_name">Ad Soyad</label>
                            <input type="text" id="full_name" name="full_name" required
                                   value="<?= e($currentUser['full_name']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="email_readonly">E-posta Adresi</label>
                            <input type="email" id="email_readonly" value="<?= e($currentUser['email']) ?>" disabled>
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="unit_label">Daire / Ev Tanımı</label>
                            <input type="text" id="unit_label" name="unit_label" required
                                   value="<?= e($currentUser['unit_label']) ?>" placeholder="Örn: Daire 4 veya Müstakil No:12">
                        </div>
                        <div class="form-group">
                            <label for="resident_count">Hanede Yaşayan Kişi Sayısı</label>
                            <input type="number" id="resident_count" name="resident_count" min="1" max="20" required
                                   value="<?= (int) $currentUser['resident_count'] ?>">
                        </div>
                    </div>

                    <?php if (empty($currentUser['apartment_id'])): ?>
                        <div class="form-group">
                            <label for="individual_housing_type">Bireysel Konut Türü</label>
                            <select id="individual_housing_type" name="individual_housing_type">
                                <option value="individual_unit" <?= $currentUser['housing_type'] === 'individual_unit' ? 'selected' : '' ?>>
                                    Apartman Dairesi (Bireysel katılımcı)
                                </option>
                                <option value="detached_house" <?= $currentUser['housing_type'] === 'detached_house' ? 'selected' : '' ?>>
                                    Müstakil Ev
                                </option>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="bio">Kısa Biyografi / Not (Zaman tünelinizde görünür)</label>
                        <input type="text" id="bio" name="bio"
                               value="<?= e($currentUser['bio'] ?? '') ?>"
                               placeholder="Örn: Balkonda yağmur suyu biriktiriyoruz.">
                    </div>

                    <div class="bill-confirm-actions">
                        <button type="submit" class="btn btn-primary">Değişiklikleri Kaydet</button>
                    </div>
                </form>
            </section>

            <!-- Kendi Zaman Tünelinden Doğrudan Paylaşım Kutusu -->
            <section class="panel-card composer-card">
                <form method="post" action="profile.php" class="composer-form" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_post">
                    <input type="hidden" name="category" value="tip">

                    <textarea name="content" rows="2" required
                              placeholder="Zaman tünelinizde yeni bir su tasarrufu notu veya fatura gelişmesi paylaşın..."></textarea>

                    <div class="composer-bar-clean">
                        <div class="composer-bar-left">
                            <select name="visibility" class="composer-scope-select" aria-label="Görünürlük">
                                <option value="friends">Sadece Arkadaşlarım</option>
                                <?php if (!empty($currentUser['apartment_id'])): ?>
                                    <option value="apartment">Apartmanım (<?= e($currentUser['apartment_name']) ?>)</option>
                                <?php endif; ?>
                                <option value="public" selected>Genel Debi (Herkes)</option>
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
        <?php endif; ?>

        <!-- Zaman Tüneli Gönderileri -->
        <div class="timeline-section-header">
            <h2 class="section-title">
                <?= $isOwnProfile ? 'Zaman Tünelim' : e($profileUser['full_name']) . ' — Zaman Tüneli' ?>
            </h2>
        </div>

        <?php if (empty($timelinePosts)): ?>
            <div class="panel-card empty-state-card">
                <h3>Zaman tünelinde henüz paylaşım bulunmuyor</h3>
                <p>
                    <?= $isOwnProfile
                        ? 'Yukarıdaki kutudan ilk paylaşımınızı ekleyebilir veya su faturanızı okuttuğunuzda sonucunu paylaşabilirsiniz.'
                        : 'Bu kullanıcının henüz herkese açık veya arkadaşlarına özel bir paylaşımı yok.' ?>
                </p>
            </div>
        <?php else: ?>
            <div class="post-list">
                <?php foreach ($timelinePosts as $post): ?>
                    <article class="panel-card post-card" id="post-<?= (int) $post['id'] ?>">
                        <div class="post-header">
                            <div class="post-author-group">
                                <a href="profile.php?id=<?= (int) $post['user_id'] ?>">
                                    <span class="avatar-circle" style="background-color: <?= e($post['avatar_color']) ?>;">
                                        <?= e(user_initials($post['full_name'])) ?>
                                    </span>
                                </a>
                                <div>
                                    <div class="post-author-line">
                                        <a href="profile.php?id=<?= (int) $post['user_id'] ?>" class="author-link">
                                            <strong><?= e($post['full_name']) ?></strong>
                                        </a>
                                    </div>
                                    <div class="post-location-line">
                                        <?php if ($post['visibility'] === 'apartment'): ?>
                                            <span>Apartman İçi</span>
                                        <?php elseif ($post['visibility'] === 'friends'): ?>
                                            <span>Arkadaşlar</span>
                                        <?php else: ?>
                                            <span>Genel Debi</span>
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
                            <form method="post" action="profile.php?id=<?= (int) $profileUser['id'] ?>" class="reaction-form" data-post-id="<?= (int) $post['id'] ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle_reaction">
                                <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
                                <button type="submit" class="btn-action <?= (int) $post['user_reacted'] > 0 ? 'reacted' : '' ?>">
                                    <span class="drop-symbol">💧</span>
                                    <span class="reaction-label">Damla Desteği</span>
                                    <span class="reaction-count">(<?= (int) $post['reaction_count'] ?>)</span>
                                </button>
                            </form>

                            <button type="button" class="btn-action js-toggle-comments" data-target="comments-<?= (int) $post['id'] ?>">
                                Yorumlar (<?= (int) $post['comment_count'] ?>)
                            </button>

                            <?php if ((int) $post['user_id'] === (int) $currentUser['id']): ?>
                                <form method="post" action="profile.php?id=<?= (int) $profileUser['id'] ?>" class="delete-post-form" onsubmit="return confirm('Bu paylaşımı silmek istediğinize emin misiniz?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_post">
                                    <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
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
                                            <a href="profile.php?id=<?= (int) $comment['user_id'] ?>">
                                                <span class="avatar-circle avatar-xs" style="background-color: <?= e($comment['avatar_color']) ?>;">
                                                    <?= e(user_initials($comment['full_name'])) ?>
                                                </span>
                                            </a>
                                            <div class="comment-content">
                                                <div class="comment-meta">
                                                    <a href="profile.php?id=<?= (int) $comment['user_id'] ?>" class="author-link">
                                                        <strong><?= e($comment['full_name']) ?></strong>
                                                    </a>
                                                    <time><?= e(time_ago($comment['created_at'])) ?></time>
                                                </div>
                                                <p><?= nl2br(e($comment['content'])) ?></p>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <form method="post" action="profile.php?id=<?= (int) $profileUser['id'] ?>" class="comment-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="add_comment">
                                <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
                                <input type="text" name="comment_content" required placeholder="Yorum yazın...">
                                <button type="submit" class="btn btn-outline btn-sm">Gönder</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <?php require_once __DIR__ . '/includes/sidebar_right.php'; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
