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
$prevLabel      = $analytics['prev_label'] ?? 'Önceki Dönem Faturası';
$currLabel      = $analytics['curr_label'] ?? 'Son Dönem Faturası';
$prevM3         = $analytics['prev_m3'];
$currM3         = $analytics['curr_m3'];
$diffM3         = (float) ($analytics['diff_m3'] ?? 0.0);
$savedM3        = (float) ($analytics['saved_m3'] ?? 0.0);
$savedLiters    = (int) ($analytics['saved_liters'] ?? 0);
$savingPct      = (float) ($analytics['saving_pct'] ?? 0.0);
$earnedPoints   = (int) ($analytics['earned_points'] ?? 0);
$walletPoints   = (int) ($analytics['user_wallet_points'] ?? 0);
$collectivePond = $analytics['collective_pond'] ?? [
    'golet_mevcut_su_m3' => 0.0,
    'golet_mevcut_litre' => 0,
    'golet_toplam_puan'  => 0,
    'golet_hedef_m3'     => 500.0,
    'doluluk_yuzdesi'    => 0.0,
];
$initStage = $analytics['pond_stage'] ?? 'dry';

$stageImageMap = [
    'dry'  => brand_asset_path('pond_dry'),
    'half' => brand_asset_path('pond_half'),
    'full' => brand_asset_path('pond_full'),
];

$mascotImageMap = [
    'dry'  => brand_asset_path('thinking'),
    'half' => brand_asset_path('thumbsup'),
    'full' => brand_asset_path('happy'),
];

$pageTitle = 'Su Analizi & Debi Puan Göleti — Debi';
$activeNav = 'analytics';
require_once __DIR__ . '/includes/header.php';
?>

<div class="app-shell app-shell-2col">
    <?php require_once __DIR__ . '/includes/sidebar_left.php'; ?>

    <main class="main-column">
        <!-- Üst Başlık ve 1. Puanlama Yöntemi (Fatura Farkı -> Puan & Gölet) Özet Metrikleri -->
        <section class="panel-card">
            <div class="panel-card-header">
                <div>
                    <div class="section-kicker">SU TÜKETİM ANALİZİ &amp; 1. PUANLAMA YÖNTEMİ</div>
                    <h1 class="section-title">Fatura Farkı (m³) &amp; Debi Puan Göleti</h1>
                </div>
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <span class="metric-pill pill-good" title="Cüzdanınızdaki Toplam Debi Puanı">
                        💧 Cüzdanım: <strong id="userWalletPointsText" style="margin-left: 4px;"><?= number_format($walletPoints, 0, ',', '.') ?> Debi Puanı</strong>
                    </span>
                    <a href="water.php" class="btn btn-outline btn-sm">Fatura Yükle / Güncelle</a>
                </div>
            </div>

            <?php if ($billCount === 0): ?>
                <div class="bill-ai-notice notice-warn" style="margin-bottom: 14px;">
                    <strong>Henüz Fatura Verisi Yok:</strong>
                    Hanenize ait yüklenmiş su faturası bulunmuyor. <a href="water.php" style="font-weight: 800; text-decoration: underline;">Su Faturalarım</a> sayfasından faturanızı yüklediğinizde aylık/yıllık tüketim grafiğiniz burada oluşacaktır.
                </div>
            <?php elseif ($billCount === 1): ?>
                <div class="bill-ai-notice notice-ok" style="margin-bottom: 14px;">
                    <strong>Tek Dönem Verisi Mevcut:</strong>
                    Hanenize ait yalnızca <strong><?= e($series[0]['period_label'] ?? '') ?></strong> faturası yüklü. Aylar arası su tasarrufu farkı ve Debi Puanı hesabı için en az 2 farklı döneme ait fatura kaydı gereklidir.
                </div>
            <?php endif; ?>

            <div class="analytics-kpi-grid">
                <div class="analytics-kpi-box">
                    <span class="analytics-kpi-label" id="kpiPrevLabel"><?= e($prevLabel) ?></span>
                    <strong class="analytics-kpi-val" id="kpiPrevM3">
                        <?= $prevM3 !== null ? number_format((float) $prevM3, 1, ',', '.') . ' m³' : 'Veri Yok' ?>
                    </strong>
                </div>
                <div class="analytics-kpi-box">
                    <span class="analytics-kpi-label" id="kpiCurrLabel"><?= e($currLabel) ?></span>
                    <strong class="analytics-kpi-val" id="kpiCurrM3">
                        <?= $currM3 !== null ? number_format((float) $currM3, 1, ',', '.') . ' m³' : 'Veri Yok' ?>
                    </strong>
                </div>
                <div class="analytics-kpi-box">
                    <span class="analytics-kpi-label" id="kpiDiffLabel">Tasarruf Farkı &amp; Oranı</span>
                    <strong class="analytics-kpi-val" id="kpiDiffM3">
                        <?php if (!$hasComparison): ?>
                            En az 2 fatura gerekli
                        <?php elseif ($savedM3 > 0): ?>
                            <?= number_format($savedM3, 1, ',', '.') . ' m³ (' . number_format($savedLiters, 0, ',', '.') . ' L · %' . number_format($savingPct, 1, ',', '.') . ')' ?>
                        <?php elseif ($diffM3 < 0): ?>
                            <?= '+' . number_format(abs($diffM3), 1, ',', '.') . ' m³ artış (Tasarruf yok)' ?>
                        <?php else: ?>
                            0,0 m³ (Değişim yok)
                        <?php endif; ?>
                    </strong>
                </div>
                <div class="analytics-kpi-box analytics-kpi-highlight">
                    <span class="analytics-kpi-label">Kazanılan Debi Puanı (%10 Barajı)</span>
                    <strong class="analytics-kpi-val" id="kpiEarnedPoints">
                        <?= $earnedPoints > 0 ? '+' . $earnedPoints . ' Puan 💧' : '0 Puan' ?>
                    </strong>
                </div>
            </div>
        </section>

        <!-- 1. BÖLÜM: ZAMAN (xlab) VE KULLANILAN m³ (ylab) ÇİZGİ TABLOSU -->
        <section class="panel-card">
            <div class="panel-card-header">
                <div>
                    <h2 class="section-title" id="chartSectionTitle">Su Tüketimi Çizgi Tablosu</h2>
                </div>
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

        <!-- 2. BÖLÜM: İNTERAKTİF DEBİ PUAN GÖLETİ (KOLEKTİF HAVUZ) WIDGET'I -->
        <section class="panel-card pond-widget-card" id="pondWidgetSection">
            <div class="panel-card-header">
                <div>
                    <h2 class="section-title">Debi Puan Göleti (Ortak Kolektif Havuz)</h2>
                </div>
                <span class="pond-stage-pill" id="pondPointsBadge">
                    💧 Gölet Puanı: <strong id="pondTotalPointsVal" style="margin-left: 4px;"><?= number_format((int) $collectivePond['golet_toplam_puan'], 0, ',', '.') ?> Puan</strong>
                </span>
            </div>

            <p class="panel-desc">
                Önceki faturanıza göre su tüketiminiz (<strong>m³</strong>) azaldığında kurtarılan su (<strong>1 m³ = 1.000 L</strong>) ve <strong>%10 tasarruf barajını</strong> geçtiğinizde kazandığınız <strong>+10 Debi Puanı</strong> doğrudan ortak <strong>Debi Puan Göleti</strong> sayacına eklenir.
            </p>

            <!-- Ortak Gölet Kapasite ve Doluluk Sayacı (Hedef: 500 m³) -->
            <div class="pond-meter-box" style="margin-top: 8px; margin-bottom: 14px;">
                <div class="pond-meter-top">
                    <span>
                        Ortak Gölet Birikimi:
                        <strong id="pondCurrentM3Text"><?= number_format((float) $collectivePond['golet_mevcut_su_m3'], 2, ',', '.') ?> m³</strong>
                        (<span id="pondCurrentLitersText"><?= number_format((int) $collectivePond['golet_mevcut_litre'], 0, ',', '.') ?> L</span>)
                        / Hedef: <strong id="pondTargetM3Text"><?= number_format((float) $collectivePond['golet_hedef_m3'], 0, ',', '.') ?> m³</strong>
                    </span>
                    <strong id="pondFillPctText">%<?= number_format((float) $collectivePond['doluluk_yuzdesi'], 1, ',', '.') ?> Doluluk</strong>
                </div>
                <div class="pond-meter-track">
                    <div class="pond-meter-fill" id="pondMeterFill" style="width: <?= min(100, max(2, (float) $collectivePond['doluluk_yuzdesi'])) ?>%;"></div>
                </div>
            </div>

            <!-- Gölet Görsel Sahnesi + Üstten Damla Yağışı Alanı -->
            <div class="pond-stage-arena" id="pondStageArena">
                <!-- Üstten Gelen Damla Sembolleri Katmanı -->
                <div class="pond-drop-layer" id="pondDropLayer" aria-hidden="true"></div>

                <!-- Gölet Görseli (Kurumuş -> Yarı Dolu -> Tam Dolu img src geçişi) -->
                <div class="pond-visual-wrap">
                    <img id="pondStageImg"
                         src="<?= e($stageImageMap[$initStage] ?? $stageImageMap['dry']) ?>"
                         alt="Debi Puan Göleti Durumu"
                         class="pond-stage-img">
                    <div class="pond-ripple-ring" id="pondRippleRing"></div>
                </div>
            </div>
        </section>
    </main>
</div>

<script>
window.DEBI_ANALYTICS_DATA = <?= json_encode([
    'series'         => $series,
    'yearlySeries'   => $yearlySeries,
    'hasComparison'  => $hasComparison,
    'prevLabel'      => $prevLabel,
    'currLabel'      => $currLabel,
    'prevM3'         => $prevM3,
    'currM3'         => $currM3,
    'savedM3'        => $savedM3,
    'savedLiters'    => $savedLiters,
    'savingPct'      => $savingPct,
    'earnedPoints'   => $earnedPoints,
    'walletPoints'   => $walletPoints,
    'collectivePond' => $collectivePond,
    'images'         => $stageImageMap,
    'mascots'        => $mascotImageMap,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
