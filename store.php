<?php
/**
 * DEBI - Mağazalar ve Kampanyalar Sayfası
 * 1) Yeşil Markalarla Harcama Yapma (%30 İndirim -> 30 DP, %50 İndirim -> 50 DP, %10 İndirim -> 10 DP, %15 İndirim -> 15 DP)
 * 2) Kampanyaya Destek Olma (Mahalle Okulları & Burdur Gölü saha kampanyalarına min. 50 DP destek gönderimi)
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/modules/store/store_service.php';

$currentUser = require_login();
$activeTab   = ($_GET['tab'] ?? 'brands') === 'campaigns' ? 'campaigns' : 'brands';
$error       = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Güvenlik doğrulaması başarısız oldu, lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        $isAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

        if ($action === 'redeem_store_item') {
            $activeTab = 'brands';
            $itemId    = (int) ($_POST['item_id'] ?? 0);
            $itemSlug  = trim((string) ($_POST['item_slug'] ?? ''));
            $res       = redeem_store_item($currentUser, $itemId, $itemSlug);
            if ($res['ok']) {
                $_SESSION['store_purchase_popup'] = [
                    'brand_name'   => (string) ($res['brand_name'] ?? 'Mağaza'),
                    'discount_pct' => (int) ($res['discount_pct'] ?? 30),
                    'spent_points' => (int) ($res['spent_points'] ?? 30),
                    'coupon_code'  => (string) ($res['coupon_code'] ?? ''),
                ];
                if (!$isAjax) {
                    redirect('store.php?tab=brands');
                }
            } else {
                $error = $res['error'] ?? 'İşlem gerçekleştirilemedi.';
            }
        } elseif ($action === 'support_campaign') {
            $activeTab       = 'campaigns';
            $campaignId      = (int) ($_POST['campaign_id'] ?? 0);
            $campaignSlug    = trim((string) ($_POST['campaign_slug'] ?? ''));
            $requestedPoints = (int) ($_POST['support_points'] ?? 50);
            $res             = support_campaign_with_points($currentUser, $campaignId, $requestedPoints, $campaignSlug);
            if ($res['ok']) {
                if (!empty($res['certificate']) && is_array($res['certificate'])) {
                    $_SESSION['campaign_certificate_popup'] = $res['certificate'];
                }
                if (!$isAjax) {
                    redirect('store.php?tab=campaigns');
                }
            } else {
                $error = $res['error'] ?? 'Kampanya desteği gönderilemedi.';
            }
        } else {
            $error = 'Geçersiz mağaza işlemi.';
        }
    }
}

$purchasePopup = null;
if (!empty($_SESSION['store_purchase_popup']) && is_array($_SESSION['store_purchase_popup'])) {
    $purchasePopup = $_SESSION['store_purchase_popup'];
    unset($_SESSION['store_purchase_popup']);
}

$certificatePopup = null;
if (!empty($_SESSION['campaign_certificate_popup']) && is_array($_SESSION['campaign_certificate_popup'])) {
    $certificatePopup = $_SESSION['campaign_certificate_popup'];
    unset($_SESSION['campaign_certificate_popup']);
}

$walletBalance    = get_store_wallet_balance($currentUser);
$storeItems       = get_active_store_items();
$supportCampaigns = get_active_support_campaigns($currentUser);
$myRedemptions    = get_household_store_redemptions((int) ($currentUser['household_id'] ?? 0));
$mySupports       = get_household_campaign_supports((int) ($currentUser['household_id'] ?? 0));

$activeCoupons = array_values(array_filter($myRedemptions, static function (array $r): bool {
    return (int) ($r['is_used'] ?? 0) === 0;
}));

$defaultStorePhotos = [
    'damla-kafe'           => 'assets/store/damla-kafe.jpg',
    'yesil-sofra-restoran' => 'assets/store/yesil-sofra-restoran.jpg',
    'aquapure-deterjan'    => 'assets/store/aquapure-deterjan.jpg',
    'ekoakis-perlator'     => 'assets/store/ekoakis-perlator.jpg',
];

$pageTitle = 'Mağazalar ve Kampanyalar — Debi';
$activeNav = 'store';
require_once __DIR__ . '/includes/header.php';
?>

<div class="app-shell app-shell-2col">
    <?php require_once __DIR__ . '/includes/sidebar_left.php'; ?>

    <main class="main-column" data-current-dp="<?= (int) $walletBalance ?>">
        <!-- Yetersiz Puan Uyarı Pop-up Modalı -->
        <div class="store-popup-overlay"
             id="storeErrorPopupModal"
             role="alertdialog"
             aria-modal="true"
             aria-labelledby="storePopupTitle"
             <?= $error ? '' : 'hidden' ?>>
            <div class="store-popup-card">
                <div class="store-popup-icon" aria-hidden="true">⚠️</div>
                <h3 class="store-popup-title" id="storePopupTitle">Yetersiz Puan</h3>
                <p class="store-popup-message" id="storeErrorPopupMessage"><?= e($error ?? '') ?></p>
                <button type="button" class="btn btn-primary btn-block js-close-store-popup">Tamam</button>
            </div>
        </div>

        <!-- Satın Alım / Gönderim Onaylama Pop-up Modalı -->
        <div class="store-popup-overlay"
             id="storeConfirmPopupModal"
             role="dialog"
             aria-modal="true"
             aria-labelledby="storeConfirmPopupTitle"
             hidden>
            <div class="store-popup-card store-confirm-card">
                <div class="store-popup-icon store-confirm-icon" aria-hidden="true">💧</div>
                <h3 class="store-popup-title store-confirm-title" id="storeConfirmPopupTitle">Satın alımını onaylıyor musun?</h3>
                <p class="store-popup-message" id="storeConfirmPopupMessage"></p>
                <div class="store-confirm-actions">
                    <button type="button" class="btn btn-outline js-cancel-store-confirm">Vazgeç</button>
                    <button type="button" class="btn btn-primary js-approve-store-confirm">Onayla</button>
                </div>
            </div>
        </div>

        <!-- Mağaza Satın Alımınız Onaylandı & Kupon Kodu Pop-up Modalı -->
        <div class="store-popup-overlay"
             id="storeCouponSuccessModal"
             role="dialog"
             aria-modal="true"
             aria-labelledby="storeCouponSuccessTitle"
             <?= $purchasePopup ? '' : 'hidden' ?>>
            <div class="store-popup-card store-coupon-success-card">
                <div class="store-popup-icon store-success-icon" aria-hidden="true">✓</div>
                <h3 class="store-popup-title store-success-title" id="storeCouponSuccessTitle">Satın Alımınız Onaylandı</h3>
                <p class="store-popup-message" id="storeCouponSuccessDesc">
                    <?php if ($purchasePopup): ?>
                        <strong><?= e($purchasePopup['brand_name']) ?></strong> mağazasında <strong>%<?= (int) $purchasePopup['discount_pct'] ?> indirim</strong> kuponunuz oluşturuldu ve <strong>Aktif Kuponlar</strong> bölümüne eklendi (-<?= (int) $purchasePopup['spent_points'] ?> DP).
                    <?php endif; ?>
                </p>

                <!-- Altta Kupon Kodu, Sağ Tarafında Kopyalama Butonu -->
                <div class="store-coupon-popup-bar">
                    <div class="store-coupon-popup-code-wrap">
                        <span class="store-coupon-popup-label">Kupon Kodunuz</span>
                        <code id="storeCouponSuccessCode"><?= e($purchasePopup['coupon_code'] ?? '') ?></code>
                    </div>
                    <button type="button"
                            class="btn btn-primary btn-copy store-coupon-popup-copy-btn"
                            id="storeCouponSuccessCopyBtn"
                            data-copy="<?= e($purchasePopup['coupon_code'] ?? '') ?>">
                        Kopyala
                    </button>
                </div>

                <button type="button" class="btn btn-outline btn-block js-close-store-popup">Tamam</button>
            </div>
        </div>

        <!-- Kampanya Destek Göstergeli Teşekkür Belgesi Pop-up Modalı -->
        <?php
        $certRecipient  = (string) ($certificatePopup['recipient_name'] ?? ($currentUser['full_name'] ?? 'Değerli Katılımcı'));
        $certCampaign   = (string) ($certificatePopup['campaign_title'] ?? 'Su Tasarrufu Kampanyası');
        $certPartner    = (string) ($certificatePopup['partner_name'] ?? 'Debi Su İşbirliği');
        $certPoints     = (int) ($certificatePopup['spent_points'] ?? 50);
        $certCurrentDp  = (int) ($certificatePopup['current_dp'] ?? 1000);
        $certTargetDp   = max(1, (int) ($certificatePopup['target_dp'] ?? 5000));
        $certProgress   = (float) ($certificatePopup['progress_pct'] ?? min(100, round(($certCurrentDp / $certTargetDp) * 100, 1)));
        $certSupporters = (int) ($certificatePopup['supporter_count'] ?? 20);
        $certDate       = (string) ($certificatePopup['date_label'] ?? format_date_tr(date('Y-m-d H:i:s'), false));
        $certPresident  = (string) ($certificatePopup['president_name'] ?? 'Debi Su Koruma Kurulu');
        ?>
        <div class="store-popup-overlay"
             id="campaignCertificateModal"
             role="dialog"
             aria-modal="true"
             aria-labelledby="certTitleHeading"
             <?= $certificatePopup ? '' : 'hidden' ?>>
            <div class="store-popup-card certificate-modal-shell">
                <button type="button" class="certificate-close-x js-close-store-popup" aria-label="Belgeyi Kapat">✕</button>

                <!-- Görseldeki Tasarıma Uygun Çift Altın Çerçeveli & Yapraklı Teşekkür Belgesi -->
                <div class="thankyou-certificate"
                     id="thankyouCertificateCard"
                     data-recipient="<?= e($certRecipient) ?>"
                     data-campaign="<?= e($certCampaign) ?>"
                     data-partner="<?= e($certPartner) ?>"
                     data-points="<?= $certPoints ?>"
                     data-current-dp="<?= $certCurrentDp ?>"
                     data-target-dp="<?= $certTargetDp ?>"
                     data-progress="<?= $certProgress ?>"
                     data-date="<?= e($certDate) ?>"
                     data-president="<?= e($certPresident) ?>">

                    <!-- Sağ Üst Suluboya Yeşil Yapraklar (SVG) -->
                    <svg class="cert-leaves cert-leaves-top-right" viewBox="0 0 260 280" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M265 0C195 10 125 45 78 108C142 98 205 70 265 18Z" fill="#7eb37a" fill-opacity="0.78"/>
                        <path d="M265 18C210 62 175 135 190 245C228 190 255 120 265 18Z" fill="#689f65" fill-opacity="0.82"/>
                        <path d="M260 -5C180 18 115 62 82 106" stroke="#558b52" stroke-width="2" stroke-opacity="0.55"/>
                        <path d="M255 20C212 85 195 155 193 238" stroke="#4c8049" stroke-width="2" stroke-opacity="0.5"/>
                    </svg>

                    <!-- Sol Alt Suluboya Yeşil Yapraklar (SVG) -->
                    <svg class="cert-leaves cert-leaves-bottom-left" viewBox="0 0 200 220" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M-10 115C32 105 75 112 115 135C72 155 28 168 -10 158Z" fill="#7eb37a" fill-opacity="0.78"/>
                        <path d="M-10 158C28 168 68 195 92 230C42 230 5 215 -10 158Z" fill="#689f65" fill-opacity="0.84"/>
                        <path d="M-8 135C35 130 75 132 110 135" stroke="#558b52" stroke-width="1.8" stroke-opacity="0.5"/>
                    </svg>

                    <!-- Çift Altın Çerçeve -->
                    <div class="cert-frame-outer"></div>
                    <div class="cert-frame-inner"></div>

                    <div class="cert-body-content">
                        <h2 class="cert-script-title" id="certTitleHeading">Teşekkür Belgesi</h2>

                        <div class="cert-recipient-line">
                            <span class="cert-sn-prefix">Sn.</span>
                            <span class="cert-recipient-name" id="certRecipientName"><?= e($certRecipient) ?></span>
                        </div>

                        <p class="cert-appreciation-text" id="certAppreciationText">
                            <span id="certDateSpan"><?= e($certDate) ?></span> tarihinde
                            <strong>"<span id="certCampaignSpan"><?= e($certCampaign) ?></span>"</strong>
                            kampanyamıza sunduğunuz <strong><span id="certPointsSpan"><?= $certPoints ?></span> DP</strong>
                            su tasarrufu katkılarından dolayı teşekkürlerimizi sunarız.
                        </p>

                        <!-- Göstergeli Katkı & Kampanya İlerleme Kutusu -->
                        <div class="cert-indicator-box">
                            <div class="cert-indicator-top">
                                <span class="cert-indicator-badge">
                                    💧 Katkı Göstergesi: +<strong id="certBadgePoints"><?= $certPoints ?></strong> DP
                                </span>
                                <span class="cert-indicator-target">
                                    Kampanya Hedefi: <strong id="certCurrentDpText"><?= number_format($certCurrentDp, 0, ',', '.') ?></strong> / <span id="certTargetDpText"><?= number_format($certTargetDp, 0, ',', '.') ?></span> DP (<strong id="certProgressPctText">%<?= number_format($certProgress, 1, ',', '.') ?></strong>)
                                </span>
                            </div>
                            <div class="cert-indicator-track">
                                <div class="cert-indicator-fill" id="certProgressFill" style="width: <?= min(100, max(5, $certProgress)) ?>%;"></div>
                            </div>
                        </div>

                        <div class="cert-signatures-row">
                            <div class="cert-sig-block">
                                <span class="cert-sig-title">Düzenleyen kurum</span>
                                <strong class="cert-sig-value" id="certPartnerName"><?= e($certPartner) ?></strong>
                            </div>
                            <div class="cert-sig-block">
                                <span class="cert-sig-title">Kurum Başkanı</span>
                                <strong class="cert-sig-value" id="certPresidentName"><?= e($certPresident) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Belgeyi Paylaş / İndir Seçenekleri -->
                <div class="certificate-actions-bar">
                    <button type="button" class="btn btn-primary js-download-certificate">
                        📥 Belgeyi İndir
                    </button>
                    <button type="button" class="btn btn-outline js-share-certificate">
                        📤 Belgeyi Paylaş
                    </button>
                    <button type="button" class="btn btn-ghost js-close-store-popup">
                        Kapat
                    </button>
                </div>
                <p class="certificate-share-feedback" id="certificateShareFeedback" hidden></p>
            </div>
        </div>

        <!-- Üst Seçim Kartı: Başlık ve Yan Yana 2 Seçenek -->
        <section class="panel-card store-hero-card">
            <div class="panel-card-header" style="margin-bottom: 12px;">
                <h1 class="section-title">Mağazalar ve Kampanyalar</h1>
            </div>

            <div class="store-choice-grid" role="tablist" aria-label="Mağazalar ve Kampanyalar Seçenekleri">
                <button type="button"
                        class="store-choice-btn js-store-tab-btn <?= $activeTab === 'brands' ? 'active' : '' ?>"
                        data-store-tab="brands"
                        role="tab"
                        aria-selected="<?= $activeTab === 'brands' ? 'true' : 'false' ?>">
                    <img src="assets/store/icon-green-brands.jpg" alt="" class="store-choice-icon-img">
                    <div class="store-choice-text">
                        <strong>Yeşil Markalarla Harcama Yapma</strong>
                    </div>
                </button>

                <button type="button"
                        class="store-choice-btn js-store-tab-btn <?= $activeTab === 'campaigns' ? 'active' : '' ?>"
                        data-store-tab="campaigns"
                        role="tab"
                        aria-selected="<?= $activeTab === 'campaigns' ? 'true' : 'false' ?>">
                    <img src="assets/store/icon-campaign-support.jpg" alt="" class="store-choice-icon-img">
                    <div class="store-choice-text">
                        <strong>Kampanyaya Destek Olma</strong>
                    </div>
                </button>
            </div>
        </section>

        <!-- =========================================================
             1. SEÇENEK: YEŞİL MARKALARLA HARCAMA YAPMA (ALT ALTA MAĞAZA KARTLARI)
             ========================================================= -->
        <div id="storeTabBrands" class="store-tab-pane" <?= $activeTab === 'brands' ? '' : 'hidden' ?>>
            <section class="panel-card">
                <div class="panel-card-header">
                    <div>
                        <h2 class="section-title">Mağaza</h2>
                    </div>
                    <span class="metric-pill pill-good"><?= count($storeItems) ?> Anlaşmalı Mağaza</span>
                </div>

                <!-- Alt Alta Mağaza Kartları Listesi -->
                <div class="store-vertical-list">
                    <?php foreach ($storeItems as $item): ?>
                        <?php
                        $itemId      = (int) $item['id'];
                        $discountPct = max(1, (int) ($item['discount_pct'] ?? 30));
                        $costPts     = $discountPct; // %30 indirim için 30 DP, %50 indirim için 50 DP
                        $slugKey     = (string) ($item['slug'] ?? '');
                        $imgPath     = trim((string) ($item['image_path'] ?? ''));
                        if ($imgPath === '' && isset($defaultStorePhotos[$slugKey])) {
                            $imgPath = $defaultStorePhotos[$slugKey];
                        }
                        ?>
                        <article class="store-vertical-card">
                            <div class="store-card-left">
                                <!-- Fotoğraf Eklemek İçin Dikdörtgen ve Çizgilere Kadar Sınırı Olan Alan -->
                                <div class="store-photo-frame">
                                    <?php if ($imgPath !== ''): ?>
                                        <img src="<?= e($imgPath) ?>" alt="<?= e($item['brand_name']) ?>" class="store-photo-img">
                                    <?php else: ?>
                                        <span class="store-photo-placeholder-text">Fotoğraf Alanı</span>
                                    <?php endif; ?>
                                </div>

                                <div class="store-card-body">
                                    <h3 class="store-brand-heading"><?= e($item['brand_name']) ?></h3>
                                    <p class="store-offer-desc"><?= e($item['description']) ?></p>
                                </div>
                            </div>

                            <div class="store-card-action">
                                <form method="post"
                                      action="store.php?tab=brands"
                                      class="store-action-form js-store-spend-form"
                                      data-spend-type="brand"
                                      data-brand-name="<?= e($item['brand_name']) ?>"
                                      data-discount-pct="<?= $discountPct ?>"
                                      data-required-dp="<?= $costPts ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="redeem_store_item">
                                    <input type="hidden" name="item_id" value="<?= $itemId ?>">
                                    <input type="hidden" name="item_slug" value="<?= e($slugKey) ?>">
                                    <button type="submit"
                                            class="store-discount-btn"
                                            title="%<?= $discountPct ?> indirim almak için <?= $costPts ?> DP kullan">
                                        <strong>%<?= $discountPct ?> İNDİRİM</strong>
                                        <span><?= $costPts ?> DP</span>
                                    </button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Aktif Kuponlar Kısmı (Geçmişin Üstünde, Sağda Random Kupon Kodu ve En Sağda Kopyala Butonu) -->
            <section class="panel-card store-active-coupons-card">
                <div class="panel-card-header">
                    <div>
                        <h2 class="section-title">Aktif Kuponlar</h2>
                    </div>
                    <span class="metric-pill pill-good"><?= count($activeCoupons) ?> Aktif Kupon</span>
                </div>

                <?php if (empty($activeCoupons)): ?>
                    <div class="empty-inline">
                        Şu anda aktif indirim kuponunuz bulunmuyor. Yukarıdaki yeşil markalardan birinin indirim butonuna basıp satın alımı onayladığınızda rastgele kupon kodunuz burada oluşturulur.
                    </div>
                <?php else: ?>
                    <div class="store-redemption-list">
                        <?php foreach ($activeCoupons as $ac): ?>
                            <div class="store-redemption-row active-coupon-row">
                                <div class="store-redemption-info">
                                    <strong><?= e($ac['brand_name']) ?> — %<?= (int) $ac['discount_pct'] ?> İndirim Kuponu</strong>
                                    <span><?= e(format_date_tr($ac['created_at'])) ?> · Harcanan: <?= (int) $ac['spent_points'] ?> DP</span>
                                </div>
                                <div class="code-share-box">
                                    <code><?= e($ac['coupon_code']) ?></code>
                                    <button type="button" class="btn btn-primary btn-sm btn-copy" data-copy="<?= e($ac['coupon_code']) ?>">
                                        Kopyala
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Mağaza Kullanım Geçmişi (Akordiyon Sistem) -->
            <details class="panel-card store-accordion">
                <summary class="store-accordion-header">
                    <div class="store-accordion-title-wrap">
                        <span class="section-title">Mağaza Kullanım Geçmişi</span>
                        <span class="metric-pill pill-neutral"><?= count($myRedemptions) ?> İşlem</span>
                    </div>
                    <span class="store-accordion-chevron" aria-hidden="true">▾</span>
                </summary>

                <div class="store-accordion-body">
                    <?php if (empty($myRedemptions)): ?>
                        <div class="empty-inline">
                            Henüz mağazada puan kullanımı yapılmadı.
                        </div>
                    <?php else: ?>
                        <div class="store-redemption-list">
                            <?php foreach ($myRedemptions as $red): ?>
                                <div class="store-redemption-row">
                                    <div class="store-redemption-info">
                                        <strong><?= e($red['brand_name']) ?> — %<?= (int) $red['discount_pct'] ?> İndirim</strong>
                                        <span><?= e($red['full_name'] ?? '') ?> · <?= e(format_date_tr($red['created_at'])) ?></span>
                                    </div>
                                    <div class="code-share-box">
                                        <span class="metric-pill pill-good">-<?= (int) $red['spent_points'] ?> DP</span>
                                        <code><?= e($red['coupon_code']) ?></code>
                                        <button type="button" class="btn btn-outline btn-sm btn-copy" data-copy="<?= e($red['coupon_code']) ?>">
                                            Kopyala
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </details>
        </div>

        <!-- =========================================================
             2. SEÇENEK: ANLAŞMALI KAMPANYAYA DESTEK OLMA (MİN 50 DP, MAKS SINIRSIZ)
             ========================================================= -->
        <div id="storeTabCampaigns" class="store-tab-pane" <?= $activeTab === 'campaigns' ? '' : 'hidden' ?>>
            <section class="panel-card">
                <div class="panel-card-header">
                    <div>
                        <h2 class="section-title">Anlaşmalı Kampanyaya Destek</h2>
                    </div>
                    <span class="metric-pill pill-good"><?= count($supportCampaigns) ?> Aktif Kampanya</span>
                </div>

                <div class="store-vertical-list">
                    <?php foreach ($supportCampaigns as $camp): ?>
                        <?php
                        $campId      = (int) $camp['id'];
                        $minPts      = max(50, (int) ($camp['min_points'] ?? 50));
                        $currentDp   = (int) ($camp['current_dp'] ?? 0);
                        $targetDp    = (int) ($camp['target_dp'] ?? 5000);
                        $supporters  = (int) ($camp['supporter_count'] ?? 0);
                        $progressPct = (float) ($camp['progress_pct'] ?? 0);
                        $campImg     = trim((string) ($camp['image_path'] ?? ''));
                        $scopeTitle  = (string) ($camp['scope_title'] ?? '');
                        $scopeItems  = (array) ($camp['scope_items'] ?? []);
                        ?>
                        <article class="campaign-feature-card campaign-support-card">
                            <!-- Kampanyanın Üst Kısmında Geniş ve Uzun Görsel Alanı -->
                            <?php if ($campImg !== ''): ?>
                                <div class="campaign-top-banner">
                                    <img src="<?= e($campImg) ?>" alt="<?= e($camp['title']) ?>" class="campaign-top-banner-img">
                                </div>
                            <?php endif; ?>

                            <div class="campaign-card-content">
                                <div class="store-card-left">
                                    <div class="store-card-body">
                                        <h3 class="store-brand-heading"><?= e($camp['title']) ?></h3>
                                        <p class="store-offer-desc"><?= e($camp['description']) ?></p>

                                        <!-- İşbirliği Sağlayan Kurum, Destekçiler, Şu Ana Kadar Toplanan Toplam Katkı ve Hedef Aynı Kutuda -->
                                        <div class="campaign-unified-box">
                                            <div class="campaign-unified-grid">
                                                <div class="campaign-stat-item">
                                                    <span class="campaign-stat-label">İşbirliği Sağlayan Kurum</span>
                                                    <strong class="campaign-stat-val"><?= e($camp['partner_name']) ?></strong>
                                                </div>
                                                <div class="campaign-stat-item">
                                                    <span class="campaign-stat-label">Destekçiler</span>
                                                    <strong class="campaign-stat-val"><?= number_format($supporters, 0, ',', '.') ?> Kişi</strong>
                                                </div>
                                                <div class="campaign-stat-item">
                                                    <span class="campaign-stat-label">Şu Ana Kadar Toplanan Toplam Katkı</span>
                                                    <strong class="campaign-stat-val"><?= number_format($currentDp, 0, ',', '.') ?> DP</strong>
                                                </div>
                                                <div class="campaign-stat-item">
                                                    <span class="campaign-stat-label">Hedef</span>
                                                    <strong class="campaign-stat-val"><?= number_format($targetDp, 0, ',', '.') ?> DP (%<?= number_format($progressPct, 1, ',', '.') ?>)</strong>
                                                </div>
                                            </div>
                                            <div class="progress-track" style="margin-top: 10px;">
                                                <div class="progress-fill fill-green" style="width: <?= min(100, max(3, $progressPct)) ?>%;"></div>
                                            </div>
                                        </div>

                                        <?php if ($scopeTitle !== '' && !empty($scopeItems)): ?>
                                            <details class="campaign-scope-details">
                                                <summary>📍 <?= e($scopeTitle) ?> (Saha Uygulama Detayını Gör)</summary>
                                                <ul class="campaign-scope-list">
                                                    <?php foreach ($scopeItems as $sItem): ?>
                                                        <li><?= e($sItem) ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </details>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="store-card-action">
                                    <form method="post"
                                          action="store.php?tab=campaigns"
                                          class="store-action-form campaign-send-form js-store-spend-form"
                                          data-spend-type="campaign"
                                          data-campaign-title="<?= e($camp['title']) ?>"
                                          data-min-dp="<?= $minPts ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="support_campaign">
                                        <input type="hidden" name="campaign_id" value="<?= $campId ?>">
                                        <input type="hidden" name="campaign_slug" value="<?= e($camp['slug'] ?? '') ?>">
                                        <label class="campaign-dp-input-wrap">
                                            <span>Gönderilecek DP (Min. <?= $minPts ?> DP)</span>
                                            <input type="number"
                                                   name="support_points"
                                                   min="<?= $minPts ?>"
                                                   step="10"
                                                   value="<?= $minPts ?>"
                                                   required>
                                        </label>
                                        <button type="submit"
                                                class="store-discount-btn campaign-badge"
                                                title="Kampanyaya DP desteği gönder">
                                            <strong>Gönder</strong>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Katkıda Bulunduğum Kampanyalar (Akordiyon Sistem) -->
            <details class="panel-card store-accordion" <?= ($certificatePopup || !empty($mySupports)) ? 'open' : '' ?>>
                <summary class="store-accordion-header">
                    <div class="store-accordion-title-wrap">
                        <span class="section-title">Katkıda Bulunduğum Kampanyalar</span>
                        <span class="metric-pill pill-neutral"><?= count($mySupports) ?> Katkı</span>
                    </div>
                    <span class="store-accordion-chevron" aria-hidden="true">▾</span>
                </summary>

                <div class="store-accordion-body">
                    <?php if (empty($mySupports)): ?>
                        <div class="empty-inline">
                            Henüz anlaşmalı bir kampanyaya katkıda bulunmadınız.
                        </div>
                    <?php else: ?>
                        <div class="store-redemption-list">
                            <?php foreach ($mySupports as $sup): ?>
                                <?php
                                $supCurrentDp = (int) ($sup['current_dp'] ?? 1000);
                                $supTargetDp  = max(1, (int) ($sup['target_dp'] ?? 5000));
                                $supProgPct   = min(100, round(($supCurrentDp / $supTargetDp) * 100, 1));
                                $supDateOnly  = format_date_tr($sup['created_at'] ?? '', false);
                                ?>
                                <div class="store-redemption-row">
                                    <div class="store-redemption-info">
                                        <strong><?= e($sup['campaign_title']) ?></strong>
                                        <span><?= e($sup['partner_name']) ?> · <?= e($sup['full_name'] ?? '') ?> · <?= e(format_date_tr($sup['created_at'])) ?></span>
                                    </div>
                                    <div class="code-share-box">
                                        <span class="metric-pill pill-good">
                                            -<?= (int) $sup['spent_points'] ?> DP Gönderildi
                                        </span>
                                        <button type="button"
                                                class="btn btn-outline btn-sm js-open-certificate-btn"
                                                data-recipient="<?= e($sup['full_name'] ?? ($currentUser['full_name'] ?? 'Değerli Katılımcı')) ?>"
                                                data-campaign="<?= e($sup['campaign_title']) ?>"
                                                data-partner="<?= e($sup['partner_name']) ?>"
                                                data-points="<?= (int) $sup['spent_points'] ?>"
                                                data-current-dp="<?= $supCurrentDp ?>"
                                                data-target-dp="<?= $supTargetDp ?>"
                                                data-progress="<?= $supProgPct ?>"
                                                data-date="<?= e($supDateOnly) ?>"
                                                data-president="Debi Su Koruma Kurulu">
                                            📜 Teşekkür Belgesi
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </details>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
