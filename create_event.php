<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/modules/events/event_ai_service.php';
require_once __DIR__ . '/modules/events/event_service.php';

$currentUser = require_login();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Güvenlik doğrulaması başarısız oldu.';
    } else {
        $result = create_event($currentUser, $_POST);
        if ($result['ok']) {
            set_flash(
                'success',
                'Etkinlik oluşturuldu. AI puanı: ' . $result['ai']['points'] . ' DP. Gerekçe: ' . $result['ai']['reason']
            );
            redirect('events.php');
        }
        $error = $result['error'];
    }
}

$pageTitle = 'Etkinlik Oluştur — Debi';
$activeNav = 'events';
require_once __DIR__ . '/includes/header.php';
?>
<div class="app-shell app-shell-2col">
    <?php require_once __DIR__ . '/includes/sidebar_left.php'; ?>
    <main class="main-column">
        <div class="page-heading-row">
            <div>
                <h1 class="page-title">Etkinlik Oluştur</h1>
                <p class="page-subtitle">Mahallende veya Genel Debi'de fiziksel bir çalışma başlat.</p>
            </div>
            <a href="events.php" class="btn btn-outline">Etkinliklere dön</a>
        </div>

        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

        <section class="panel-card">
            <form method="post" class="form-stack">
                <?= csrf_field() ?>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="event_title">Etkinlik başlığı</label>
                        <input id="event_title" name="title" required maxlength="160" value="<?= e($_POST['title'] ?? '') ?>" placeholder="Mahalle parkı temizliği">
                    </div>
                    <div class="form-group">
                        <label for="event_location">Buluşma / çalışma yeri</label>
                        <input id="event_location" name="location" required maxlength="255" value="<?= e($_POST['location'] ?? '') ?>" placeholder="Atatürk Parkı">
                    </div>
                </div>
                <div class="form-group">
                    <label for="event_description">Ne yapılacak?</label>
                    <textarea id="event_description" name="description" rows="5" required placeholder="Fiziksel işin kapsamını ve beklenen katkıyı anlat."><?= e($_POST['description'] ?? '') ?></textarea>
                </div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="event_date">Tarih</label>
                        <input id="event_date" type="date" name="event_date" required min="<?= date('Y-m-d') ?>" value="<?= e($_POST['event_date'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="start_time">Başlangıç saati</label>
                        <input id="start_time" type="time" name="start_time" required value="<?= e($_POST['start_time'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="event_scope">Görünürlük</label>
                    <select id="event_scope" name="scope">
                        <option value="neighborhood" <?= ($_POST['scope'] ?? '') !== 'public' ? 'selected' : '' ?>>Mahallem</option>
                        <option value="public" <?= ($_POST['scope'] ?? '') === 'public' ? 'selected' : '' ?>>Genel Debi</option>
                    </select>
                </div>
                <div class="event-info-box">
                    <strong>Puanlama ve doğrulama</strong>
                    <p>AI, etkinliğin fiziksel katkısına göre puanı ve gerekçesini belirler. Etkinlik sahibi bitirdiğinde yoklama kapanır. Puan için yoklamada katılmış olmak ve etkinlik fotoğrafının AI tarafından onaylanması gerekir.</p>
                </div>
                <button class="btn btn-primary" type="submit">Etkinliği AI ile değerlendir ve oluştur</button>
            </form>
        </section>
    </main>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
