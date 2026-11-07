<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/modules/events/event_ai_service.php';
require_once __DIR__ . '/modules/events/event_service.php';

$currentUser = require_login();
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Güvenlik doğrulaması başarısız oldu.';
    } else {
        $action = $_POST['action'] ?? '';
        $eventId = (int) ($_POST['event_id'] ?? 0);
        if ($action === 'create_event') {
            $result = create_event($currentUser, $_POST);
            if ($result['ok']) {
                $success = 'Etkinlik oluşturuldu. Yapay zekanın önerdiği puan: ' . $result['ai']['points'] . ' DP. Gerekçe: ' . $result['ai']['reason'];
            } else {
                $error = $result['error'];
            }
        } elseif ($action === 'join_event') {
            $result = join_event($currentUser, $eventId);
            if ($result['ok']) {
                $success = $result['message'];
            } else {
                $error = $result['error'];
            }
        } elseif ($action === 'mark_attendance') {
            $result = mark_event_attendance($currentUser, $eventId, (array) ($_POST['attendee_ids'] ?? []));
            if ($result['ok']) {
                $success = $result['message'];
            } else {
                $error = $result['error'];
            }
        } elseif ($action === 'upload_event_photo') {
            $result = upload_event_photo($currentUser, $eventId, $_FILES['event_photo'] ?? []);
            if ($result['ok']) {
                $success = $result['message'];
            } else {
                $error = $result['error'];
            }
        }
    }
}

$events = [];
try {
    $events = get_events_for_user($currentUser);
} catch (RuntimeException $e) {
    $error = $e->getMessage();
}
$managedAttendees = [];
foreach ($events as $event) {
    if ((int) $event['creator_id'] === (int) $currentUser['id']) {
        $managedAttendees[(int) $event['id']] = get_event_attendees($currentUser, (int) $event['id']);
    }
}

$pageTitle = 'Etkinlikler — Debi';
$activeNav = 'events';
require_once __DIR__ . '/includes/header.php';
?>
<div class="app-shell">
    <?php require_once __DIR__ . '/includes/sidebar_left.php'; ?>
    <main class="main-column">
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

        <section class="panel-card">
            <div class="panel-card-header"><h1 class="section-title">Etkinlik Oluştur</h1></div>
            <form method="post" class="form-stack">
                <?= csrf_field() ?><input type="hidden" name="action" value="create_event">
                <div class="form-grid-2">
                    <div class="form-group"><label for="event_title">Başlık</label><input id="event_title" name="title" required maxlength="160" placeholder="Mahalle parkı temizliği"></div>
                    <div class="form-group"><label for="event_location">Yer</label><input id="event_location" name="location" required maxlength="255" placeholder="Atatürk Parkı"></div>
                </div>
                <div class="form-group"><label for="event_description">Ne yapılacak?</label><textarea id="event_description" name="description" rows="3" required placeholder="Fiziksel işin kapsamını ve beklenen katkıyı anlatın."></textarea></div>
                <div class="form-grid-3">
                    <div class="form-group"><label for="event_date">Tarih</label><input id="event_date" type="date" name="event_date" required min="<?= date('Y-m-d') ?>"></div>
                    <div class="form-group"><label for="start_time">Başlangıç</label><input id="start_time" type="time" name="start_time" required></div>
                    <div class="form-group"><label for="end_time">Bitiş (isteğe bağlı)</label><input id="end_time" type="time" name="end_time"></div>
                </div>
                <div class="form-group">
                    <label for="event_scope">Görünürlük</label>
                    <select id="event_scope" name="scope"><option value="neighborhood">Mahallem</option><option value="public">Genel Debi</option></select>
                </div>
                <p class="form-help">Katılım puanını yapay zeka belirler ve gerekçesini gösterir. Puan yalnızca yoklama ve ilgili fotoğraf doğrulamasından sonra verilir.</p>
                <button class="btn btn-primary" type="submit">Etkinliği AI ile Değerlendir ve Oluştur</button>
            </form>
        </section>

        <section class="panel-card">
            <div class="panel-card-header"><h1 class="section-title">Etkinlikler</h1></div>
            <?php if (!$events): ?><div class="empty-inline">Henüz etkinlik yok.</div><?php endif; ?>
            <?php foreach ($events as $event): ?>
                <?php
                $isCreator = (int) $event['creator_id'] === (int) $currentUser['id'];
                $isAttendee = !empty($event['attendee_status']);
                ?>
                <article class="event-card" style="margin-top:16px;">
                    <div class="panel-card-header">
                        <div>
                            <span class="event-scope-label"><?= $event['scope'] === 'public' ? 'Genel Debi' : 'Mahalle Etkinliği' ?></span>
                            <h2 class="section-title"><?= e($event['title']) ?></h2>
                            <p><?= e($event['event_date']) ?> · <?= e(substr($event['start_time'], 0, 5)) ?><?= $event['end_time'] ? '–' . e(substr($event['end_time'], 0, 5)) : '' ?> · <?= e($event['location']) ?></p>
                        </div>
                        <div class="event-points"><strong><?= (int) $event['points'] ?></strong><span>DP</span></div>
                    </div>
                    <p><?= nl2br(e($event['description'])) ?></p>
                    <div class="event-meta-row">
                        <span>Oluşturan: <?= e($event['creator_name']) ?></span>
                        <span><?= (int) $event['attendee_count'] ?> katılımcı</span>
                        <span class="tag"><?= $event['ended'] ? 'Etkinlik sona erdi' : ($event['started'] ? 'Etkinlik devam ediyor' : 'Başlamadı') ?></span>
                    </div>
                    <details class="event-reason">
                        <summary>AI puan gerekçesi</summary>
                        <p><?= e($event['points_reason']) ?></p>
                    </details>

                    <?php if (!$isCreator && !$isAttendee): ?>
                        <form method="post" class="event-action-row"><input type="hidden" name="action" value="join_event"><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>"><?= csrf_field() ?><button class="btn btn-primary btn-sm">Katılacağım</button></form>
                    <?php elseif ($isAttendee): ?>
                        <div class="event-participation">
                            <strong><?= $isCreator ? 'Etkinliği siz oluşturuyorsunuz; katılımınız otomatik kaydedildi.' : ($event['attendee_status'] === 'attended' ? 'Yoklamada katıldığınız işaretlendi.' : 'Katılımınız kayıtlı, yoklama bekleniyor.') ?></strong>
                            <?php if ($event['attendee_status'] === 'attended' && $event['photo_status'] !== 'approved' && $event['ended']): ?>
                                <form method="post" enctype="multipart/form-data" class="event-photo-form"><input type="hidden" name="action" value="upload_event_photo"><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>"><?= csrf_field() ?><input type="file" name="event_photo" accept="image/jpeg,image/png,image/webp" required><button class="btn btn-outline btn-sm">Fotoğraf yükle ve doğrula</button></form>
                            <?php elseif ($event['attendee_status'] === 'attended' && !$event['ended']): ?>
                                <span class="form-help">Etkinlik sona erince fotoğraf yükleyebilirsiniz.</span>
                            <?php elseif ($event['photo_status'] === 'approved'): ?>
                                <span class="tag">Fotoğraf doğrulandı, puan verildi</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($isCreator): ?>
                        <?php if ($event['started']): ?>
                            <form method="post" class="event-attendance"><input type="hidden" name="action" value="mark_attendance"><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>"><?= csrf_field() ?><h3>Yoklama</h3><p class="form-help">Etkinlik başladı; yalnızca gerçekten gelenleri işaretleyin.</p><?php foreach ($managedAttendees[(int) $event['id']] as $attendee): ?><label><input type="checkbox" name="attendee_ids[]" value="<?= (int) $attendee['user_id'] ?>" <?= $attendee['user_id'] == $currentUser['id'] || $attendee['status'] === 'attended' ? 'checked' : '' ?> <?= $attendee['user_id'] == $currentUser['id'] ? 'disabled' : '' ?>> <?= e($attendee['full_name']) ?><?= $attendee['user_id'] == $currentUser['id'] ? ' (oluşturan)' : '' ?></label><?php endforeach; ?><button class="btn btn-outline btn-sm">Yoklamayı kaydet</button></form>
                        <?php else: ?>
                            <div class="event-attendance-locked">Yoklama, etkinliğin başlama saatinde açılacak.</div>
                        <?php endif; ?>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>
    </main>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
