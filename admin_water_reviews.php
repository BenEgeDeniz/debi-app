<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/modules/water/water_service.php';

$currentUser = require_login();
$pageTitle = 'Fatura İncelemeleri — Debi';
$activeNav = '';
$error = null;
$success = get_flash();

if (empty($currentUser['is_admin'])) {
    http_response_code(403);
    $error = 'Bu sayfaya yalnızca admin kullanıcılar erişebilir.';
    $reviews = [];
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf()) {
            $error = 'Güvenlik doğrulaması başarısız oldu.';
        } else {
            $result = review_water_log(
                $currentUser,
                (int) ($_POST['log_id'] ?? 0),
                $_POST
            );
            if ($result['ok']) {
                $success = ['type' => 'success', 'message' => $result['message']];
            } else {
                $error = $result['error'];
            }
        }
    }
    $reviews = get_pending_water_log_reviews();
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="app-shell app-shell-2col">
    <?php require_once __DIR__ . '/includes/sidebar_left.php'; ?>
    <main class="main-column">
        <div class="page-heading-row">
            <div>
                <h1 class="page-title">Fatura incelemeleri</h1>
                <p class="page-subtitle">AI değerleri değiştirilen faturaları kontrol edip onaylayın.</p>
            </div>
        </div>

        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= e($success['message']) ?></div><?php endif; ?>

        <?php if (!$reviews): ?>
            <div class="empty-inline">Bekleyen fatura incelemesi yok.</div>
        <?php else: ?>
            <div class="admin-review-list">
                <?php foreach ($reviews as $review): ?>
                    <form method="post" class="panel-card admin-review-card">
                        <?= csrf_field() ?>
                        <input type="hidden" name="log_id" value="<?= (int) $review['id'] ?>">
                        <div class="panel-card-header">
                            <div>
                                <h2 class="section-title"><?= e($review['full_name']) ?> · <?= e($review['unit_label']) ?></h2>
                                <p class="form-help"><?= e(turkish_month_name((int) $review['period_month']) . ' ' . $review['period_year']) ?></p>
                            </div>
                            <span class="tag">İnceleme bekliyor</span>
                        </div>
                        <div class="form-grid-2">
                            <label class="form-field">
                                <span>Tüketim (m³)</span>
                                <input type="number" name="consumption_m3" step="0.01" min="0.01" max="500" value="<?= e((string) $review['consumption_m3']) ?>" required>
                            </label>
                            <label class="form-field">
                                <span>Okuma günü</span>
                                <input type="number" name="reading_days" min="1" max="90" value="<?= (int) $review['reading_days'] ?>" required>
                            </label>
                        </div>
                        <?php if (!empty($review['bill_image_path'])): ?>
                            <p><a class="link-subtle" href="<?= e($review['bill_image_path']) ?>" target="_blank" rel="noopener">Fatura görselini aç</a></p>
                        <?php endif; ?>
                        <label class="form-field">
                            <span>İnceleme notu</span>
                            <textarea name="review_note" rows="2" placeholder="Kontrol notu (opsiyonel)"></textarea>
                        </label>
                        <div class="event-action-row">
                            <button class="btn btn-primary btn-sm" name="decision" value="approve">Onayla ve puanla</button>
                            <button class="btn btn-outline btn-sm" name="decision" value="reject">Reddet</button>
                        </div>
                    </form>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
