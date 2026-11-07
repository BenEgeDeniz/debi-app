<?php
/**
 * DEBI - Su Tüketimi, Sayaç Girişi ve Tasarruf Alışkanlıkları Sayfası
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/modules/water/water_service.php';

$currentUser = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('error', 'Güvenlik doğrulaması başarısız oldu.');
        redirect('water.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save_water_log') {
        $res = save_water_log($currentUser, $_POST);
        if ($res['ok']) {
            $ptsTxt = !empty($res['points_awarded']) ? ' (+' . (int) $res['points_awarded'] . ' DP kazandın!)' : '';
            set_flash('success', 'Sayaç kaydın güncellendi! Kişi başı günlük ortalaman: ' . number_format($res['daily_liters'], 1, ',', '.') . ' L' . $ptsTxt);
        } else {
            set_flash('error', $res['error']);
        }
        redirect('water.php');
    }

    if ($action === 'toggle_habit') {
        $habitId = (int) ($_POST['habit_id'] ?? 0);
        if ($habitId > 0) {
            $pts = toggle_household_habit((int) $currentUser['household_id'], $habitId, (int) $currentUser['id']);
            if ($pts > 0) {
                set_flash('success', 'Yeni tasarruf alışkanlığın eklendi! 🛠️ +' . $pts . ' Damla Puanı (DP) kazandın!');
            } else {
                set_flash('success', 'Hane tasarruf alışkanlığın güncellendi.');
            }
        }
        redirect('water.php#habits');
    }
}

$history = get_household_water_history((int) $currentUser['household_id']);
$habits  = get_saving_habits_with_status((int) $currentUser['household_id']);

$totalHabitSavings = 0;
foreach ($habits as $h) {
    if ((int) $h['is_active'] === 1) {
        $totalHabitSavings += (int) $h['estimated_monthly_liters'];
    }
}

$pageTitle = 'Su Tüketimi & Sayaç — Debi';
$activeNav = 'water';
require_once __DIR__ . '/includes/header.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/includes/sidebar_left.php'; ?>

    <main class="main-column">
        <!-- Aylık Su Tüketimi Giriş Kartı -->
        <section class="panel-card">
            <div class="panel-card-header">
                <div>
                    <div class="section-kicker">SAYAÇ &amp; FATURA KAYDI</div>
                    <h1 class="section-title">Aylık Su Tüketimi Girin</h1>
                </div>
                <span class="tag"><?= e($currentUser['unit_label']) ?> · <?= (int) $currentUser['resident_count'] ?> Kişi</span>
            </div>

            <p class="panel-desc">
                Su faturanızdaki veya sayacınızdaki aylık metreküp (<strong>1 m³ = 1.000 Litre</strong>) değerini girin.
                Sistem hanenizdeki kişi sayısına (<?= (int) $currentUser['resident_count'] ?> kişi) bölerek mahalleniz ve apartmanınızla adil kıyaslama yapar.
            </p>

            <form method="post" action="water.php" class="form-stack">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_water_log">

                <div class="form-grid-4">
                    <div class="form-group">
                        <label for="period_month">Fatura Ayı</label>
                        <select id="period_month" name="period_month">
                            <?php $curM = (int) date('n'); ?>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= $m === $curM ? 'selected' : '' ?>><?= e(turkish_month_name($m)) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="period_year">Yıl</label>
                        <select id="period_year" name="period_year">
                            <?php $curY = (int) date('Y'); ?>
                            <?php for ($y = $curY; $y >= 2024; $y--): ?>
                                <option value="<?= $y ?>"><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="consumption_m3">Tüketim (m³)</label>
                        <input type="number" step="0.1" min="0.5" max="500" id="consumption_m3" name="consumption_m3"
                               required placeholder="Örn: 9.5">
                    </div>

                    <div class="form-group">
                        <label for="bill_amount">Fatura Tutarı (TL - Opsiyonel)</label>
                        <input type="number" step="0.5" min="0" id="bill_amount" name="bill_amount"
                               placeholder="Örn: 340">
                    </div>
                </div>

                <div class="form-group">
                    <label for="notes">Bu Ay Uyguladığınız Tasarruf Notu (İsteğe bağlı)</label>
                    <input type="text" id="notes" name="notes" placeholder="Örn: Mutfak musluğuna perlatör taktık, 2 m³ düştü.">
                </div>

                <div class="composer-submit-row">
                    <label class="checkbox-inline">
                        <input type="checkbox" name="share_on_feed" value="1" checked>
                        <span>Bu kaydı mahalle akışında başarı olarak paylaş</span>
                    </label>

                    <button type="submit" class="btn btn-primary">💧 Kaydet ve +100 DP Kazan</button>
                </div>
            </form>
        </section>

        <!-- Geçmiş Su Tüketim Kayıtları ve Tüketim Trendi -->
        <section class="panel-card">
            <div class="panel-card-header">
                <h2 class="section-title">Hanenizin Su Tüketim Trendi &amp; Kayıtları</h2>
            </div>

            <?php if (empty($history)): ?>
                <div class="empty-inline">
                    Henüz aylık su tüketim kaydı girmediniz. Yukarıdaki formdan ilk kaydınızı ekleyebilirsiniz.
                </div>
            <?php else: ?>
                <!-- Saf CSS/HTML Aylık Tüketim Çubuk Grafiği (Trend) -->
                <?php
                // Kronolojik sıraya çevir
                $trendList = array_reverse($history);
                $maxDaily = 1;
                foreach ($trendList as $item) {
                    if ((float) $item['daily_liters_per_person'] > $maxDaily) {
                        $maxDaily = (float) $item['daily_liters_per_person'];
                    }
                }
                $maxScale = max(150, (int) ceil($maxDaily * 1.15));
                ?>
                <div class="trend-chart-box">
                    <div class="trend-chart-header">
                        <span class="trend-chart-title">Son Dönemler Kişi Başı Günlük Su Tüketimi (Litre)</span>
                        <span class="trend-target-legend">Tasarruf Eşiği: 100 L</span>
                    </div>

                    <div class="trend-bars-wrapper">
                        <?php foreach ($trendList as $tRow): ?>
                            <?php
                            $tDaily = (float) $tRow['daily_liters_per_person'];
                            $pctHeight = min(100, max(12, round(($tDaily / $maxScale) * 100)));
                            $barClass = $tDaily <= 100 ? 'bar-good' : ($tDaily <= 140 ? 'bar-warn' : 'bar-high');
                            $shortLabel = mb_substr(turkish_month_name((int) $tRow['period_month']), 0, 3, 'UTF-8') . " '" . substr((string) $tRow['period_year'], -2);
                            ?>
                            <div class="trend-bar-col">
                                <div class="trend-bar-val"><?= number_format($tDaily, 1, ',', '.') ?> L</div>
                                <div class="trend-bar-track">
                                    <div class="trend-bar-fill <?= $barClass ?>" style="height: <?= $pctHeight ?>%;"></div>
                                </div>
                                <div class="trend-bar-label"><?= e($shortLabel) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Dönem</th>
                                <th>Toplam (m³)</th>
                                <th>Kişi Sayısı</th>
                                <th>Kişi Başı Günlük</th>
                                <th>Fatura</th>
                                <th>Not</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $row): ?>
                                <?php $daily = (float) $row['daily_liters_per_person']; ?>
                                <tr>
                                    <td><strong><?= e(turkish_month_name((int) $row['period_month']) . ' ' . $row['period_year']) ?></strong></td>
                                    <td><?= number_format((float) $row['consumption_m3'], 1, ',', '.') ?> m³</td>
                                    <td><?= (int) $row['resident_count_snapshot'] ?> kişi</td>
                                    <td>
                                        <span class="metric-pill <?= $daily <= 110 ? 'pill-good' : 'pill-neutral' ?>">
                                            <?= number_format($daily, 1, ',', '.') ?> L / gün
                                        </span>
                                    </td>
                                    <td>
                                        <?= $row['bill_amount'] !== null ? number_format((float) $row['bill_amount'], 0, ',', '.') . ' TL' : '—' ?>
                                    </td>
                                    <td><?= e($row['notes'] ?? '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <!-- Hane Su Tasarrufu Alışkanlıkları -->
        <section class="panel-card" id="habits">
            <div class="panel-card-header">
                <div>
                    <h2 class="section-title">Evde Uyguladığımız Tasarruf Adımları</h2>
                </div>
                <span class="saved-water-pill">
                    Aylık Tahmini Korunan: <strong><?= number_format($totalHabitSavings, 0, ',', '.') ?> L</strong>
                </span>
            </div>
            <p class="panel-desc">
                Hanenizde düzenli olarak uyguladığınız su tasarrufu alışkanlıklarını işaretleyin:
            </p>

            <div class="habit-list">
                <?php foreach ($habits as $habit): ?>
                    <?php $active = (int) $habit['is_active'] === 1; ?>
                    <div class="habit-item <?= $active ? 'habit-active' : '' ?>">
                        <div class="habit-info">
                            <strong><?= e($habit['title']) ?></strong>
                            <p><?= e($habit['description']) ?></p>
                            <span class="habit-meta">Tahmini katkı: ~<?= number_format((int) $habit['estimated_monthly_liters'], 0, ',', '.') ?> L / ay</span>
                        </div>
                        <form method="post" action="water.php" class="habit-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle_habit">
                            <input type="hidden" name="habit_id" value="<?= (int) $habit['id'] ?>">
                            <button type="submit" class="btn <?= $active ? 'btn-primary' : 'btn-outline' ?> btn-sm">
                                <?= $active ? '✓ Uygulanıyor' : '+ Uygula (+40 DP)' ?>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <?php require_once __DIR__ . '/includes/sidebar_right.php'; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

