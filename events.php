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

$events = get_events_for_user($currentUser);
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
                <article class="panel-card" style="margin-top:12px;">
                    <div class="panel-card-header">
                        <div><h2 class="section-title"><?= e($event['title']) ?></h2><p><?= e($event['event_date']) ?> · <?= e(substr($event['start_time'], 0, 5)) ?><?= $event['end_time'] ? '–' . e(substr($event['end_time'], 0, 5)) : '' ?> · <?= e($event['location']) ?></p></div>
                        <span class="tag"><?= (int) $event['points'] ?> DP</span>
                    </div>
                    <p><?= nl2br(e($event['description'])) ?></p>
                    <p class="form-help">Puan gerekçesi: <?= e($event['points_reason']) ?></p>
                    <p class="form-help">Oluşturan: <?= e($event['creator_name']) ?> · <?= (int) $event['attendee_count'] ?> katılımcı</p>
                    <?php if ((int) $event['creator_id'] !== (int) $currentUser['id'] && !$event['attendee_status']): ?>
                        <form method="post"><input type="hidden" name="action" value="join_event"><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>"><?= csrf_field() ?><button class="btn btn-primary btn-sm">Katılacağım</button></form>
                    <?php elseif ((int) $event['creator_id'] !== (int) $currentUser['id']): ?>
                        <span class="tag"><?= $event['attendee_status'] === 'attended' ? 'Yoklama alındı' : 'Katılımınız kayıtlı' ?></span>
                        <?php if ($event['attendee_status'] === 'attended' && $event['photo_status'] !== 'approved'): ?>
                            <form method="post" enctype="multipart/form-data" class="form-stack" style="margin-top:10px;"><input type="hidden" name="action" value="upload_event_photo"><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>"><?= csrf_field() ?><input type="file" name="event_photo" accept="image/jpeg,image/png,image/webp" required><button class="btn btn-outline btn-sm">Etkinlik Fotoğrafını Gönder</button></form>
                        <?php elseif ($event['photo_status'] === 'approved'): ?><span class="tag">Fotoğraf doğrulandı, puan verildi</span><?php endif; ?>
                    <?php endif; ?>
                    <?php if ((int) $event['creator_id'] === (int) $currentUser['id']): ?>
                        <form method="post" class="form-stack" style="margin-top:12px;"><input type="hidden" name="action" value="mark_attendance"><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>"><?= csrf_field() ?><strong>Yoklama</strong><?php foreach ($managedAttendees[(int) $event['id']] as $attendee): ?><label><input type="checkbox" name="attendee_ids[]" value="<?= (int) $attendee['user_id'] ?>" <?= $attendee['status'] === 'attended' ? 'checked' : '' ?>> <?= e($attendee['full_name']) ?></label><?php endforeach; ?><button class="btn btn-outline btn-sm">Yoklamayı Kaydet</button></form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>
    </main>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
