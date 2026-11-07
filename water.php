<?php
/**
 * DEBI - Yapay Zeka (Gemini) ile Su Faturası Okuma ve Tüketim Takibi
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/modules/water/water_service.php';
require_once __DIR__ . '/modules/water/bill_ai_service.php';

$currentUser = require_login();
$scanResult  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('error', 'Güvenlik doğrulaması başarısız oldu.');
        redirect('water.php');
    }

    $action = $_POST['action'] ?? '';

    // 1. Adım: Fatura fotoğrafını yükle ve Gemini AI ile verileri çıkar
    if ($action === 'scan_bill') {
        $file = $_FILES['bill_image'] ?? [];
        $res  = analyze_water_bill_image($file);
        if (!$res['ok']) {
            set_flash('error', $res['error']);
            redirect('water.php');
        }
        $scanResult = $res;
    }

    // 2. Adım: Kullanıcının "Evet, Doğru" diyerek onayladığı fatura verisini kaydet
    if ($action === 'confirm_bill') {
        $res = save_water_log($currentUser, $_POST);
        if ($res['ok']) {
            set_flash('success', 'Faturanız kaydedildi. Kişi başı günlük ortalamanız: ' . number_format($res['daily_liters'], 1, ',', '.') . ' L');
            redirect('water.php');
        } else {
            set_flash('error', $res['error']);
            $scanResult = [
                'ok'              => true,
                'ai_used'         => false,
                'bill_image_path' => $_POST['bill_image_path'] ?? '',
                'extracted'       => [
                    'provider_name'  => $_POST['provider_name'] ?? '',
                    'period_month'   => (int) ($_POST['period_month'] ?? date('n')),
                    'period_year'    => (int) ($_POST['period_year'] ?? date('Y')),
                    'consumption_m3' => $_POST['consumption_m3'] ?? '',
                    'bill_amount'    => $_POST['bill_amount'] ?? '',
                    'reading_days'   => (int) ($_POST['reading_days'] ?? 30),
                    'summary_text'   => 'Lütfen eksik veya hatalı alanı düzeltip tekrar onaylayın.',
                ],
            ];
        }
    }
}

$history = get_household_water_history((int) $currentUser['household_id']);

$pageTitle = 'Su Faturası Takibi — Debi';
$activeNav = 'water';
require_once __DIR__ . '/includes/header.php';
?>

<div class="app-shell app-shell-2col">
    <?php require_once __DIR__ . '/includes/sidebar_left.php'; ?>

    <main class="main-column">
        <?php if ($scanResult): ?>
            <?php
            $ext          = $scanResult['extracted'];
            $residentCnt  = max(1, (int) $currentUser['resident_count']);
            $initM3       = (float) ($ext['consumption_m3'] ?: 0);
            $initDays     = max(1, (int) ($ext['reading_days'] ?: 30));
            $previewDaily = $initM3 > 0 ? round(($initM3 * 1000) / ($initDays * $residentCnt), 1) : 0;
            $selectedFeed = $_POST['feed_scope'] ?? (!empty($currentUser['apartment_id']) ? 'apartment' : 'friends');
            ?>
            <!-- ADIM 2: "BU BİLGİLER DOĞRU MU?" ONAY KARTI -->
            <section class="panel-card bill-confirm-card">
                <div class="panel-card-header">
                    <div>
                        <div class="section-kicker">YAPAY ZEKA FATURA KONTROLÜ</div>
                        <h1 class="section-title">Faturanızdan Çıkarılan Bilgiler Doğru mu?</h1>
                    </div>
                    <span class="tag"><?= e($currentUser['unit_label']) ?> · <?= $residentCnt ?> Kişi</span>
                </div>

                <?php if (!empty($scanResult['api_key_missing'])): ?>
                    <div class="bill-ai-notice notice-warn">
                        <strong>Gemini API Anahtarı Bekleniyor:</strong>
                        <code>config/database.local.php</code> dosyasına <code>GEMINI_API_KEY</code> değerinizi girdiğinizde fatura fotoğrafları otomatik okunacaktır. Şimdilik faturanıza bakarak bilgileri aşağıdan onaylayabilirsiniz.
                    </div>
                <?php elseif (!empty($scanResult['ai_error'])): ?>
                    <div class="bill-ai-notice notice-warn">
                        <?= e($scanResult['ai_error']) ?>
                    </div>
                <?php else: ?>
                    <div class="bill-ai-notice notice-ok">
                        <strong>Yapay Zeka Özeti:</strong> <?= e($ext['summary_text']) ?>
                    </div>
                <?php endif; ?>

                <div class="bill-verify-layout">
                    <?php if (!empty($scanResult['bill_image_path'])): ?>
                        <div class="bill-preview-column">
                            <a href="<?= e($scanResult['bill_image_path']) ?>" target="_blank" title="Tam boyutta görüntüle">
                                <img src="<?= e($scanResult['bill_image_path']) ?>" alt="Yüklenen Su Faturası" class="bill-scanned-img">
                            </a>
                            <span class="field-hint">Büyütmek için faturaya tıklayın</span>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="water.php" class="form-stack bill-confirm-form" id="billConfirmForm" data-residents="<?= $residentCnt ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="confirm_bill">
                        <input type="hidden" name="bill_image_path" value="<?= e($scanResult['bill_image_path'] ?? '') ?>">

                        <div class="form-grid-3">
                            <div class="form-group">
                                <label for="provider_name">Su İdaresi / Kurum</label>
                                <input type="text" id="provider_name" name="provider_name"
                                       value="<?= e((string) $ext['provider_name']) ?>"
                                       placeholder="Örn: İSKİ, ASKİ, İZSU">
                            </div>

                            <div class="form-group">
                                <label for="period_month">Fatura Ayı</label>
                                <select id="period_month" name="period_month">
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?= $m ?>" <?= $m === (int) $ext['period_month'] ? 'selected' : '' ?>>
                                            <?= e(turkish_month_name($m)) ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="period_year">Fatura Yılı</label>
                                <select id="period_year" name="period_year">
                                    <?php $curY = (int) date('Y'); ?>
                                    <?php for ($y = $curY + 1; $y >= 2023; $y--): ?>
                                        <option value="<?= $y ?>" <?= $y === (int) $ext['period_year'] ? 'selected' : '' ?>><?= $y ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-grid-3">
                            <div class="form-group">
                                <label for="consumption_m3">Tüketim Miktarı (m³)</label>
                                <input type="number" step="0.01" min="0.5" max="500" id="consumption_m3" name="consumption_m3"
                                       required value="<?= e((string) $ext['consumption_m3']) ?>" placeholder="Örn: 11">
                            </div>

                            <div class="form-group">
                                <label for="reading_days">Okuma Gün Sayısı</label>
                                <input type="number" min="1" max="90" id="reading_days" name="reading_days"
                                       required value="<?= (int) $ext['reading_days'] ?>">
                            </div>

                            <div class="form-group">
                                <label for="bill_amount">Fatura Tutarı (TL)</label>
                                <input type="number" step="0.01" min="0" id="bill_amount" name="bill_amount"
                                       value="<?= e((string) $ext['bill_amount']) ?>" placeholder="Örn: 345.50">
                            </div>
                        </div>

                        <div class="bill-calc-preview">
                            <span>Hesaplanan Kişi Başı Günlük Tüketim (<?= $residentCnt ?> kişi):</span>
                            <strong id="liveDailyLiters"><?= $previewDaily > 0 ? number_format($previewDaily, 1, ',', '.') . ' L / gün' : '—' ?></strong>
                        </div>

                        <div class="form-grid-2">
                            <div class="form-group">
                                <label for="notes">Kısa Not (İsteğe bağlı)</label>
                                <input type="text" id="notes" name="notes" placeholder="Örn: Bu ay musluk başlıklarını değiştirdik.">
                            </div>

                            <div class="form-group">
                                <label for="feed_scope">Hangi Akışta Paylaşılsın?</label>
                                <select id="feed_scope" name="feed_scope">
                                    <option value="friends" <?= $selectedFeed === 'friends' ? 'selected' : '' ?>>Arkadaşlarım Akışında Paylaş</option>
                                    <?php if (!empty($currentUser['apartment_id'])): ?>
                                        <option value="apartment" <?= $selectedFeed === 'apartment' ? 'selected' : '' ?>>Apartmanım (<?= e($currentUser['apartment_name']) ?>) Akışında Paylaş</option>
                                    <?php endif; ?>
                                    <option value="public" <?= $selectedFeed === 'public' ? 'selected' : '' ?>>Genel Debi Akışında Paylaş</option>
                                    <option value="none" <?= $selectedFeed === 'none' ? 'selected' : '' ?>>Akışta Paylaşma (Sadece Kaydet)</option>
                                </select>
                            </div>
                        </div>

                        <div class="bill-confirm-actions">
                            <button type="submit" class="btn btn-primary">Evet, Doğru — Faturayı Kaydet</button>
                            <a href="water.php" class="btn btn-outline">İptal / Yeniden Yükle</a>
                        </div>
                    </form>
                </div>
            </section>
        <?php else: ?>
            <!-- ADIM 1: FATURA FOTOĞRAFI YÜKLEME KARTI -->
            <section class="panel-card">
                <div class="panel-card-header">
                    <div>
                        <div class="section-kicker">YAPAY ZEKA DESTEKLİ FATURA OKUMA</div>
                        <h1 class="section-title">Su Faturanızın Fotoğrafını Yükleyin</h1>
                    </div>
                    <span class="tag"><?= e($currentUser['unit_label']) ?> · <?= (int) $currentUser['resident_count'] ?> Kişi</span>
                </div>

                <p class="panel-desc">
                    Faturanızın fotoğrafını çekin veya seçin. Yapay zeka (Gemini) fatura dönemini, harcanan metreküpü (<strong>m³</strong>) ve fatura tutarını otomatik çıkarıp onayınıza sunar.
                </p>

                <form method="post" action="water.php" enctype="multipart/form-data" class="bill-upload-form" id="billScanForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="scan_bill">

                    <label for="bill_image_input" class="bill-dropzone" id="billDropzone">
                        <input type="file" id="bill_image_input" name="bill_image"
                               accept="image/jpeg,image/png,image/webp" capture="environment" required>
                        <div class="bill-dropzone-content" id="billDropzoneText">
                            <div class="bill-dropzone-icon">📷</div>
                            <strong>Su Faturası Fotoğrafı Seçin veya Çekin</strong>
                            <span>JPG, PNG veya WebP · Maksimum 8 MB</span>
                        </div>
                        <div class="bill-selected-preview" id="billSelectedPreview" hidden>
                            <img id="billPreviewImg" src="" alt="Seçilen fatura önizlemesi">
                            <span id="billPreviewName"></span>
                        </div>
                    </label>

                    <div class="composer-submit-row">
                        <div class="composer-bar-left">
                            <label for="initial_feed_scope" class="field-hint" style="font-weight: 700;">Paylaşım Akışı:</label>
                            <select id="initial_feed_scope" name="feed_scope" class="composer-scope-select">
                                <option value="friends">Arkadaşlarım</option>
                                <?php if (!empty($currentUser['apartment_id'])): ?>
                                    <option value="apartment" selected>Apartmanım (<?= e($currentUser['apartment_name']) ?>)</option>
                                <?php endif; ?>
                                <option value="public" <?= empty($currentUser['apartment_id']) ? 'selected' : '' ?>>Genel Debi</option>
                                <option value="none">Akışta Paylaşma</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary" id="billScanSubmitBtn">
                            Faturayı Yapay Zeka ile Okut
                        </button>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <!-- Geçmiş Su Faturaları ve Tüketim Grafiği -->
        <section class="panel-card">
            <div class="panel-card-header">
                <h2 class="section-title">Geçmiş Su Faturalarım</h2>
                <a href="analytics.php" class="btn btn-outline btn-sm">Su Analizi &amp; Debi Göleti &rarr;</a>
            </div>

            <?php if (empty($history)): ?>
                <div class="empty-inline">
                    Henüz kaydedilmiş bir su faturanız bulunmuyor. Yukarıdan ilk faturanızın fotoğrafını yükleyebilirsiniz.
                </div>
            <?php else: ?>
                <?php
                $trendList = array_reverse($history);
                $maxDaily  = 1;
                foreach ($trendList as $item) {
                    if ((float) $item['daily_liters_per_person'] > $maxDaily) {
                        $maxDaily = (float) $item['daily_liters_per_person'];
                    }
                }
                $maxScale = max(150, (int) ceil($maxDaily * 1.15));
                ?>
                <div class="trend-chart-box">
                    <div class="trend-chart-header">
                        <span class="trend-chart-title">Kişi Başı Günlük Tüketim (Litre)</span>
                        <span class="trend-target-legend">Hedef: 100 L / gün</span>
                    </div>

                    <div class="trend-bars-wrapper">
                        <?php foreach ($trendList as $tRow): ?>
                            <?php
                            $tDaily     = (float) $tRow['daily_liters_per_person'];
                            $pctHeight  = min(100, max(12, round(($tDaily / $maxScale) * 100)));
                            $barClass   = $tDaily <= 100 ? 'bar-good' : ($tDaily <= 140 ? 'bar-warn' : 'bar-high');
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
                                <th>Kurum</th>
                                <th>Tüketim</th>
                                <th>Kişi Başı Günlük</th>
                                <th>Tutar</th>
                                <th>Fatura Görseli</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $row): ?>
                                <?php $daily = (float) $row['daily_liters_per_person']; ?>
                                <tr>
                                    <td>
                                        <strong><?= e(turkish_month_name((int) $row['period_month']) . ' ' . $row['period_year']) ?></strong>
                                        <?php if (!empty($row['reading_days'])): ?>
                                            <div class="table-sub"><?= (int) $row['reading_days'] ?> gün</div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= !empty($row['provider_name']) ? e($row['provider_name']) : '—' ?></td>
                                    <td><strong><?= number_format((float) $row['consumption_m3'], 1, ',', '.') ?> m³</strong></td>
                                    <td>
                                        <span class="metric-pill <?= $daily <= 110 ? 'pill-good' : 'pill-neutral' ?>">
                                            <?= number_format($daily, 1, ',', '.') ?> L / gün
                                        </span>
                                    </td>
                                    <td>
                                        <?= $row['bill_amount'] !== null ? number_format((float) $row['bill_amount'], 2, ',', '.') . ' TL' : '—' ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['bill_image_path'])): ?>
                                            <a href="<?= e($row['bill_image_path']) ?>" target="_blank" class="link-subtle">Faturayı Gör</a>
                                        <?php else: ?>
                                            <span class="muted-text">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
