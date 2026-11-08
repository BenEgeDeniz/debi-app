<?php
/**
 * DEBI - Yapay Zeka (Gemini) ile Su Faturası Okuma ve Tüketim Takibi
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/modules/water/water_service.php';
require_once __DIR__ . '/modules/water/bill_ai_service.php';
require_once __DIR__ . '/modules/water/invoice_storage.php';

$currentUser     = require_login();
$demoMode        = isset($_GET['demo']) || isset($_POST['demo']);
$waterRedirect   = $demoMode ? 'water.php?demo' : 'water.php';
$scanResult      = null;
$pondCelebration = $_SESSION['pond_celebration'] ?? null;
unset($_SESSION['pond_celebration']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('error', 'Güvenlik doğrulaması başarısız oldu.');
        redirect($waterRedirect);
    }

    $action = $_POST['action'] ?? '';

    // 1. Adım: Fatura fotoğrafını yükle ve Gemini AI ile verileri çıkar
    if ($action === 'scan_bill') {
        $file = $_FILES['bill_image'] ?? [];
        $res  = analyze_water_bill_image($file);
        if (!$res['ok']) {
            set_flash('error', $res['error']);
            redirect($waterRedirect);
        }
        $allowedPeriod = get_next_water_period($currentUser);
        $scannedPeriod = ((int) ($res['extracted']['period_year'] ?? 0) * 12)
            + (int) ($res['extracted']['period_month'] ?? 0);
        $allowedPeriodNumber = ($allowedPeriod['year'] * 12) + $allowedPeriod['month'];
        if (!$demoMode && $scannedPeriod > 0 && $scannedPeriod !== $allowedPeriodNumber) {
            set_flash('error', 'Bu fatura dönemi yükleme sırasına uygun değil. Yalnızca '
                . turkish_month_name($allowedPeriod['month']) . ' ' . $allowedPeriod['year']
                . ' faturası yüklenebilir.');
            redirect($waterRedirect);
        }
        $scanResult = $res;
        $_SESSION['pending_invoice_reference'] = $res['bill_image_path'] ?? '';
        set_flash('success', 'Fatura okundu. Bilgileri kontrol edip onaylayın.');
        if (!empty($res['ai_used'])) {
            $scanResult['ai_approved_notice'] = 'Fatura doğrulandı. Görsel ve bilgiler uygun bulundu; şimdi değerleri kontrol edip onaylayın.';
        }
        $_SESSION['pending_ai_scan'] = [
            'ai_used'        => !empty($res['ai_used']),
            'period_month'   => (int) ($res['extracted']['period_month'] ?? 0),
            'period_year'    => (int) ($res['extracted']['period_year'] ?? 0),
            'consumption_m3' => round((float) str_replace(',', '.', (string) ($res['extracted']['consumption_m3'] ?? '0')), 2),
            'reading_days'   => (int) ($res['extracted']['reading_days'] ?? 30),
            'bill_amount'    => trim((string) ($res['extracted']['bill_amount'] ?? '')),
            'provider_name'  => trim((string) ($res['extracted']['provider_name'] ?? '')),
        ];
    }

    // 2. Adım: Kullanıcının "Evet, Doğru" diyerek onayladığı fatura verisini kaydet
    if ($action === 'confirm_bill') {
        $householdId  = (int) ($currentUser['household_id'] ?? 0);
        $pondBefore   = get_household_pond_state($householdId);
        $aiScan       = $_SESSION['pending_ai_scan'] ?? null;
        unset($_SESSION['pending_ai_scan']);

        $aiUnmodified = false;
        if (!empty($aiScan) && !empty($aiScan['ai_used'])) {
            $subMonth    = (int) ($_POST['period_month'] ?? 0);
            $subYear     = (int) ($_POST['period_year'] ?? 0);
            $subM3       = round((float) str_replace(',', '.', trim((string) ($_POST['consumption_m3'] ?? '0'))), 2);
            $subDays     = (int) ($_POST['reading_days'] ?? 30);
            $subBillRaw  = trim((string) ($_POST['bill_amount'] ?? ''));
            $subProvider = trim((string) ($_POST['provider_name'] ?? ''));
            $aiBillRaw   = trim((string) ($aiScan['bill_amount'] ?? ''));
            $aiProvider  = trim((string) ($aiScan['provider_name'] ?? ''));
            $billMatches = ($aiBillRaw === '' && $subBillRaw === '') ||
                (round((float) str_replace(',', '.', $subBillRaw), 2) === round((float) str_replace(',', '.', $aiBillRaw), 2));

            if (
                $subMonth === (int) $aiScan['period_month'] &&
                $subYear === (int) $aiScan['period_year'] &&
                abs($subM3 - (float) $aiScan['consumption_m3']) < 0.001 &&
                $subDays === (int) $aiScan['reading_days'] &&
                $billMatches &&
                $subProvider === $aiProvider
            ) {
                $aiUnmodified = true;
            }
        }

        $_POST['ai_verified'] = $aiUnmodified ? 1 : 0;
        $_POST['ai_used'] = !empty($aiScan['ai_used']) ? 1 : 0;
        if ($demoMode) {
            $_POST['demo'] = 1;
        }
        $res = save_water_log($currentUser, $_POST);

        if ($res['ok']) {
            $pondAfter = get_household_pond_state($householdId);

            $pointsAwarded = (int) ($res['points_awarded'] ?? 0);
            $dropsBefore = (int) ($pondBefore['total_drops'] ?? 0);
            $dropsAfter  = (int) ($pondAfter['total_drops'] ?? $dropsBefore);
            $earnedDrops = max($pointsAwarded, $dropsAfter - $dropsBefore);
            if ($earnedDrops > 0) {
                set_flash('success', 'Fatura kaydedildi · +' . $earnedDrops . ' Damla');
                $_SESSION['pond_celebration'] = [
                    'earned_drops'    => $earnedDrops,
                    'drop_reasons'    => !empty($res['drop_reasons']) ? $res['drop_reasons'] : ['💧 Fatura kaydı'],
                    'drops_before'    => $dropsBefore,
                    'drops_after'     => $dropsAfter,
                    'target_drops'    => (int) ($pondAfter['target_drops'] ?? 100),
                    'fill_before'     => (float) ($pondBefore['doluluk_yuzdesi'] ?? 0.0),
                    'fill_after'      => (float) ($pondAfter['doluluk_yuzdesi'] ?? 10.0),
                    'stage_before'    => $pondBefore['stage'] ?? 'dry',
                    'stage_after'     => $pondAfter['stage'] ?? 'dry',
                    'stage_title'     => $pondAfter['stage_title'] ?? 'Kurumuş Gölet',
                    'next_stage_hint' => $pondAfter['next_stage_hint'] ?? '',
                    'period_label'    => $res['period_label'] ?? '',
                    'consumption_m3'  => (float) ($res['consumption_m3'] ?? 0),
                    'saved_liters'    => (int) ($res['saved_liters'] ?? 0),
                    'unit_label'      => format_unit_label($currentUser['unit_label'] ?? '', $currentUser['housing_type'] ?? 'apartment_unit'),
                ];
            } else {
                $msg = 'Fatura kaydedildi (' . number_format($res['daily_liters'], 1, ',', '.') . ' L/gün).';
                if (!empty($res['points_awarded']) && $res['points_awarded'] > 0) {
                    $msg .= ' +' . (int) $res['points_awarded'] . ' Damla 💧';
                }
                set_flash('success', $msg);
            }
            redirect($waterRedirect);
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

$history   = get_household_water_history((int) $currentUser['household_id']);
$nextWaterPeriod = get_next_water_period($currentUser);

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
            $aiWasUsed    = !empty($scanResult['ai_used']);
            ?>
            <!-- ADIM 2: "BU BİLGİLER DOĞRU MU?" ONAY KARTI -->
            <section class="panel-card bill-confirm-card">
                <div class="panel-card-header">
                    <h1 class="section-title">Fatura Bilgilerini Onayla</h1>
                    <span class="tag"><?= e($currentUser['unit_label']) ?> · <?= $residentCnt ?> Kişi</span>
                </div>

                <?php if (!empty($scanResult['api_key_missing'])): ?>
                    <div class="bill-ai-notice notice-warn">
                        <code>GEMINI_API_KEY</code> tanımlı değil; bilgileri elle girebilirsiniz.
                    </div>
                <?php elseif (!empty($scanResult['ai_error'])): ?>
                    <div class="bill-ai-notice notice-warn">
                        <?= e($scanResult['ai_error']) ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($scanResult['ai_approved_notice'])): ?>
                    <div class="bill-ai-notice notice-ok">
                        <?= e($scanResult['ai_approved_notice']) ?>
                    </div>
                <?php endif; ?>

                <?php if ($aiWasUsed): ?>
                    <div class="ai-verify-reward-banner" id="aiVerifyRewardBanner">
                        <span class="ai-verify-icon" id="aiVerifyIcon">✨</span>
                        <div class="ai-verify-text" id="aiVerifyText">
                            <strong>AI okudu · Değerleri değiştirmezsen otomatik puanlanır</strong>
                            <small>Değişiklik yaparsan fatura admin incelemesine gider ve inceleme bitene kadar puan verilmez.</small>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="bill-points-rule-box">
                    <strong>Fatura puanı nasıl hesaplanır?</strong>
                    <span>İlk fatura: +10 puan · Aynı tüketim: +10 puan · Tüketim artışı: +2 puan · Tüketim azalması: azalan ton farkı × 10</span>
                    <small>Verilen puanlar hesabında kalıcıdır; fatura kaydı sonradan düzenlense veya silinse geri alınmaz.</small>
                </div>

                <div class="bill-verify-layout">
                    <?php if (!empty($scanResult['bill_image_path'])): ?>
                        <div class="bill-preview-column">
                            <a href="<?= e($scanResult['bill_image_path']) ?>" target="_blank" title="Büyüt">
                                <img src="<?= e($scanResult['bill_image_path']) ?>" alt="Su Faturası" class="bill-scanned-img">
                            </a>
                        </div>
                    <?php endif; ?>

                            <form method="post" action="water.php" class="form-stack bill-confirm-form" id="billConfirmForm"
                          data-residents="<?= $residentCnt ?>"
                          data-ai-used="<?= $aiWasUsed ? '1' : '0' ?>"
                          data-ai-month="<?= (int) $ext['period_month'] ?>"
                          data-ai-year="<?= (int) $ext['period_year'] ?>"
                          data-ai-m3="<?= e((string) $ext['consumption_m3']) ?>"
                          data-ai-days="<?= (int) $ext['reading_days'] ?>"
                          data-ai-provider="<?= e((string) $ext['provider_name']) ?>"
                          data-ai-bill="<?= e((string) $ext['bill_amount']) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="confirm_bill">
                        <?php if ($demoMode): ?><input type="hidden" name="demo" value="1"><?php endif; ?>
                        <input type="hidden" name="bill_image_path" value="<?= e($scanResult['bill_image_path'] ?? '') ?>">

                        <div class="form-grid-3">
                            <div class="form-group">
                                <label for="provider_name">Kurum</label>
                                <input type="text" id="provider_name" name="provider_name"
                                       value="<?= e((string) $ext['provider_name']) ?>"
                                       placeholder="İSKİ, ASKİ, İZSU">
                            </div>

                            <div class="form-group">
                                <label for="period_month">Ay</label>
                                <select id="period_month" name="period_month">
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <?php if (!$demoMode && $m !== $nextWaterPeriod['month']): continue; endif; ?>
                                        <option value="<?= $m ?>" <?= $m === (int) $ext['period_month'] ? 'selected' : '' ?>>
                                            <?= e(turkish_month_name($m)) ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="period_year">Yıl</label>
                                <select id="period_year" name="period_year">
                                    <?php if ($demoMode): ?>
                                        <?php for ($y = 2020; $y <= 2035; $y++): ?>
                                            <option value="<?= $y ?>" <?= $y === (int) ($ext['period_year'] ?: $nextWaterPeriod['year']) ? 'selected' : '' ?>><?= $y ?></option>
                                        <?php endfor; ?>
                                    <?php else: ?>
                                        <option value="<?= $nextWaterPeriod['year'] ?>" selected><?= $nextWaterPeriod['year'] ?></option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-grid-3">
                            <div class="form-group">
                                <label for="consumption_m3">Tüketim (m³)</label>
                                <input type="number" step="0.01" min="0.5" max="500" id="consumption_m3" name="consumption_m3"
                                       required value="<?= e((string) $ext['consumption_m3']) ?>" placeholder="11">
                            </div>

                            <div class="form-group">
                                <label for="reading_days">Gün</label>
                                <input type="number" min="1" max="90" id="reading_days" name="reading_days"
                                       required value="<?= (int) $ext['reading_days'] ?>">
                            </div>

                            <div class="form-group">
                                <label for="bill_amount">Tutar (TL)</label>
                                <input type="number" step="0.01" min="0" id="bill_amount" name="bill_amount"
                                       value="<?= e((string) $ext['bill_amount']) ?>" placeholder="345.50">
                            </div>
                        </div>

                        <div class="bill-calc-preview">
                            <span>Kişi Başı Günlük (<?= $residentCnt ?> kişi):</span>
                            <strong id="liveDailyLiters"><?= $previewDaily > 0 ? number_format($previewDaily, 1, ',', '.') . ' L / gün' : '—' ?></strong>
                        </div>

                        <div class="form-grid-2">
                            <div class="form-group">
                                <label for="notes">Not (İsteğe bağlı)</label>
                                <input type="text" id="notes" name="notes" placeholder="Kısa not...">
                            </div>

                            <div class="form-group">
                                <label for="feed_scope">Paylaşım</label>
                                <select id="feed_scope" name="feed_scope">
                                    <option value="friends" <?= $selectedFeed === 'friends' ? 'selected' : '' ?>>Arkadaşlar</option>
                                    <?php if (!empty($currentUser['apartment_id'])): ?>
                                        <option value="apartment" <?= $selectedFeed === 'apartment' ? 'selected' : '' ?>><?= e($currentUser['apartment_name']) ?></option>
                                    <?php endif; ?>
                                    <option value="public" <?= $selectedFeed === 'public' ? 'selected' : '' ?>>Genel</option>
                                    <option value="none" <?= $selectedFeed === 'none' ? 'selected' : '' ?>>Paylaşma</option>
                                </select>
                            </div>
                        </div>

                        <div class="bill-confirm-actions">
                            <button type="submit" class="btn btn-primary" id="billConfirmSubmitBtn">
                                <?= $aiWasUsed ? '💧 Onayla &amp; Damla Kazan' : 'Kaydet' ?>
                            </button>
                            <a href="water.php" class="btn btn-outline">İptal</a>
                        </div>
                    </form>
                </div>
            </section>
        <?php else: ?>
            <!-- ADIM 1: FATURA FOTOĞRAFI YÜKLEME KARTI -->
            <section class="panel-card">
                <div class="panel-card-header">
                    <h1 class="section-title">Fatura Yükle</h1>
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <span class="tag"><?= e($currentUser['unit_label']) ?> · <?= (int) $currentUser['resident_count'] ?> kişi</span>
                    </div>
                </div>

                <form method="post" action="water.php" enctype="multipart/form-data" class="bill-upload-form" id="billScanForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="scan_bill">
                    <?php if ($demoMode): ?><input type="hidden" name="demo" value="1"><?php endif; ?>

                    <label for="bill_image_input" class="bill-dropzone" id="billDropzone">
                        <input type="file" id="bill_image_input" name="bill_image" class="bill-file-input"
                               accept="image/jpeg,image/png,image/webp" capture="environment" required>
                        <div class="bill-dropzone-content" id="billDropzoneText">
                            <svg class="bill-dropzone-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="3" y="5" width="18" height="14" rx="2"/>
                                <path d="M8 5l1.5-2h5L16 5"/>
                                <circle cx="12" cy="12" r="3.5"/>
                            </svg>
                            <strong>Fatura Fotoğrafı Seç</strong>
                            <span><?= $demoMode ? 'Demo modu: herhangi bir fatura dönemi seçilebilir' : e(turkish_month_name($nextWaterPeriod['month']) . ' ' . $nextWaterPeriod['year']) . ' faturası · aylar sırayla yüklenir' ?></span>
                        </div>
                        <div class="bill-selected-preview" id="billSelectedPreview" hidden>
                            <img id="billPreviewImg" src="" alt="">
                            <span id="billPreviewName"></span>
                        </div>
                    </label>

                    <div class="composer-submit-row">
                        <div class="composer-bar-left">
                            <label for="initial_feed_scope" class="field-hint" style="font-weight: 700;">Paylaşım:</label>
                            <select id="initial_feed_scope" name="feed_scope" class="composer-scope-select">
                                <option value="friends">Arkadaşlar</option>
                                <?php if (!empty($currentUser['apartment_id'])): ?>
                                    <option value="apartment" selected><?= e($currentUser['apartment_name']) ?></option>
                                <?php endif; ?>
                                <option value="public" <?= empty($currentUser['apartment_id']) ? 'selected' : '' ?>>Genel</option>
                                <option value="none">Paylaşma</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary" id="billScanSubmitBtn">
                            ✨ Okut
                        </button>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <!-- Geçmiş Su Faturaları ve Tüketim Grafiği -->
        <section class="panel-card">
            <div class="panel-card-header">
                <h2 class="section-title">Faturalarım</h2>
                <a href="analytics.php" class="btn btn-outline btn-sm">Analiz &rarr;</a>
            </div>

            <?php if (empty($history)): ?>
                <div class="empty-inline">
                    Henüz fatura yüklenmedi.
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
                                <th>m³</th>
                                <th>Günlük</th>
                                <th>Puan</th>
                                <th>Tutar</th>
                                <th>Görsel</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $row): ?>
                                <?php
                                $daily    = (float) $row['daily_liters_per_person'];
                                $rowDrops = (int) ($row['earned_points'] ?? 0);
                                $rowAiVer = (int) ($row['ai_verified'] ?? 0) === 1;
                                $reviewPending = ($row['review_status'] ?? 'approved') === 'pending';
                                ?>
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
                                        <?php if ($reviewPending): ?>
                                            <span class="tag review-pending-tag">Admin incelemesi bekliyor · puan beklemede</span>
                                        <?php elseif ($rowDrops > 0): ?>
                                            <span class="drop-motivation-badge" style="padding: 3px 9px; font-size: 0.78rem;">
                                                💧 +<?= $rowDrops ?> Puan
                                            </span>
                                            <?php if ($rowAiVer): ?>
                                                <div class="table-sub" style="color: var(--brand-primary-dark); font-weight: 700;">✨ AI Onaylı</div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="muted-text">0 Damla</span>
                                        <?php endif; ?>
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

<?php if (!empty($pondCelebration)): ?>
    <?php
    $celStageMap = [
        'dry'  => brand_asset_path('pond_dry'),
        'half' => brand_asset_path('pond_half'),
        'full' => brand_asset_path('pond_full'),
    ];
    $celInitStage  = $pondCelebration['stage_before'] ?? $pondCelebration['stage_after'] ?? 'dry';
    $celFinalStage = $pondCelebration['stage_after'] ?? 'dry';
    ?>
    <!-- EKRANI KARARTIP GERÇEK DAİRE GÖLETİNE DAMLA YAĞDIRAN KUTLAMA MODALI -->
    <div class="pond-celebration-overlay" id="pondCelebrationOverlay" role="dialog" aria-modal="true" aria-labelledby="pondCelTitle">
        <div class="pond-celebration-card">
            <h2 class="pond-cel-title" id="pondCelTitle">
                +<?= (int) $pondCelebration['earned_drops'] ?> Damla! 💧
            </h2>

            <?php if (!empty($pondCelebration['drop_reasons'])): ?>
                <div class="pond-cel-reasons" style="margin-top: 8px;">
                    <?php foreach ($pondCelebration['drop_reasons'] as $reasonText): ?>
                        <span class="pond-cel-reason-pill"><?= e($reasonText) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Gerçek Gölet Sahnesi + Yağmur Katmanı -->
            <div class="pond-cel-arena" id="pondCelArena">
                <div class="pond-cel-drop-layer" id="pondCelDropLayer" aria-hidden="true"></div>
                <div class="pond-cel-visual-wrap">
                    <img id="pondCelStageImg"
                         src="<?= e($celStageMap[$celInitStage] ?? $celStageMap['dry']) ?>"
                         alt="Daire Göleti"
                         class="pond-cel-stage-img">
                    <div class="pond-ripple-ring" id="pondCelRippleRing"></div>
                </div>
            </div>

            <!-- Canlı Damla Sayacı & Gölet Doluluk Çubuğu -->
            <div class="pond-cel-meter-box">
                <div class="pond-cel-meter-top">
                    <span>
                        💧 <strong id="pondCelDropsCounter"><?= (int) $pondCelebration['drops_before'] ?></strong>
                        / <?= (int) $pondCelebration['target_drops'] ?> Damla
                    </span>
                    <strong id="pondCelFillPctText">%<?= number_format((float) $pondCelebration['fill_before'], 0, ',', '.') ?></strong>
                </div>
                <div class="pond-meter-track" style="height: 12px;">
                    <div class="pond-meter-fill" id="pondCelMeterFill" style="width: <?= min(100, max(3, (float) $pondCelebration['fill_before'])) ?>%;"></div>
                </div>
            </div>

            <div class="pond-cel-actions">
                <a href="index.php#daireGoleti" class="btn btn-primary btn-sm">Gölete Git</a>
                <button type="button" class="btn btn-outline btn-sm" id="btnClosePondCelebration">Kapat</button>
            </div>
        </div>
    </div>

    <script>
    window.DEBI_POND_CELEBRATION = <?= json_encode([
        'earnedDrops'  => (int) $pondCelebration['earned_drops'],
        'dropsBefore'  => (int) $pondCelebration['drops_before'],
        'dropsAfter'   => (int) $pondCelebration['drops_after'],
        'targetDrops'  => (int) $pondCelebration['target_drops'],
        'fillBefore'   => (float) $pondCelebration['fill_before'],
        'fillAfter'    => (float) $pondCelebration['fill_after'],
        'stageBefore'  => $celInitStage,
        'stageAfter'   => $celFinalStage,
        'images'       => $celStageMap,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
