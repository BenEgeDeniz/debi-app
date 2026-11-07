<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/modules/events/event_ai_service.php';
require_once __DIR__ . '/modules/events/event_service.php';

$currentUser = require_login();
$error = null;
$success = get_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Güvenlik doğrulaması başarısız oldu.';
    } else {
        $action = $_POST['action'] ?? '';
        $eventId = (int) ($_POST['event_id'] ?? 0);
        if ($action === 'join_event') {
            $result = join_event($currentUser, $eventId);
        } elseif ($action === 'mark_attendance') {
            $result = mark_event_attendance($currentUser, $eventId, (array) ($_POST['attendee_ids'] ?? []));
        } elseif ($action === 'finish_event') {
            $result = finish_event($currentUser, $eventId);
        } elseif ($action === 'upload_event_photo') {
            $result = upload_event_photo($currentUser, $eventId, $_FILES['event_photo'] ?? []);
        } else {
            $result = ['ok' => false, 'error' => 'Geçersiz etkinlik işlemi.'];
        }

        if ($result['ok']) {
            set_flash('success', $result['message']);
            redirect('events.php');
        }
        $error = $result['error'];
    }
}

$events = [];
try {
    $events = get_events_for_user($currentUser);
} catch (RuntimeException $e) {
    $error = $e->getMessage();
}
$sections = categorize_events_for_user($events);
$managedAttendees = [];
foreach ($sections['active'] as $event) {
    if ((int) $event['creator_id'] === (int) $currentUser['id']) {
        $managedAttendees[(int) $event['id']] = get_event_attendees($currentUser, (int) $event['id']);
    }
}

function render_event_card(array $event, array $currentUser, array $managedAttendees): void
{
    $isCreator = (int) $event['creator_id'] === (int) $currentUser['id'];
    $isAttendee = !empty($event['attendee_status']);
    $needsPhoto = $event['ended'] && $event['attendee_status'] === 'attended' && $event['photo_status'] !== 'approved';
    ?>
    <article class="event-card">
        <div class="panel-card-header">
            <div>
                <span class="event-scope-label"><?= $event['scope'] === 'public' ? 'Genel Debi' : 'Mahalle Etkinliği' ?></span>
                <h2 class="section-title"><?= e($event['title']) ?></h2>
                <p><?= e($event['event_date']) ?> · <?= e(substr($event['start_time'], 0, 5)) ?> · <?= e($event['location']) ?></p>
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

        <?php if (!$isCreator && !$isAttendee && !$event['started'] && !$event['ended']): ?>
            <form method="post" class="event-action-row">
                <?= csrf_field() ?><input type="hidden" name="action" value="join_event"><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
                <button class="btn btn-primary btn-sm">Katılacağım</button>
            </form>
        <?php elseif (!$isCreator && !$isAttendee && $event['ended']): ?>
            <div class="event-attendance-locked">Etkinlik sona erdi; artık katılım kaydı alınamıyor.</div>
        <?php elseif ($isAttendee): ?>
            <div class="event-participation">
                <strong><?= $isCreator ? 'Etkinliği siz oluşturuyorsunuz; katılımınız otomatik kaydedildi.' : ($event['attendee_status'] === 'attended' ? 'Yoklamada katıldığınız işaretlendi.' : ($event['ended'] ? 'Yoklamada yok yazıldınız.' : 'Katılımınız kayıtlı, yoklama bekleniyor.')) ?></strong>
                <?php if ($needsPhoto): ?>
                    <form method="post" enctype="multipart/form-data" class="event-photo-form">
                        <?= csrf_field() ?><input type="hidden" name="action" value="upload_event_photo"><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
                        <input type="file" name="event_photo" accept="image/jpeg,image/png,image/webp" required>
                        <button class="btn btn-outline btn-sm">Fotoğraf yükle ve doğrula</button>
                    </form>
                <?php elseif ($event['attendee_status'] === 'attended' && !$event['ended']): ?>
                    <span class="form-help">Etkinlik bitince fotoğraf yükleyebilirsiniz.</span>
                <?php elseif ($event['photo_status'] === 'approved'): ?>
                    <span class="tag">Fotoğraf doğrulandı, puan verildi</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($isCreator && $event['started'] && !$event['ended']): ?>
            <form method="post" class="event-action-row">
                <?= csrf_field() ?><input type="hidden" name="action" value="finish_event"><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
                <button class="btn btn-primary btn-sm">Etkinliği bitir</button>
            </form>
            <form method="post" class="event-attendance">
                <?= csrf_field() ?><input type="hidden" name="action" value="mark_attendance"><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
                <h3>Yoklama</h3>
                <p class="form-help">Etkinlik başladı; yalnızca gerçekten gelenleri işaretleyin.</p>
                <?php foreach ($managedAttendees[(int) $event['id']] ?? [] as $attendee): ?>
                    <label><input type="checkbox" name="attendee_ids[]" value="<?= (int) $attendee['user_id'] ?>" <?= $attendee['user_id'] == $currentUser['id'] || $attendee['status'] === 'attended' ? 'checked' : '' ?> <?= $attendee['user_id'] == $currentUser['id'] ? 'disabled' : '' ?>> <?= e($attendee['full_name']) ?><?= $attendee['user_id'] == $currentUser['id'] ? ' (oluşturan)' : '' ?></label>
                <?php endforeach; ?>
                <button class="btn btn-outline btn-sm">Yoklamayı kaydet</button>
            </form>
        <?php endif; ?>
    </article>
    <?php
}

function render_event_section(string $title, array $events, array $currentUser, array $managedAttendees, string $emptyText): void
{
    ?>
    <section class="event-section">
        <div class="panel-card-header">
            <h2 class="section-title"><?= e($title) ?></h2>
            <span class="tag"><?= count($events) ?></span>
        </div>
        <?php if (!$events): ?>
            <div class="empty-inline"><?= e($emptyText) ?></div>
        <?php else: ?>
            <div class="event-list">
                <?php foreach ($events as $event) render_event_card($event, $currentUser, $managedAttendees); ?>
            </div>
        <?php endif; ?>
    </section>
    <?php
}

$pageTitle = 'Etkinlikler — Debi';
$activeNav = 'events';
require_once __DIR__ . '/includes/header.php';
?>
<div class="app-shell app-shell-2col">
    <?php require_once __DIR__ . '/includes/sidebar_left.php'; ?>
    <main class="main-column">
        <div class="page-heading-row">
            <div><h1 class="page-title">Etkinlikler</h1><p class="page-subtitle">Mahalledeki fiziksel çalışmalara katıl, katkını fotoğrafla doğrula.</p></div>
            <a href="create_event.php" class="btn btn-primary">Etkinlik oluştur</a>
        </div>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= e($success['message'] ?? $success) ?></div><?php endif; ?>

        <?php render_event_section('Öncelikli etkinlikler', $sections['active'], $currentUser, $managedAttendees, 'Şu anda devam eden veya işlem bekleyen etkinlik yok.'); ?>
        <?php render_event_section('Katılacağım etkinlikler', $sections['attending'], $currentUser, $managedAttendees, 'Henüz katılım kaydı yaptığın gelecek etkinlik yok.'); ?>
        <?php render_event_section('Katılabileceğim etkinlikler', $sections['available'], $currentUser, $managedAttendees, 'Katılabileceğin yeni etkinlik yok.'); ?>
        <?php render_event_section('Geçmiş etkinlikler', $sections['past'], $currentUser, $managedAttendees, 'Geçmiş etkinlik yok.'); ?>
    </main>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
