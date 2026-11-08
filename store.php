<?php
/**
 * DEBI - Mağaza Sayfası
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

        if ($action === 'redeem_store_item') {
            $activeTab = 'brands';
            $itemId    = (int) ($_POST['item_id'] ?? 0);
            $res       = redeem_store_item($currentUser, $itemId);
            if ($res['ok']) {
                set_flash('success', $res['message']);
                redirect('store.php?tab=brands');
            }
            $error = $res['error'] ?? 'İşlem gerçekleştirilemedi.';
        } elseif ($action === 'use_coupon') {
            $activeTab    = 'brands';
            $redemptionId = (int) ($_POST['redemption_id'] ?? 0);
            $res          = mark_store_coupon_used($currentUser, $redemptionId);
            if ($res['ok']) {
                set_flash('success', $res['message']);
                redirect('store.php?tab=brands');
            }
            $error = $res['error'] ?? 'Kupon güncellenemedi.';
        } elseif ($action === 'support_campaign') {
            $activeTab       = 'campaigns';
            $campaignId      = (int) ($_POST['campaign_id'] ?? 0);
            $requestedPoints = (int) ($_POST['support_points'] ?? 50);
            $res             = support_campaign_with_points($currentUser, $campaignId, $requestedPoints);
            if ($res['ok']) {
                set_flash('success', $res['message']);
                redirect('store.php?tab=campaigns');
            }
            $error = $res['error'] ?? 'Kampanya desteği gönderilemedi.';
        } else {
            $error = 'Geçersiz mağaza işlemi.';
        }
    }
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

    <main class="main-column">
        <?php if ($error): ?>
            <!-- Uyarı Mesajı Pop-up Modalı -->
            <div class="store-popup-overlay" id="storeErrorPopupModal" role="alertdialog" aria-modal="true" aria-labelledby="storePopupTitle">
                <div class="store-popup-card">
                    <div class="store-popup-icon" aria-hidden="true">⚠️</div>
                    <h3 class="store-popup-title" id="storePopupTitle">Yetersiz Puan Bakiyesi</h3>
                    <p class="store-popup-message"><?= e($error) ?></p>
                    <button type="button" class="btn btn-primary btn-block js-close-store-popup">Tamam</button>
                </div>
            </div>
        <?php endif; ?>

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
                                <form method="post" action="store.php?tab=brands" class="store-action-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="redeem_store_item">
                                    <input type="hidden" name="item_id" value="<?= $itemId ?>">
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

            <!-- Aktif Kuponlar Kısmı (Geçmişin Üstünde) -->
            <section class="panel-card store-active-coupons-card">
                <div class="panel-card-header">
                    <div>
                        <h2 class="section-title">Aktif Kuponlar</h2>
                    </div>
                    <span class="metric-pill pill-good"><?= count($activeCoupons) ?> Aktif Kupon</span>
                </div>

                <?php if (empty($activeCoupons)): ?>
                    <div class="empty-inline">
                        Şu anda aktif indirim kuponunuz bulunmuyor. Yukarıdaki yeşil markalardan birinin indirim butonuna bastığınızda aktif kuponunuz burada oluşturulur.
                    </div>
                <?php else: ?>
                    <div class="store-redemption-list">
                        <?php foreach ($activeCoupons as $ac): ?>
                            <div class="store-redemption-row active-coupon-row">
                                <div class="store-redemption-info">
                                    <strong><?= e($ac['brand_name']) ?> — %<?= (int) $ac['discount_pct'] ?> İndirim Kuponu</strong>
                                    <span>Oluşturulma: <?= e(format_date_tr($ac['created_at'])) ?> · Harcanan: <?= (int) $ac['spent_points'] ?> DP</span>
                                </div>
                                <div class="code-share-box">
                                    <code><?= e($ac['coupon_code']) ?></code>
                                    <button type="button" class="btn btn-outline btn-sm btn-copy" data-copy="<?= e($ac['coupon_code']) ?>">
                                        Kopyala
                                    </button>
                                    <form method="post" action="store.php?tab=brands" style="display:inline-block; margin:0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="use_coupon">
                                        <input type="hidden" name="redemption_id" value="<?= (int) $ac['id'] ?>">
                                        <button type="submit" class="btn btn-primary btn-sm">Kullandım</button>
                                    </form>
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
                                <?php $isUsed = (int) ($red['is_used'] ?? 0) === 1; ?>
                                <div class="store-redemption-row">
                                    <div class="store-redemption-info">
                                        <strong><?= e($red['brand_name']) ?> — %<?= (int) $red['discount_pct'] ?> İndirim</strong>
                                        <span>
                                            <?= e($red['full_name'] ?? '') ?> · <?= e(format_date_tr($red['created_at'])) ?>
                                            · <?= $isUsed ? 'Kasada Kullanıldı' : 'Aktif Kupon' ?>
                                        </span>
                                    </div>
                                    <div class="code-share-box">
                                        <span class="metric-pill pill-good">-<?= (int) $red['spent_points'] ?> DP</span>
                                        <code><?= e($red['coupon_code']) ?></code>
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
                            <!-- Kampanyanın Üst Kısmında Geniş ve Uzun (0.75x Daha Uzun Ölçekli) Görsel Alanı -->
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
                                    <form method="post" action="store.php?tab=campaigns" class="store-action-form campaign-send-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="support_campaign">
                                        <input type="hidden" name="campaign_id" value="<?= $campId ?>">
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
            <details class="panel-card store-accordion">
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
                                <div class="store-redemption-row">
                                    <div class="store-redemption-info">
                                        <strong><?= e($sup['campaign_title']) ?></strong>
                                        <span><?= e($sup['partner_name']) ?> · <?= e($sup['full_name'] ?? '') ?> · <?= e(format_date_tr($sup['created_at'])) ?></span>
                                    </div>
                                    <span class="metric-pill pill-good">
                                        <?= (int) $sup['spent_points'] ?> DP Gönderildi
                                    </span>
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
