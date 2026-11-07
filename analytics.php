<?php
/**
 * DEBI - Su Tüketim Analizi (m³ Çizgi Tablosu) & Debi Puan Göleti (1. Puanlama Yöntemi: Fatura Farkı -> Puan ve Gölet Birikimi)
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/modules/water/water_service.php';

$currentUser = require_login();
$analytics   = get_household_water_analytics($currentUser);

$series         = $analytics['series'] ?? [];
$yearlySeries   = $analytics['yearly_series'] ?? [];
$billCount      = (int) ($analytics['bill_count'] ?? count($series));
$hasComparison  = !empty($analytics['has_comparison']);
$prevLabel      = $analytics['prev_label'] ?? 'Önceki Fatura';
$currLabel      = $analytics['curr_label'] ?? 'Son Fatura';
$prevM3         = $analytics['prev_m3'];
$currM3         = $analytics['curr_m3'];
$diffM3         = (float) ($analytics['diff_m3'] ?? 0.0);
$savedM3        = (float) ($analytics['saved_m3'] ?? 0.0);
$savedLiters    = (int) ($analytics['saved_liters'] ?? 0);
$savingPct      = (float) ($analytics['saving_pct'] ?? 0.0);
$earnedPoints   = (int) ($analytics['earned_points'] ?? 0);
$walletPoints   = (int) ($analytics['user_wallet_points'] ?? 0);
$pondState     = $analytics['household_pond'] ?? [
    'total_drops'     => $walletPoints,
    'target_drops'    => 100,
    'doluluk_yuzdesi' => 0.0,
    'stage_title'     => 'Kurumuş Gölet',
];
$totalDrops    = (int) ($pondState['total_drops'] ?? $walletPoints);

$pageTitle = 'Su Tüketim Analizi — Debi';
$activeNav = 'analytics';
require_once __DIR__ . '/includes/header.php';
?>

<div class="app-shell app-shell-2col">
    <?php require_once __DIR__ . '/includes/sidebar_left.php'; ?>

    <main class="main-column">
        <!-- Üst Başlık ve Fatura Farkı / Damla Özet Metrikleri -->
        <section class="panel-card">
            <div class="panel-card-header">
                <h1 class="section-title">Su Analizi</h1>
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <a href="index.php#daireGoleti" class="drop-motivation-badge" title="Daire Göleti">
                        <span class="drop-badge-icon">💧</span>
                        <strong id="userWalletPointsText"><?= number_format($totalDrops, 0, ',', '.') ?> Damla</strong>
                    </a>
                    <a href="water.php" class="btn btn-primary btn-sm">📷 Fatura Yükle</a>
                </div>
            </div>

            <div class="analytics-kpi-grid">
                <div class="analytics-kpi-box">
                    <span class="analytics-kpi-label" id="kpiPrevLabel"><?= e($prevLabel) ?></span>
                    <strong class="analytics-kpi-val" id="kpiPrevM3">
                        <?= $prevM3 !== null ? number_format((float) $prevM3, 1, ',', '.') . ' m³' : '—' ?>
                    </strong>
                </div>
                <div class="analytics-kpi-box">
                    <span class="analytics-kpi-label" id="kpiCurrLabel"><?= e($currLabel) ?></span>
                    <strong class="analytics-kpi-val" id="kpiCurrM3">
                        <?= $currM3 !== null ? number_format((float) $currM3, 1, ',', '.') . ' m³' : '—' ?>
                    </strong>
                </div>
                <div class="analytics-kpi-box">
                    <span class="analytics-kpi-label" id="kpiDiffLabel">Tasarruf</span>
                    <strong class="analytics-kpi-val" id="kpiDiffM3">
                        <?php if (!$hasComparison): ?>
                            En az 2 fatura
                        <?php elseif ($savedM3 > 0): ?>
                            <?= number_format($savedM3, 1, ',', '.') . ' m³ (%' . number_format($savingPct, 1, ',', '.') . ')' ?>
                        <?php elseif ($diffM3 < 0): ?>
                            <?= '+' . number_format(abs($diffM3), 1, ',', '.') . ' m³ artış' ?>
                        <?php else: ?>
                            Değişim yok
                        <?php endif; ?>
                    </strong>
                </div>
                <div class="analytics-kpi-box analytics-kpi-highlight">
                    <span class="analytics-kpi-label">Son Fatura</span>
                    <strong class="analytics-kpi-val" id="kpiEarnedPoints">
                        <?= $earnedPoints > 0 ? '+' . $earnedPoints . ' Damla 💧' : '0 Damla' ?>
                    </strong>
                </div>
            </div>
        </section>

        <!-- ZAMAN (xlab) VE KULLANILAN m³ (ylab) ÇİZGİ TABLOSU -->
        <section class="panel-card">
            <div class="panel-card-header">
                <h2 class="section-title" id="chartSectionTitle">Tüketim Grafiği (m³)</h2>
                <div class="chart-period-toggle" role="tablist" aria-label="Zaman Aralığı Seçimi">
                    <button type="button" class="btn btn-primary btn-sm js-chart-mode active" data-chart-mode="monthly">
                        Aylık
                    </button>
                    <button type="button" class="btn btn-outline btn-sm js-chart-mode" data-chart-mode="yearly">
                        Yıllık
                    </button>
                </div>
            </div>

            <div class="line-chart-container">
                <svg id="waterLineChart" class="water-line-svg" viewBox="0 0 760 300" role="img" aria-label="Su tüketimi m³ çizgi tablosu"></svg>
            </div>
        </section>
    </main>
</div>

<script>
window.DEBI_ANALYTICS_DATA = <?= json_encode([
    'series'        => $series,
    'yearlySeries'  => $yearlySeries,
    'hasComparison' => $hasComparison,
    'prevLabel'     => $prevLabel,
    'currLabel'     => $currLabel,
    'prevM3'        => $prevM3,
    'currM3'        => $currM3,
    'savedM3'       => $savedM3,
    'savedLiters'   => $savedLiters,
    'savingPct'     => $savingPct,
    'earnedPoints'  => $earnedPoints,
    'walletPoints'  => $totalDrops,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
