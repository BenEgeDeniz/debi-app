<?php
/**
 * DEBI - Mağaza Sayfası
 * 1) Yeşil Markalarla Harcama Yapma (Alt alta anlaşmalı kafe, restoran, deterjan ve su ekipmanı mağazaları)
 * 2) Kampanyaya Destek Olma (Anlaşmalı su koruma kampanyalarına min 50 DP destek gönderimi)
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
$supportCampaigns = get_active_support_campaigns();
$myRedemptions    = get_household_store_redemptions((int) ($currentUser['household_id'] ?? 0));
$mySupports       = get_household_campaign_supports((int) ($currentUser['household_id'] ?? 0));

$pageTitle = 'Mağaza — Debi';
$activeNav = 'store';
require_once __DIR__ . '/includes/header.php';
?>

<div class="app-shell app-shell-2col">
    <?php require_once __DIR__ . '/includes/sidebar_left.php'; ?>

    <main class="main-column">
        <!-- Üst Başlık ve Yan Yana 2 Seçenek -->
        <section class="panel-card store-hero-card">
            <div class="panel-card-header">
                <div>
                    <div class="section-kicker">MAĞAZA</div>
                    <h1 class="section-title">Yeşil Markalarla Harcama Yap ya da Kampanyaya Destek Ol</h1>
                </div>
            </div>

            <p class="panel-desc" style="margin-bottom: 14px;">
                Su faturalarınızdaki tasarruflardan ve etkinlik katılımlarınızdan biriktirdiğiniz puanları aşağıda <strong>yan yana yer alan iki seçenekten</strong> birini seçerek değerlendirebilirsiniz.
            </p>

            <?php if ($error): ?>
                <div class="alert alert-error" style="margin-bottom: 14px;">
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- YAN YANA 2 FARKLI SEÇENEK BUTONU -->
            <div class="store-choice-grid" role="tablist" aria-label="Mağaza Seçenekleri">
                <button type="button"
                        class="store-choice-btn js-store-tab-btn <?= $activeTab === 'brands' ? 'active' : '' ?>"
                        data-store-tab="brands"
                        role="tab"
                        aria-selected="<?= $activeTab === 'brands' ? 'true' : 'false' ?>">
                    <span class="store-choice-emoji">🌿</span>
                    <div class="store-choice-text">
                        <strong>Yeşil Markalarla Harcama Yapma</strong>
                        <span>Anlaşmalı yeşil kafe, restoran, deterjan ve su tasarruf ekipmanı mağazalarında puanlarını kullan</span>
                    </div>
                </button>

                <button type="button"
                        class="store-choice-btn js-store-tab-btn <?= $activeTab === 'campaigns' ? 'active' : '' ?>"
                        data-store-tab="campaigns"
                        role="tab"
                        aria-selected="<?= $activeTab === 'campaigns' ? 'true' : 'false' ?>">
                    <span class="store-choice-emoji">💧</span>
                    <div class="store-choice-text">
                        <strong>Kampanyaya Destek Olma</strong>
                        <span>Anlaşmalı su koruma kampanyalarına puanlarınla (min. 50 DP) doğrudan destek gönder</span>
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
                        $costPts     = (int) ($item['cost_points'] ?? 50);
                        $discountPct = (int) ($item['discount_pct'] ?? 30);
                        $imgPath     = trim((string) ($item['image_path'] ?? ''));
                        $canAfford   = $walletBalance >= $costPts;
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
                                <div class="store-discount-badge">
                                    <strong>%<?= $discountPct ?> İNDİRİM</strong>
                                    <span><?= $costPts ?> DP</span>
                                </div>

                                <form method="post" action="store.php?tab=brands" class="store-action-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="redeem_store_item">
                                    <input type="hidden" name="item_id" value="<?= $itemId ?>">
                                    <button type="submit"
                                            class="btn <?= $canAfford ? 'btn-primary' : 'btn-outline' ?> btn-block"
                                            <?= $canAfford ? '' : 'title="Bu indirim için 50 DP puana ihtiyacınız var"' ?>>
                                        %<?= $discountPct ?> İndirim İçin <?= $costPts ?> Puan Harca
                                    </button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Kullanıcının Aldığı İndirim Kuponları -->
            <section class="panel-card">
                <div class="panel-card-header">
                    <h2 class="section-title">Aldığım Mağaza İndirim Kuponları</h2>
                    <span class="metric-pill pill-neutral"><?= count($myRedemptions) ?> Kupon</span>
                </div>

                <?php if (empty($myRedemptions)): ?>
                    <div class="empty-inline">
                        Henüz anlaşmalı yeşil mağazalardan indirim kuponu almadınız.
                    </div>
                <?php else: ?>
                    <div class="store-redemption-list">
                        <?php foreach ($myRedemptions as $red): ?>
                            <div class="store-redemption-row">
                                <div class="store-redemption-info">
                                    <strong><?= e($red['brand_name']) ?> — %<?= (int) $red['discount_pct'] ?> İndirim</strong>
                                    <span><?= e(format_date_tr($red['created_at'])) ?> (-<?= (int) $red['spent_points'] ?> DP)</span>
                                </div>
                                <div class="code-share-box">
                                    <code><?= e($red['coupon_code']) ?></code>
                                    <button type="button" class="btn btn-outline btn-sm btn-copy" data-copy="<?= e($red['coupon_code']) ?>">
                                        Kodu Kopyala
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
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
                        $canAfford   = $walletBalance >= $minPts;
                        ?>
                        <article class="store-vertical-card campaign-support-card">
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
                                </div>
                            </div>

                            <div class="store-card-action">
                                <div class="store-discount-badge campaign-badge">
                                    <strong><?= $minPts ?> DP</strong>
                                    <span>Min. <?= $minPts ?> DP Gönderim</span>
                                </div>

                                <form method="post" action="store.php?tab=campaigns" class="store-action-form campaign-send-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="support_campaign">
                                    <input type="hidden" name="campaign_id" value="<?= $campId ?>">
                                    <label class="campaign-dp-input-wrap">
                                        <span>Gönderilecek Puan (Min. <?= $minPts ?> DP)</span>
                                        <input type="number"
                                               name="support_points"
                                               min="<?= $minPts ?>"
                                               step="10"
                                               value="<?= $minPts ?>"
                                               required>
                                    </label>
                                    <button type="submit"
                                            class="btn <?= $canAfford ? 'btn-primary' : 'btn-outline' ?> btn-block"
                                            <?= $canAfford ? '' : 'title="Kampanyaya destek göndermek için en az 50 DP puana ihtiyacınız var"' ?>>
                                        50 DP Gönder
                                    </button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Kullanıcının Gönderdiği Kampanya Destekleri -->
            <section class="panel-card">
                <div class="panel-card-header">
                    <h2 class="section-title">Kampanya Destek Geçmişim</h2>
                    <span class="metric-pill pill-neutral"><?= count($mySupports) ?> Destek</span>
                </div>

                <?php if (empty($mySupports)): ?>
                    <div class="empty-inline">
                        Henüz anlaşmalı bir kampanyaya destek göndermediniz.
                    </div>
                <?php else: ?>
                    <div class="store-redemption-list">
                        <?php foreach ($mySupports as $sup): ?>
                            <div class="store-redemption-row">
                                <div class="store-redemption-info">
                                    <strong><?= e($sup['campaign_title']) ?></strong>
                                    <span><?= e($sup['partner_name']) ?> · <?= e(format_date_tr($sup['created_at'])) ?></span>
                                </div>
                                <span class="metric-pill pill-good">
                                    <?= (int) $sup['spent_points'] ?> DP Gönderildi
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
