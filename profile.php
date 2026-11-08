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
$profileTaggableUsers = [];
$profileTaggableByVisibility = [];
foreach (['friends', 'apartment', 'public'] as $tagScope) {
    foreach (get_taggable_users($currentUser, $tagScope) as $tagUser) {
        $profileTaggableUsers[(int) $tagUser['id']] = $tagUser;
        $profileTaggableByVisibility[$tagScope][(int) $tagUser['id']] = $tagUser;
    }
}

// POST İşlemleri (Profil/Kapak fotoğrafı güncelleme, Zaman Tüneli paylaşımı, Damla Tepkisi, Yorum)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('error', 'Güvenlik doğrulaması başarısız oldu.');
        redirect('profile.php?id=' . (int) $profileUser['id']);
    }

    $action = $_POST['action'] ?? 'update_profile';

    if ($action === 'update_profile' && $isOwnProfile) {
        $res = update_user_and_household_profile($currentUser, $_POST, $_FILES);
        if ($res['ok']) {
            set_flash('success', 'Profiliniz ve fotoğraflarınız güncellendi.');
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
            $res = add_post_comment($currentUser, $postId, $content, (array) ($_POST['tagged_user_ids'] ?? []));
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
$friendCount   = count_user_friends((int) $profileUser['id']);
$timelinePosts = get_user_timeline_posts($profileUser, $currentUser);

$pageTitle       = $profileUser['full_name'] . ' — Zaman Tüneli — Debi';
$activeNav       = $isOwnProfile ? 'profile' : 'friends';
$profileUnitName = format_unit_label($profileUser['unit_label'] ?? '', $profileUser['housing_type'] ?? 'apartment_unit');
require_once __DIR__ . '/includes/header.php';
?>

<div class="app-shell app-shell-2col">
    <?php require_once __DIR__ . '/includes/sidebar_left.php'; ?>

    <main class="main-column">
        <!-- Zaman Tüneli (Timeline) Kapak & Profil Kartı -->
        <section class="panel-card timeline-profile-card">
            <div class="timeline-cover" <?php if (!empty($profileUser['cover_photo'])): ?>style="background-image: url('<?= e($profileUser['cover_photo']) ?>');"<?php endif; ?>></div>

            <div class="timeline-body">
                <div class="timeline-header-main">
                    <div class="timeline-avatar-wrap">
                        <?= render_avatar($profileUser, 'avatar-xl') ?>
                    </div>

                    <div class="timeline-identity">
                        <div class="profile-title-row">
                            <h1 class="page-title"><?= e($profileUser['full_name']) ?></h1>
                            <?php if ($profileUser['role'] === 'manager'): ?>
                                <span class="role-badge role-badge-apt">🏢 Apt. Yöneticisi</span>
                            <?php endif; ?>
                            <?php if (is_unit_manager($profileUser)): ?>
                                <span class="role-badge role-badge-unit">🏠 <?= e($profileUnitName) ?></span>
                            <?php else: ?>
                                <span class="role-badge role-badge-resident">👤 <?= e($profileUnitName) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="profile-nickname">@<?= e($profileUser['nickname'] ?? '') ?></div>

                        <p class="page-subtitle">
                            <?= e($profileUser['neighborhood_district'] . ' / ' . $profileUser['neighborhood_city']) ?>
                            &middot;
                            <strong><?= e($profileUnitName) ?></strong>
                        </p>

                        <?php if (!empty($profileUser['bio'])): ?>
                            <p class="timeline-bio"><?= e($profileUser['bio']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="timeline-hero-actions">
                        <?php if ($isOwnProfile): ?>
                            <button type="button" class="btn btn-outline btn-sm js-open-profile-edit" id="toggleEditProfileBtn">
                                Profili Düzenle
                            </button>
                        <?php else: ?>
                            <?php $relState = $relation['state'] ?? 'none'; ?>
                            <?php if ($relState === 'accepted'): ?>
                                <form method="post" action="friends.php" onsubmit="return confirm('Arkadaşlıktan çıkarmak istediğinize emin misiniz?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="remove_friend">
                                    <input type="hidden" name="target_user_id" value="<?= (int) $profileUser['id'] ?>">
                                    <input type="hidden" name="return_url" value="profile.php?id=<?= (int) $profileUser['id'] ?>">
                                    <button type="submit" class="btn btn-outline btn-sm">✓ Arkadaşsınız</button>
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

                <!-- Tek Satır Kompakt Sayaçlar -->
                <div class="timeline-meta-inline">
                    <?php if ($isOwnProfile): ?>
                        <a href="friends.php" class="timeline-meta-item">
                            <strong><?= $friendCount ?></strong> Arkadaş
                        </a>
                    <?php else: ?>
                        <span class="timeline-meta-item">
                            <strong><?= $friendCount ?></strong> Arkadaş
                        </span>
                    <?php endif; ?>
                    <span class="context-sep">·</span>
                    <span class="timeline-meta-item">
                        <strong><?= count($timelinePosts) ?></strong> Paylaşım
                    </span>
                    <?php if ($profileUser['current_daily_liters'] !== null): ?>
                        <span class="context-sep">·</span>
                        <span class="timeline-meta-item timeline-water-badge">
                            💧 Bu Ay: <strong><?= number_format((float) $profileUser['current_daily_liters'], 1, ',', '.') ?> L / gün</strong>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <?php if ($isOwnProfile): ?>
            <!-- Kendi Profilimizse: Şık ve Kompakt Profil & Fotoğraf Düzenleme Kartı -->
            <section class="panel-card profile-edit-card" id="editProfileSection" <?= $showEditForm ? '' : 'hidden' ?>>
                <div class="panel-card-header">
                    <div>
                        <h2 class="section-title">Profili &amp; Görselleri Düzenle</h2>
                    </div>
                    <button type="button" class="btn btn-outline btn-sm js-open-profile-edit">Kapat</button>
                </div>

                <form method="post" action="profile.php" enctype="multipart/form-data" class="form-stack">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_profile">

                    <!-- Özel Tasarımlı Görsel Seçici Kartlar -->
                    <div class="media-picker-grid">
                        <label for="avatar_photo_input" class="media-picker-card">
                            <div class="media-picker-thumb media-thumb-circle" id="avatarPickerThumb">
                                <?php if (!empty($currentUser['avatar_photo'])): ?>
                                    <img src="<?= e($currentUser['avatar_photo']) ?>" alt="Profil Fotoğrafı" id="avatarPreviewEl">
                                <?php else: ?>
                                    <span id="avatarInitialsEl"><?= e(user_initials($currentUser['full_name'])) ?></span>
                                    <img src="" alt="" id="avatarPreviewEl" hidden>
                                <?php endif; ?>
                            </div>
                            <div class="media-picker-info">
                                <strong>Profil Fotoğrafı</strong>
                                <span id="avatarFileName">JPG, PNG veya WebP · Maks. 5 MB</span>
                                <span class="media-picker-btn">Fotoğraf Seç</span>
                            </div>
                            <input type="file" id="avatar_photo_input" name="avatar_photo" accept="image/jpeg,image/png,image/webp">
                        </label>

                        <label for="cover_photo_input" class="media-picker-card">
                            <div class="media-picker-thumb media-thumb-wide" id="coverPickerThumb"
                                 <?php if (!empty($currentUser['cover_photo'])): ?>style="background-image: url('<?= e($currentUser['cover_photo']) ?>');"<?php endif; ?>>
                            </div>
                            <div class="media-picker-info">
                                <strong>Kapak Görseli</strong>
                                <span id="coverFileName">Geniş yatay görsel · Maks. 6 MB</span>
                                <span class="media-picker-btn">Kapak Seç</span>
                            </div>
                            <input type="file" id="cover_photo_input" name="cover_photo" accept="image/jpeg,image/png,image/webp">
                        </label>
                    </div>

                    <div class="form-grid-3">
                        <div class="form-group">
                            <label for="full_name">Ad Soyad</label>
                            <input type="text" id="full_name" name="full_name" required
                                   value="<?= e($currentUser['full_name']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="unit_label">Daire / Ev Tanımı</label>
                            <input type="text" id="unit_label" name="unit_label" required
                                   value="<?= e($currentUser['unit_label']) ?>" placeholder="Örn: Daire 4">
                        </div>
                        <div class="form-group">
                            <label for="resident_count">Hanedeki kişi sayısı</label>
                            <input type="number" id="resident_count" name="resident_count" min="1" max="20" required
                                   value="<?= (int) $currentUser['resident_count'] ?>">
                            <small>Hanede yaşayan toplam kişi sayısı. Kişi başı kullanım hesabı buna göre yapılır.</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="bio">Kısa Biyografi</label>
                        <input type="text" id="bio" name="bio"
                               value="<?= e($currentUser['bio'] ?? '') ?>"
                               placeholder="Örn: Su tasarrufu ve yağmur suyu hasadıyla ilgileniyorum.">
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

                    <div class="edit-profile-footer">
                        <button type="submit" class="btn btn-primary btn-sm">Değişiklikleri Kaydet</button>
                        <button type="button" class="btn btn-outline btn-sm js-open-profile-edit">Vazgeç</button>
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
                              placeholder="Bir şeyler paylaş..."></textarea>
                    <select name="tagged_user_ids[]" multiple class="mention-picker" aria-label="Kişileri etiketle" data-mention-scope>
                        <?php foreach ($profileTaggableByVisibility as $mentionScope => $mentionUsers): ?>
                            <?php foreach ($mentionUsers as $tagUser): ?>
                                <option value="<?= (int) $tagUser['id'] ?>" data-mention-visibility="<?= e($mentionScope) ?>" <?= $mentionScope !== 'public' ? 'hidden' : '' ?>>@<?= e($tagUser['nickname']) ?> — <?= e($tagUser['full_name']) ?></option>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </select>

                    <div class="composer-bar-clean">
                        <div class="composer-bar-left">
                            <select name="visibility" class="composer-scope-select" aria-label="Görünürlük">
                                <option value="friends">Arkadaşlar</option>
                                <?php if (!empty($currentUser['apartment_id'])): ?>
                                    <option value="apartment"><?= e($currentUser['apartment_name']) ?></option>
                                <?php endif; ?>
                                <option value="public" selected>Genel</option>
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
        <?php endif; ?>

        <!-- Zaman Tüneli Gönderileri -->
        <?php if (empty($timelinePosts)): ?>
            <div class="panel-card empty-state-card">
                <h3>Henüz paylaşım yok</h3>
            </div>
        <?php else: ?>
            <div class="post-list">
                <?php foreach ($timelinePosts as $post): ?>
                    <article class="panel-card post-card" id="post-<?= (int) $post['id'] ?>">
                        <div class="post-header">
                            <div class="post-author-group">
                                <a href="profile.php?id=<?= (int) $post['user_id'] ?>">
                                    <?= render_avatar($post) ?>
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
                                    <span class="reaction-count"><?= (int) $post['reaction_count'] ?></span>
                                </button>
                            </form>

                            <button type="button" class="btn-action js-toggle-comments" data-target="comments-<?= (int) $post['id'] ?>">
                                💬 <?= (int) $post['comment_count'] ?>
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
                                                <?= render_avatar($comment, 'avatar-xs') ?>
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
                                <select name="tagged_user_ids[]" multiple class="mention-picker" aria-label="Yorumda kişileri etiketle">
                                    <?php foreach ($profileTaggableByVisibility[$post['visibility']] ?? [] as $tagUser): ?>
                                        <option value="<?= (int) $tagUser['id'] ?>">@<?= e($tagUser['nickname']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-outline btn-sm">Gönder</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
