<?php
/**
 * DEBI - Ana Sosyal Medya Akışı (Arkadaşlar / Apartman / Genel Debi)
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/modules/feed/feed_service.php';

$currentUser    = require_login();
$isDetachedHome = ($currentUser['housing_type'] ?? '') === 'detached_house' && empty($currentUser['apartment_id']);

// POST İşlemleri (Gönderi, Damla Tepkisi, Yorum, Silme, Etkinlik İşlemleri)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('error', 'Güvenlik doğrulaması başarısız oldu.');
        redirect('index.php');
    }

    $action    = $_POST['action'] ?? '';
    $returnTab = $_POST['return_scope'] ?? 'friends';
    if (!in_array($returnTab, ['friends', 'apartment', 'public'], true)) {
        $returnTab = 'friends';
    }

    // 1. Standart Gönderi Paylaşımı
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
        redirect('index.php?scope=' . urlencode($returnTab));
    }

    // 2. Akışta Etkinlik Oluşturma (Yapay Zeka Puanlamalı)
    if ($action === 'create_event') {
        $res = create_event($currentUser, $_POST);
        if ($res['ok']) {
            set_flash('success', '📅 Etkinlik oluşturuldu! Yapay zeka katılanlar için +' . (int) $res['reward_points'] . ' Damla ödül belirledi.');
            $vis = $_POST['visibility'] ?? 'friends';
            if (in_array($vis, ['friends', 'apartment', 'public'], true)) {
                $returnTab = $vis;
            }
        } else {
            set_flash('error', $res['error']);
        }
        redirect('index.php?scope=' . urlencode($returnTab));
    }

    // 3. Etkinliğe Katılacağım / Vazgeç (RSVP)
    if ($action === 'toggle_event_rsvp') {
        $eventId = (int) ($_POST['event_id'] ?? 0);
        if ($eventId > 0) {
            $res = toggle_event_rsvp($eventId, (int) $currentUser['id'], (int) $currentUser['household_id']);
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($res);
                exit;
            }
        }
        redirect('index.php?scope=' . urlencode($returnTab) . '#event-' . $eventId);
    }

    // 4. Etkinliği Canlıya Başlat (Oluşturan Kişi)
    if ($action === 'start_event') {
        $eventId = (int) ($_POST['event_id'] ?? 0);
        if ($eventId > 0) {
            $res = start_event($eventId, (int) $currentUser['id']);
            if ($res['ok']) {
                set_flash('success', '🔴 Etkinlik canlıya alındı! Katılımcılar için sohbet ve yoklama odası açıldı.');
            }
        }
        redirect('index.php?scope=' . urlencode($returnTab));
    }

    // 5. Yoklama Alma (Oluşturan Kişinin Gelenleri İşaretlemesi)
    if ($action === 'toggle_attendance') {
        $eventId   = (int) ($_POST['event_id'] ?? 0);
        $targetUid = (int) ($_POST['target_user_id'] ?? $_POST['user_id'] ?? 0);
        if ($eventId > 0 && $targetUid > 0) {
            $res = toggle_participant_attendance($eventId, $targetUid, (int) $currentUser['id']);
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($res);
                exit;
            }
        }
        redirect('index.php?scope=' . urlencode($returnTab));
    }

    // 6. Canlı Etkinlik Sohbet Mesajı Gönder
    if ($action === 'send_event_message') {
        $eventId = (int) ($_POST['event_id'] ?? 0);
        $msg     = trim((string) ($_POST['event_message'] ?? $_POST['message'] ?? ''));
        if ($eventId > 0 && $msg !== '') {
            $res = send_event_message($eventId, (int) $currentUser['id'], $msg);
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json; charset=utf-8');
                if ($res['ok']) {
                    $res['user_name']    = $currentUser['full_name'];
                    $res['avatar_photo'] = $currentUser['avatar_photo'] ?? null;
                    $res['avatar_color'] = $currentUser['avatar_color'] ?? '#1b5670';
                    $res['message']      = $msg;
                    $res['time_ago']     = 'Az önce';
                }
                echo json_encode($res);
                exit;
            }
        }
        redirect('index.php?scope=' . urlencode($returnTab));
    }

    // 7. Etkinliği Tamamla
    if ($action === 'complete_event') {
        $eventId = (int) ($_POST['event_id'] ?? 0);
        if ($eventId > 0) {
            $res = complete_event($eventId, (int) $currentUser['id']);
            if ($res['ok']) {
                set_flash('success', '🏁 Etkinlik tamamlandı! Katılanlar özetlerini yazıp ödüllerini kazanabilir.');
            }
        }
        redirect('index.php?scope=' . urlencode($returnTab));
    }

    // 8. Katılımcının "Ne Yaptım & Ne Öğrendim" Özetini Göndermesi & Ödülünü Alması
    if ($action === 'submit_event_takeaway') {
        $eventId = (int) ($_POST['event_id'] ?? 0);
        $summary = trim((string) ($_POST['summary_note'] ?? ''));
        if ($eventId > 0 && $summary !== '') {
            $res = submit_event_takeaway($eventId, (int) $currentUser['id'], $summary);
            if ($res['ok']) {
                set_flash('success', '🎉 Tebrikler! Etkinlik özetiniz paylaşıldı ve dairenize +' . (int) $res['reward_points'] . ' Damla 💧 eklendi!');
            } else {
                set_flash('error', $res['error']);
            }
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

$scope = $_GET['scope'] ?? 'friends';
if (!in_array($scope, ['friends', 'apartment', 'public'], true)) {
    $scope = 'friends';
}
// Müstakil ev kullanıcılarının apartman akışı yoktur; yalnızca Arkadaşlar ve Genel Debi vardır
if ($isDetachedHome && $scope === 'apartment') {
    $scope = 'friends';
}

$posts           = get_feed_posts($currentUser, $scope, null);
$activeLiveEvent = get_active_live_event_for_user((int) $currentUser['id']);
$feedEvents      = get_feed_events($currentUser, $scope, 8);

$pageTitle = 'Sosyal Akış — Debi';
$activeNav = 'feed';
require_once __DIR__ . '/includes/header.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/includes/sidebar_left.php'; ?>

    <main class="main-column">
        <?php if ($activeLiveEvent !== null): ?>
            <?php
            $isEventHost       = ((int) $activeLiveEvent['user_id'] === (int) $currentUser['id']);
            $isLive            = ($activeLiveEvent['status'] === 'live');
            $isCompleted       = ($activeLiveEvent['status'] === 'completed');
            $userAttended      = ((int) ($activeLiveEvent['attended'] ?? 0) === 1);
            $userPointsAwarded = ((int) ($activeLiveEvent['points_awarded'] ?? 0) > 0);
            $userSummary       = trim((string) ($activeLiveEvent['summary_note'] ?? ''));
            ?>
            <!-- Canlı Etkinlik Odası & Canlı Sohbet -->
            <section class="panel-card live-event-room" id="liveEventRoom">
                <div class="live-event-header">
                    <div class="live-badge-row">
                        <?php if ($isLive): ?>
                            <span class="live-pulse-badge"><span class="pulse-dot"></span> CANLI ETKİNLİK ODASI</span>
                        <?php elseif ($isCompleted): ?>
                            <span class="live-pulse-badge badge-done">🏁 ETKİNLİK TAMAMLANDI</span>
                        <?php else: ?>
                            <span class="live-pulse-badge badge-soon">⏰ ETKİNLİK ZAMANI GELDİ</span>
                        <?php endif; ?>
                        <span class="live-event-scope-tag">
                            <?= e(match($activeLiveEvent['visibility']) { 'friends' => '👥 Arkadaşlar', 'apartment' => '🏢 Apartman', default => '🌍 Genel Debi' }) ?>
                        </span>
                    </div>
                    <h2 class="live-event-title"><?= e($activeLiveEvent['title']) ?></h2>
                    <div class="live-event-meta">
                        <span>📅 <?= date('d.m.Y H:i', strtotime($activeLiveEvent['event_date'])) ?></span>
                        <span class="meta-sep">&middot;</span>
                        <span>📍 <?= e($activeLiveEvent['location']) ?></span>
                        <span class="meta-sep">&middot;</span>
                        <span>Düzenleyen: <strong><?= e($activeLiveEvent['creator_name']) ?></strong></span>
                    </div>
                </div>

                <!-- Yapay Zeka Ödülü & Gerekçesi (Maks 100 Karakter) -->
                <div class="live-ai-card">
                    <div class="live-ai-icon">✨</div>
                    <div class="live-ai-body">
                        <div class="live-ai-title">
                            <span>Yapay Zeka Etkinlik Ödülü:</span>
                            <strong class="live-ai-points">+<?= (int) $activeLiveEvent['reward_points'] ?> Damla 💧</strong>
                        </div>
                        <p class="live-ai-reason">“<?= e($activeLiveEvent['ai_reason']) ?>”</p>
                    </div>
                </div>

                <?php if (!empty($activeLiveEvent['description'])): ?>
                    <div class="live-event-desc">
                        <p><?= nl2br(e($activeLiveEvent['description'])) ?></p>
                    </div>
                <?php endif; ?>

                <!-- Yoklama & Yönetici Kontrolleri / Katılımcı Durumu -->
                <div class="live-event-status-box">
                    <?php if ($isEventHost): ?>
                        <div class="host-controls">
                            <div class="host-controls-head">
                                <div>
                                    <h4 class="host-controls-title">📋 Yoklama Listesi (Gelenleri İşaretleyin)</h4>
                                    <span class="sub-label">Yoklamada gelen olarak işaretlenen sakinler, etkinlik sonunda ne yaptıklarını özetleyerek ödül puanlarını alacaktır.</span>
                                </div>
                                <?php if (!$isCompleted): ?>
                                    <form method="post" action="index.php" onsubmit="return confirm('Etkinliği sonlandırmak istediğinize emin misiniz? Katılımcılar ne yaptıklarını özetleyerek ödüllerini alacaktır.');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="complete_event">
                                        <input type="hidden" name="event_id" value="<?= (int) $activeLiveEvent['id'] ?>">
                                        <input type="hidden" name="return_scope" value="<?= e($scope) ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">🏁 Etkinliği Tamamla</button>
                                    </form>
                                <?php else: ?>
                                    <span class="status-chip chip-success">✓ Etkinlik Tamamlandı</span>
                                <?php endif; ?>
                            </div>

                            <?php if (empty($activeLiveEvent['participants'])): ?>
                                <p class="empty-note">Henüz katılacağını bildiren sakin yok.</p>
                            <?php else: ?>
                                <div class="yoklama-list">
                                    <?php foreach ($activeLiveEvent['participants'] as $p): ?>
                                        <div class="yoklama-item <?= (int) $p['attended'] === 1 ? 'is-attended' : '' ?>">
                                            <div class="yoklama-user">
                                                <?= render_avatar($p, 'avatar-xs') ?>
                                                <div class="yoklama-name">
                                                    <strong><?= e($p['full_name']) ?></strong>
                                                    <span><?= e($p['unit_label']) ?></span>
                                                </div>
                                            </div>
                                            <form method="post" action="index.php" class="yoklama-toggle-form">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="toggle_attendance">
                                                <input type="hidden" name="event_id" value="<?= (int) $activeLiveEvent['id'] ?>">
                                                <input type="hidden" name="user_id" value="<?= (int) $p['user_id'] ?>">
                                                <input type="hidden" name="return_scope" value="<?= e($scope) ?>">
                                                <button type="submit" class="btn btn-sm yoklama-btn <?= (int) $p['attended'] === 1 ? 'btn-attended' : 'btn-outline' ?>">
                                                    <?= (int) $p['attended'] === 1 ? '✓ Geldi' : 'Katılmadı' ?>
                                                </button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- Katılımcı Durum Alanı -->
                        <div class="attendee-status-card">
                            <div class="attendee-status-indicator">
                                <?php if ($userAttended): ?>
                                    <div class="status-chip chip-success">✓ Yoklamadasınız (Katılımınız onaylandı)</div>
                                <?php else: ?>
                                    <div class="status-chip chip-waiting">⏳ Yoklama Bekleniyor (Düzenleyici yoklama alıyor)</div>
                                <?php endif; ?>
                            </div>

                            <?php if ($isCompleted && $userAttended): ?>
                                <?php if ($userPointsAwarded && $userSummary !== ''): ?>
                                    <div class="takeaway-confirmed">
                                        <div class="takeaway-head">
                                            <span>🎉 <strong>Tebrikler!</strong> Özetiniz kaydedildi ve dairenize <strong>+<?= (int) $activeLiveEvent['reward_points'] ?> Damla 💧</strong> yüklendi!</span>
                                        </div>
                                        <blockquote class="takeaway-quote">“<?= e($userSummary) ?>”</blockquote>
                                    </div>
                                <?php else: ?>
                                    <div class="takeaway-form-wrap">
                                        <h4>🎉 Etkinlik Tamamlandı! Ne Yaptınız &amp; Ne Öğrendiniz?</h4>
                                        <p>Etkinlikte öğrendiklerinizi ve uyguladığınız su tasarrufu adımını kısaca özetleyin, yapay zekanın belirlediği <strong>+<?= (int) $activeLiveEvent['reward_points'] ?> Damla 💧</strong> ödülünüzü göletinize ekleyin!</p>
                                        <form method="post" action="index.php" class="takeaway-form">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="submit_event_takeaway">
                                            <input type="hidden" name="event_id" value="<?= (int) $activeLiveEvent['id'] ?>">
                                            <input type="hidden" name="return_scope" value="<?= e($scope) ?>">
                                            <textarea name="summary_note" rows="2" required placeholder="Örn: Yağmur hasadı montajında boru filtrelemeyi öğrendik ve balkonda uyguladık..."></textarea>
                                            <button type="submit" class="btn btn-primary btn-sm">Özeti Paylaş &amp; +<?= (int) $activeLiveEvent['reward_points'] ?> Damla Kazan 💧</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Canlı Etkinlik Sohbeti -->
                <div class="live-chat-wrap">
                    <div class="live-chat-header">
                        <h3>💬 Canlı Etkinlik Sohbeti</h3>
                        <span class="live-chat-sub">Katılımcılarla anlık konuşun</span>
                    </div>

                    <div class="live-chat-box" id="liveChatMessages">
                        <?php if (empty($activeLiveEvent['messages'])): ?>
                            <p class="empty-chat-note" id="emptyChatNote">Henüz mesaj yazılmadı. İlk mesajı siz yazın!</p>
                        <?php else: ?>
                            <?php foreach ($activeLiveEvent['messages'] as $msg): ?>
                                <div class="live-chat-msg <?= (int) $msg['user_id'] === (int) $currentUser['id'] ? 'is-me' : '' ?>" id="event-msg-<?= (int) $msg['id'] ?>">
                                    <?= render_avatar($msg, 'avatar-xs') ?>
                                    <div class="live-msg-content">
                                        <div class="live-msg-header">
                                            <strong><?= e($msg['full_name']) ?></strong>
                                            <time><?= e(time_ago($msg['created_at'])) ?></time>
                                        </div>
                                        <div class="live-msg-text"><?= nl2br(e($msg['message'])) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <form method="post" action="index.php" class="live-chat-form" id="liveChatForm">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="send_event_message">
                        <input type="hidden" name="event_id" value="<?= (int) $activeLiveEvent['id'] ?>">
                        <input type="hidden" name="return_scope" value="<?= e($scope) ?>">
                        <input type="text" name="message" id="liveChatInput" placeholder="Etkinlik sohbetine bir mesaj yazın..." required autocomplete="off">
                        <button type="submit" class="btn btn-primary btn-sm">Gönder</button>
                    </form>
                </div>
            </section>
        <?php endif; ?>

        <!-- Gönderi & Etkinlik Oluşturma Kutusu -->
        <section class="panel-card composer-card">
            <div class="composer-type-bar">
                <button type="button" class="composer-type-btn active js-composer-type" data-target="postComposerBox">
                    📝 Gönderi Paylaş
                </button>
                <button type="button" class="composer-type-btn js-composer-type" data-target="eventComposerBox">
                    📅 Etkinlik Oluştur (Yapay Zeka Puanlı)
                </button>
            </div>

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

            <!-- 1. Normal Gönderi Formu -->
            <div id="postComposerBox">
                <form method="post" action="index.php" class="composer-form" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_post">
                    <input type="hidden" name="return_scope" value="<?= e($scope) ?>">
                    <input type="hidden" name="category" value="tip">

                    <textarea name="content" rows="2" required
                              placeholder="Neler oluyor? Tasarruf ipucunu veya faturanı paylaş..."></textarea>

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
            </div>

            <!-- 2. Yapay Zeka Destekli Etkinlik Oluşturma Formu -->
            <div id="eventComposerBox" style="display: none;">
                <form method="post" action="index.php" class="composer-form form-stack">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_event">
                    <input type="hidden" name="return_scope" value="<?= e($scope) ?>">

                    <div class="event-form-grid">
                        <div class="form-group">
                            <label for="event_title">Etkinlik Başlığı</label>
                            <input type="text" id="event_title" name="title" required placeholder="Örn: Yağmur Suyu Hasadı &amp; Bahçe Tasarrufu">
                        </div>

                        <div class="form-group">
                            <label for="event_date">Tarih &amp; Saat</label>
                            <input type="datetime-local" id="event_date" name="event_date" required min="<?= date('Y-m-d\TH:i') ?>">
                        </div>

                        <div class="form-group">
                            <label for="event_location">Buluşma Yeri</label>
                            <input type="text" id="event_location" name="location" required placeholder="Örn: Apartman Bahçesi / Çardak / Çevrimiçi">
                        </div>

                        <div class="form-group">
                            <label for="event_visibility">Yayınlanacak Akış</label>
                            <select id="event_visibility" name="visibility" class="composer-scope-select">
                                <option value="friends" <?= $scope === 'friends' ? 'selected' : '' ?>>👥 Arkadaşlar</option>
                                <?php if (!empty($currentUser['apartment_id'])): ?>
                                    <option value="apartment" <?= $scope === 'apartment' ? 'selected' : '' ?>>🏢 <?= e($currentUser['apartment_name']) ?></option>
                                <?php endif; ?>
                                <option value="public" <?= $scope === 'public' ? 'selected' : '' ?>>🌍 Genel Debi</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 10px;">
                        <label for="event_description">Etkinlik Detayları &amp; Su Tasarrufu Amacı</label>
                        <textarea id="event_description" name="description" rows="2" required placeholder="Bu etkinlikte ne yapılacak? Topluluk ve haneler su tasarrufuna nasıl katkı sağlayacak? (Yapay zeka bunu inceleyerek ödül puanını belirler)"></textarea>
                    </div>

                    <div class="composer-bar-clean" style="margin-top: 10px;">
                        <div class="composer-ai-hint">
                            <span>✨ Yapay zeka etkinliğinizi analiz edip ödül puanı ve gerekçe atayacaktır.</span>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">✨ Etkinliği Oluştur</button>
                    </div>
                </form>
            </div>
        </section>

        <!-- Akış Sekmeleri (Arkadaşlar / Apartmanım / Genel Debi) -->
        <div class="feed-tabs-bar">
            <div class="feed-tabs <?= $isDetachedHome ? 'feed-tabs-2' : '' ?>">
                <a href="index.php?scope=friends" class="feed-tab <?= $scope === 'friends' ? 'active' : '' ?>">
                    <span>Arkadaşlar</span>
                </a>
                <?php if (!$isDetachedHome): ?>
                    <a href="index.php?scope=apartment" class="feed-tab <?= $scope === 'apartment' ? 'active' : '' ?>">
                        <span><?= !empty($currentUser['apartment_name']) ? e($currentUser['apartment_name']) : 'Apartman' ?></span>
                    </a>
                <?php endif; ?>
                <a href="index.php?scope=public" class="feed-tab <?= $scope === 'public' ? 'active' : '' ?>">
                    <span>Genel</span>
                </a>
            </div>
        </div>

        <!-- Akıştaki Etkinlikler (Yapay Zeka Destekli & Ödüllü) -->
        <?php if (!empty($feedEvents)): ?>
            <section class="events-feed-section">
                <div class="events-feed-header">
                    <div>
                        <h3 class="events-feed-title">📅 Akış Etkinlikleri</h3>
                        <span class="events-feed-sub">Yapay zeka ödüllü etkinliklere katılın, su tasarrufuyla göletinizi büyütün</span>
                    </div>
                </div>

                <div class="events-feed-list">
                    <?php foreach ($feedEvents as $evt): ?>
                        <?php
                        $isEventCreator = ((int) $evt['user_id'] === (int) $currentUser['id']);
                        $isAttending    = ($evt['my_rsvp'] === 'attending');
                        $isLive         = ($evt['status'] === 'live');
                        $isCompleted    = ($evt['status'] === 'completed');
                        $hasAttended    = ((int) ($evt['my_attended'] ?? 0) === 1);
                        ?>
                        <article class="panel-card event-feed-card <?= $isLive ? 'event-card-live' : '' ?>" id="event-<?= (int) $evt['id'] ?>">
                            <div class="event-feed-top">
                                <div class="event-author-info">
                                    <a href="profile.php?id=<?= (int) $evt['user_id'] ?>">
                                        <?= render_avatar($evt, 'avatar-sm') ?>
                                    </a>
                                    <div>
                                        <div class="event-author-line">
                                            <a href="profile.php?id=<?= (int) $evt['user_id'] ?>" class="author-link">
                                                <strong><?= e($evt['full_name']) ?></strong>
                                            </a>
                                            <span class="event-scope-badge">
                                                <?= e(match($evt['visibility']) { 'friends' => '👥 Arkadaşlar', 'apartment' => '🏢 Apartman', default => '🌍 Genel Debi' }) ?>
                                            </span>
                                        </div>
                                        <div class="event-location-sub">
                                            <?= !empty($evt['apartment_name']) ? e($evt['apartment_name'] . ' · ' . $evt['unit_label']) : e($evt['unit_label']) ?>
                                            &middot; <time><?= e(time_ago($evt['created_at'])) ?></time>
                                        </div>
                                    </div>
                                </div>

                                <div class="event-status-pill">
                                    <?php if ($isLive): ?>
                                        <span class="event-pill pill-live"><span class="pulse-dot"></span> CANLI</span>
                                    <?php elseif ($isCompleted): ?>
                                        <span class="event-pill pill-done">✓ Tamamlandı</span>
                                    <?php else: ?>
                                        <span class="event-pill pill-soon">⏰ Yaklaşıyor</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="event-feed-main">
                                <h3 class="event-feed-card-title"><?= e($evt['title']) ?></h3>

                                <div class="event-meta-chips">
                                    <span class="event-chip">📅 <?= date('d.m.Y H:i', strtotime($evt['event_date'])) ?></span>
                                    <span class="event-chip">📍 <?= e($evt['location']) ?></span>
                                    <span class="event-chip">👥 <strong><?= (int) $evt['participant_count'] ?></strong> Katılımcı</span>
                                </div>

                                <p class="event-feed-desc"><?= nl2br(e($evt['description'])) ?></p>

                                <!-- Yapay Zeka Ödül Rozeti & Gerekçesi (Maks 100 Karakter) -->
                                <div class="event-ai-badge-box">
                                    <div class="event-ai-badge-top">
                                        <span class="ai-sparkle">✨</span>
                                        <span class="ai-badge-title">Yapay Zeka Ödülü:</span>
                                        <strong class="ai-badge-pts">+<?= (int) $evt['reward_points'] ?> Damla 💧</strong>
                                    </div>
                                    <div class="event-ai-reason">
                                        “<?= e($evt['ai_reason']) ?>”
                                    </div>
                                </div>

                                <!-- Katılımcı Avatarları -->
                                <?php if (!empty($evt['attendees'])): ?>
                                    <div class="event-attendees-row">
                                        <div class="attendees-avatar-stack">
                                            <?php foreach ($evt['attendees'] as $att): ?>
                                                <a href="profile.php?id=<?= (int) $att['id'] ?>" title="<?= e($att['full_name']) ?>">
                                                    <?= render_avatar($att, 'avatar-xs') ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                        <span class="attendees-count-text">
                                            <strong><?= (int) $evt['participant_count'] ?></strong> sakin katılıyor
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <!-- Tamamlanan Katılımcı Özetleri (Ne Yaptım & Ne Öğrendim) -->
                                <?php if ($isCompleted && !empty($evt['takeaways'])): ?>
                                    <div class="event-takeaways-wrap">
                                        <h5 class="takeaways-title">🌱 Katılımcıların Öğrendikleri ve Uyguladıkları</h5>
                                        <div class="takeaways-list">
                                            <?php foreach ($evt['takeaways'] as $tk): ?>
                                                <div class="takeaway-bubble">
                                                    <?= render_avatar($tk, 'avatar-xs') ?>
                                                    <div class="takeaway-bubble-body">
                                                        <strong><?= e($tk['full_name']) ?>:</strong>
                                                        <span>“<?= e($tk['summary_note']) ?>”</span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="event-feed-actions">
                                <?php if ($isEventCreator): ?>
                                    <?php if ($evt['status'] === 'upcoming'): ?>
                                        <form method="post" action="index.php">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="start_event">
                                            <input type="hidden" name="event_id" value="<?= (int) $evt['id'] ?>">
                                            <input type="hidden" name="return_scope" value="<?= e($scope) ?>">
                                            <button type="submit" class="btn btn-primary btn-sm">🔴 Etkinliği Başlat (Canlıya Al)</button>
                                        </form>
                                    <?php elseif ($isLive): ?>
                                        <a href="#liveEventRoom" class="btn btn-primary btn-sm">🔴 Canlı Odaya Git</a>
                                    <?php else: ?>
                                        <span class="status-chip chip-success">✓ Etkinlik Tamamlandı</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php if (!$isCompleted): ?>
                                        <form method="post" action="index.php" class="event-rsvp-form" data-event-id="<?= (int) $evt['id'] ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle_event_rsvp">
                                            <input type="hidden" name="event_id" value="<?= (int) $evt['id'] ?>">
                                            <input type="hidden" name="return_scope" value="<?= e($scope) ?>">
                                            <button type="submit" class="btn btn-sm <?= $isAttending ? 'btn-primary' : 'btn-outline' ?>">
                                                <?= $isAttending ? '✓ Katılıyorsun (Vazgeç)' : '+ Katılacağım' ?>
                                            </button>
                                        </form>
                                        <?php if ($isLive && $isAttending): ?>
                                            <a href="#liveEventRoom" class="btn btn-outline btn-sm">💬 Canlı Sohbete Git</a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if ($hasAttended && empty($evt['my_summary_note'])): ?>
                                            <a href="#liveEventRoom" class="btn btn-primary btn-sm">🎉 Özeti Yaz &amp; Puanını Al</a>
                                        <?php else: ?>
                                            <span class="status-chip chip-success">✓ Etkinlik Sona Erdi</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

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
                            <a href="index.php?scope=public" class="btn btn-outline btn-sm">Genel Akış</a>
                        </div>
                    <?php else: ?>
                        <h3>Henüz paylaşım yok</h3>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="post-list">
                    <?php foreach ($posts as $post): ?>
                        <article class="panel-card post-card <?= $post['is_pinned'] ? 'post-pinned' : '' ?>" id="post-<?= (int) $post['id'] ?>">
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
                                            <?php if ($post['user_role'] === 'manager'): ?>
                                                <span class="tag tag-manager">Yönetici</span>
                                            <?php endif; ?>
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
                                        <span class="reaction-count"><?= (int) $post['reaction_count'] ?></span>
                                    </button>
                                </form>

                                <button type="button" class="btn-action js-toggle-comments" data-target="comments-<?= (int) $post['id'] ?>">
                                    💬 <?= (int) $post['comment_count'] ?>
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
                                                <a href="profile.php?id=<?= (int) $comment['user_id'] ?>">
                                                    <?= render_avatar($comment, 'avatar-xs') ?>
                                                </a>
                                                <div class="comment-content">
                                                    <div class="comment-meta">
                                                        <a href="profile.php?id=<?= (int) $comment['user_id'] ?>" class="author-link">
                                                            <strong><?= e($comment['full_name']) ?></strong>
                                                        </a>
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
