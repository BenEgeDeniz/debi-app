<?php
/**
 * DEBI - Su Tüketim Analizi (m³ Çizgi Tablosu) & İnteraktif Debi Göleti (debi-pond) Widget'ı
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/modules/water/water_service.php';

$currentUser = require_login();
$analytics   = get_household_water_analytics($currentUser);

$series      = $analytics['series'];
$prevM3      = (float) $analytics['prev_m3'];
$currM3      = (float) $analytics['curr_m3'];
$savedLiters = (int) $analytics['saved_liters'];
$initStage   = $analytics['pond_stage'];

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

$pageTitle = 'Su Analizi & Debi Göleti — Debi';
$activeNav = 'analytics';
require_once __DIR__ . '/includes/header.php';
?>

<div class="app-shell app-shell-2col">
    <?php require_once __DIR__ . '/includes/sidebar_left.php'; ?>

    <main class="main-column">
        <!-- Üst Başlık ve Özet Metrikler -->
        <section class="panel-card">
            <div class="panel-card-header">
                <div>
                    <div class="section-kicker">AYLIK SU TÜKETİM ANALİZİ</div>
                    <h1 class="section-title">Zaman Çizelgesi (m³) &amp; Canlı Debi Göleti</h1>
                </div>
                <a href="water.php" class="btn btn-outline btn-sm">Fatura Yükle / Güncelle</a>
            </div>

            <?php if ($analytics['is_sample']): ?>
                <div class="bill-ai-notice notice-ok" style="margin-bottom: 14px;">
                    <strong>Bilgi:</strong>
                    <?= $analytics['has_real_logs']
                        ? 'Tek dönemlik faturanız bulunduğu için geçen ay referans değeriyle karşılaştırma gösteriliyor.'
                        : 'Henüz fatura veriniz olmadığı için örnek tüketim verileri gösteriliyor.' ?>
                    Aşağıdaki interaktif kontrollerle tüketim (m³) değişimini ve göletin yeşermesini canlı test edebilirsiniz.
                </div>
            <?php endif; ?>

            <div class="analytics-kpi-grid">
                <div class="analytics-kpi-box">
                    <span class="analytics-kpi-label">Geçen Ay Tüketim</span>
                    <strong class="analytics-kpi-val" id="kpiPrevM3"><?= number_format($prevM3, 1, ',', '.') ?> m³</strong>
                </div>
                <div class="analytics-kpi-box">
                    <span class="analytics-kpi-label">Bu Ay Tüketim</span>
                    <strong class="analytics-kpi-val" id="kpiCurrM3"><?= number_format($currM3, 1, ',', '.') ?> m³</strong>
                </div>
                <div class="analytics-kpi-box">
                    <span class="analytics-kpi-label">Aylık Değişim</span>
                    <strong class="analytics-kpi-val" id="kpiDiffM3">
                        <?= $prevM3 >= $currM3 ? '-' . number_format($prevM3 - $currM3, 1, ',', '.') . ' m³' : '+' . number_format($currM3 - $prevM3, 1, ',', '.') . ' m³' ?>
                    </strong>
                </div>
                <div class="analytics-kpi-box analytics-kpi-highlight">
                    <span class="analytics-kpi-label">Kurtarılan Su Miktarı</span>
                    <strong class="analytics-kpi-val" id="kpiSavedLiters"><?= number_format($savedLiters, 0, ',', '.') ?> L</strong>
                </div>
            </div>
        </section>

        <!-- 1. BÖLÜM: ZAMAN ÇİZELGESİ (xlab) VE KULLANILAN m³ (ylab) ÇİZGİ TABLOSU -->
        <section class="panel-card">
            <div class="panel-card-header">
                <div>
                    <h2 class="section-title">Aylık Su Tüketimi Çizgi Tablosu</h2>
                    <p class="panel-desc" style="margin-bottom: 0;">
                        <strong>X Ekseni (xlab):</strong> Aylık Zaman Çizelgesi (Geçen Ay &rarr; Bu Ay) &middot;
                        <strong>Y Ekseni (ylab):</strong> Kullanılan Su Miktarı (m³)
                    </p>
                </div>
                <span class="tag">1 m³ = 1.000 Litre</span>
            </div>

            <div class="line-chart-container">
                <svg id="waterLineChart" class="water-line-svg" viewBox="0 0 760 300" role="img" aria-label="Aylık su tüketimi m³ çizgi tablosu"></svg>
            </div>
        </section>

        <!-- 2. BÖLÜM: İNTERAKTİF DEBİ GÖLETİ (debi-pond) WIDGET'I -->
        <section class="panel-card pond-widget-card" id="pondWidgetSection">
            <div class="panel-card-header">
                <div>
                    <div class="section-kicker">CANLI EKOSİSTEM SİMÜLASYONU</div>
                    <h2 class="section-title">Kurtarılan Su ile Yeşeren Debi Göleti</h2>
                </div>
                <span class="pond-stage-pill" id="pondStageBadge">Aşama Yükleniyor...</span>
            </div>

            <p class="panel-desc">
                Harcanan su miktarı (<strong>m³</strong>) geçen aya göre azaldıkça aradaki fark <strong>kurtarılan litreye</strong> dönüşür. Üstten düşen her damla (💧) göletin su seviyesini yükseltir ve çevresini yeşertir.
            </p>

            <!-- Gölet Görsel Sahnesi + Üstten Damla Yağışı Alanı -->
            <div class="pond-stage-arena" id="pondStageArena">
                <!-- Üstten Gelen Damla Sembolleri Katmanı -->
                <div class="pond-drop-layer" id="pondDropLayer" aria-hidden="true"></div>

                <!-- Gölet Görseli (Kurumuş -> Yarı Dolu -> Tam Dolu img src geçişi) -->
                <div class="pond-visual-wrap">
                    <img id="pondStageImg"
                         src="<?= e($stageImageMap[$initStage] ?? $stageImageMap['dry']) ?>"
                         alt="Debi Göleti Durumu"
                         class="pond-stage-img">
                    <div class="pond-ripple-ring" id="pondRippleRing"></div>
                </div>

                <!-- Maskot Durum Balonu -->
                <div class="pond-mascot-companion">
                    <img id="pondMascotImg"
                         src="<?= e($mascotImageMap[$initStage] ?? $mascotImageMap['dry']) ?>"
                         alt="Debi Maskotu"
                         class="pond-mascot-img">
                    <div class="pond-mascot-speech">
                        <strong id="pondMascotTitle">Gölet Durumu</strong>
                        <span id="pondMascotDesc">Veriler hesaplanıyor...</span>
                    </div>
                </div>
            </div>

            <!-- Canlı Su Seviyesi Çubuğu ve 3 Aşama Göstergesi -->
            <div class="pond-meter-box">
                <div class="pond-meter-top">
                    <span>Gölet Canlılık &amp; Su Seviyesi</span>
                    <strong id="pondMeterLitersText"><?= number_format($savedLiters, 0, ',', '.') ?> / 3.000+ L Kurtarılan Su</strong>
                </div>
                <div class="pond-meter-track">
                    <div class="pond-meter-fill" id="pondMeterFill" style="width: 0%;"></div>
                </div>
                <div class="pond-stage-steps">
                    <div class="pond-step-item" data-stage-step="dry">
                        <span class="pond-step-dot">1</span>
                        <div>
                            <strong>Kurumuş Göl</strong>
                            <small>0 – 999 L Tasarruf</small>
                        </div>
                    </div>
                    <div class="pond-step-item" data-stage-step="half">
                        <span class="pond-step-dot">2</span>
                        <div>
                            <strong>Yarı Dolu Göl</strong>
                            <small>1.000 – 2.999 L Tasarruf</small>
                        </div>
                    </div>
                    <div class="pond-step-item" data-stage-step="full">
                        <span class="pond-step-dot">3</span>
                        <div>
                            <strong>Tam Dolu &amp; Yeşeren Göl</strong>
                            <small>3.000+ L Tasarruf</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- İnteraktif Tüketim & Damla Simülasyon Kontrolleri -->
            <div class="pond-controls-box">
                <div class="pond-sliders-grid">
                    <div class="pond-slider-group">
                        <div class="pond-slider-label">
                            <label for="simPrevM3Input">Geçen Ay Harcanan Su (m³)</label>
                            <strong id="simPrevM3Label"><?= number_format($prevM3, 1, ',', '.') ?> m³</strong>
                        </div>
                        <input type="range" id="simPrevM3Input" min="4" max="30" step="0.5" value="<?= e((string) $prevM3) ?>">
                    </div>

                    <div class="pond-slider-group">
                        <div class="pond-slider-label">
                            <label for="simCurrM3Input">Bu Ay Harcanan Su (m³) — <em>Azaltarak Göleti Doldurun</em></label>
                            <strong id="simCurrM3Label"><?= number_format($currM3, 1, ',', '.') ?> m³</strong>
                        </div>
                        <input type="range" id="simCurrM3Input" min="4" max="30" step="0.5" value="<?= e((string) $currM3) ?>">
                    </div>
                </div>

                <div class="pond-action-buttons">
                    <button type="button" class="btn btn-primary btn-sm" id="btnDropWater">
                        💧 Üstten Damla Gönder (+250 L Kurtar)
                    </button>
                    <button type="button" class="btn btn-outline btn-sm" id="btnAutoPlayStages">
                        ▶ 3 Aşamayı Canlı İzle
                    </button>
                    <button type="button" class="btn btn-outline btn-sm" id="btnResetRealData">
                        Verilerime Sıfırla
                    </button>
                </div>
            </div>
        </section>
    </main>
</div>

<script>
window.DEBI_ANALYTICS_DATA = <?= json_encode([
    'series' => $series,
    'prevM3' => $prevM3,
    'currM3' => $currM3,
    'images' => $stageImageMap,
    'mascots' => $mascotImageMap,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

