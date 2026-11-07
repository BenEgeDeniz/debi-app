<?php
/**
 * DEBI - Puan Kullanım & Mağaza Sayfası
 * 1) Yeşil Markalarla Harcama Yapma (Alt alta anlaşmalı kafe ve su ayak izini azaltan deterjan mağazalarında %50 indirim için 50 puan harca)
 * 2) Kampanyaya Destek Olma (Anlaşmalı kampanyaya 50 puan harcayarak 50 L/m³ tasarruf gönderimi)
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
            $activeTab  = 'campaigns';
            $campaignId = (int) ($_POST['campaign_id'] ?? 0);
            $res        = support_campaign_with_points($currentUser, $campaignId);
            if ($res['ok']) {
                set_flash('success', $res['message']);
                redirect('store.php?tab=campaigns');
            }
            $error = $res['error'] ?? 'Kampanya desteği gönderilemedi.';
        } else {
            $error = 'Geçersiz puan kullanım işlemi.';
        }
    }
}

$walletBalance    = get_store_wallet_balance($currentUser);
$storeItems       = get_active_store_items();
$supportCampaigns = get_active_support_campaigns();
$myRedemptions    = get_household_store_redemptions((int) ($currentUser['household_id'] ?? 0));
$mySupports       = get_household_campaign_supports((int) ($currentUser['household_id'] ?? 0));

$categoryLabels = [
    'cafe'        => 'Anlaşmalı Yeşil Kafe · Cam Bardak & Fincan',
    'detergent'   => 'Su Ayak İzini Azaltan Deterjan Markası',
    'eco_product' => 'Su Tasarrufuna Katkı Sağlayan Yeşil Mağaza',
];

$pageTitle = 'Puan Kullanımı & Mağaza — Debi';
$activeNav = 'store';
require_once __DIR__ . '/includes/header.php';
?>

<div class="app-shell app-shell-2col">
    <?php require_once __DIR__ . '/includes/sidebar_left.php'; ?>

    <main class="main-column">
        <!-- Üst Başlık ve Cüzdan Puan Bakiyesi -->
        <section class="panel-card store-hero-card">
            <div class="panel-card-header">
                <div>
                    <div class="section-kicker">DEBİ PUAN KULLANIM &amp; MAĞAZA MERKEZİ</div>
                    <h1 class="section-title">Yeşil Markalarla Harcama Yap &amp; Kampanyaya Destek Ol</h1>
                </div>
                <div class="store-wallet-badge" title="Kullanılabilir Toplam Puan Bakiyeniz">
                    <span class="store-wallet-icon">💧</span>
                    <div class="store-wallet-meta">
                        <small>Kullanılabilir Puanınız</small>
                        <strong><?= number_format($walletBalance, 0, ',', '.') ?> Puan</strong>
                    </div>
                </div>
            </div>

            <p class="panel-desc" style="margin-bottom: 14px;">
                Su faturalarınızdaki tasarruflardan ve etkinlik katılımlarınızdan biriktirdiğiniz puanları aşağıda <strong>yan yana yer alan iki seçenekten</strong> birini seçerek değerlendirebilirsiniz: anlaşmalı yeşil markalarda <strong>%50 indirim</strong> kazanın veya anlaşmalı su kampanyalarına <strong>50 L/m³ tasarruf</strong> gönderin.
            </p>

            <?php if ($error): ?>
                <div class="alert alert-error" style="margin-bottom: 14px;">
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- YAN YANA 2 FARKLI SEÇENEK BUTONU -->
            <div class="store-choice-grid" role="tablist" aria-label="Puan Kullanım Seçenekleri">
                <button type="button"
                        class="store-choice-btn js-store-tab-btn <?= $activeTab === 'brands' ? 'active' : '' ?>"
                        data-store-tab="brands"
                        role="tab"
                        aria-selected="<?= $activeTab === 'brands' ? 'true' : 'false' ?>">
                    <span class="store-choice-emoji">🌿</span>
                    <div class="store-choice-text">
                        <strong>Yeşil Markalarla Harcama Yapma</strong>
                        <span>Cam bardak/fincan kullanan anlaşmalı kafeler ve su ayak izini azaltan deterjan markalarında <strong>%50 indirim için 50 puan harca</strong></span>
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
                        <span>Anlaşmalı su koruma kampanyalarına <strong>50 puan harcayarak 50 L/m³'lük tasarruf</strong> gönderimi sağla</span>
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
                        <div class="section-kicker">ANLAŞMALI YEŞİL MARKALAR &amp; KAFELER</div>
                        <h2 class="section-title">Mağaza Ekranı (%50 İndirim İçin 50 Puan Harca)</h2>
                    </div>
                    <span class="metric-pill pill-good"><?= count($storeItems) ?> Anlaşmalı Mağaza</span>
                </div>

                <p class="panel-desc">
                    Cam bardak ve porselen fincan kullanarak kağıt-plastik harcamasını azaltan anlaşmalı kafeler ile su tasarrufuna katkı sağlayan, su ayak izini azaltmayı amaçlamış deterjan markalarının ürünlerinde <strong>50 puan harcayarak anında %50 indirim kuponu</strong> alabilirsiniz.
                </p>

                <!-- Alt Alta Mağaza Sayfaları / Kartları Listesi -->
                <div class="store-vertical-list">
                    <?php foreach ($storeItems as $item): ?>
                        <?php
                        $itemId      = (int) $item['id'];
                        $costPts     = (int) ($item['cost_points'] ?? 50);
                        $discountPct = (int) ($item['discount_pct'] ?? 50);
                        $catKey      = (string) ($item['category'] ?? 'cafe');
                        $catLabel    = $categoryLabels[$catKey] ?? 'Anlaşmalı Yeşil Mağaza';
                        $canAfford   = $walletBalance >= $costPts;
                        ?>
                        <article class="store-vertical-card">
                            <div class="store-card-left">
                                <div class="store-card-icon" aria-hidden="true">
                                    <?= e($item['icon_emoji'] ?? '🌿') ?>
                                </div>
                                <div class="store-card-body">
                                    <div class="store-card-tags">
                                        <span class="store-cat-tag"><?= e($catLabel) ?></span>
                                        <span class="store-eco-tag"><?= e($item['eco_badge'] ?? '') ?></span>
                                    </div>
                                    <h3 class="store-brand-heading"><?= e($item['brand_name']) ?></h3>
                                    <div class="store-offer-subtitle"><?= e($item['title']) ?></div>
                                    <p class="store-offer-desc"><?= e($item['description']) ?></p>
                                </div>
                            </div>

                            <div class="store-card-action">
                                <div class="store-discount-badge">
                                    <strong>%<?= $discountPct ?> İNDİRİM</strong>
                                    <span>Bedel: <?= $costPts ?> Puan</span>
                                </div>

                                <form method="post" action="store.php?tab=brands" class="store-action-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="redeem_store_item">
                                    <input type="hidden" name="item_id" value="<?= $itemId ?>">
                                    <button type="submit"
                                            class="btn <?= $canAfford ? 'btn-primary' : 'btn-outline' ?> btn-block"
                                            <?= $canAfford ? '' : 'title="Bu indirim için 50 puana ihtiyacınız var"' ?>>
                                        %<?= $discountPct ?> İndirim İçin <?= $costPts ?> Puan Harca
                                    </button>
                                </form>
                                <?php if (!$canAfford): ?>
                                    <small class="store-balance-hint">
                                        <?= max(0, $costPts - $walletBalance) ?> puan daha gerekli
                                    </small>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Kullanıcının Aldığı İndirim Kuponları -->
            <section class="panel-card">
                <div class="panel-card-header">
                    <h2 class="section-title">Aldığım Anlaşmalı Mağaza İndirim Kuponları</h2>
                    <span class="metric-pill pill-neutral"><?= count($myRedemptions) ?> Kupon</span>
                </div>

                <?php if (empty($myRedemptions)): ?>
                    <div class="empty-inline">
                        Henüz anlaşmalı yeşil mağazalardan indirim kuponu almadınız. Yukarıdaki mağaza listesinden 50 puan harcayarak %50 indirim kuponu oluşturabilirsiniz.
                    </div>
                <?php else: ?>
                    <div class="store-redemption-list">
                        <?php foreach ($myRedemptions as $red): ?>
                            <div class="store-redemption-row">
                                <div class="store-redemption-info">
                                    <strong><?= e($red['icon_emoji'] ?? '🌿') ?> <?= e($red['brand_name']) ?> — %<?= (int) $red['discount_pct'] ?> İndirim</strong>
                                    <span><?= e($red['title']) ?> · <?= e(format_date_tr($red['created_at'])) ?> (-<?= (int) $red['spent_points'] ?> Puan)</span>
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
             2. SEÇENEK: ANLAŞMALI KAMPANYAYA DESTEK OLMA (50 PUAN -> 50 L/m³ TASARRUF GÖNDERİMİ)
             ========================================================= -->
        <div id="storeTabCampaigns" class="store-tab-pane" <?= $activeTab === 'campaigns' ? '' : 'hidden' ?>>
            <section class="panel-card">
                <div class="panel-card-header">
                    <div>
                        <div class="section-kicker">ANLAŞMALI SU KORUMA KAMPANYALARI</div>
                        <h2 class="section-title">Anlaşmalı Kampanyaya Destek (50 Puan = 50 L/m³ Tasarruf Gönderimi)</h2>
                    </div>
                    <span class="metric-pill pill-good"><?= count($supportCampaigns) ?> Aktif Kampanya</span>
                </div>

                <p class="panel-desc">
                    Anlaşmalı su tasarrufu ve havza koruma kampanyalarına <strong>50 puan harcayarak 50 L/m³'lük bir tasarrufu</strong> doğrudan kampanya havuzuna gönderebilirsiniz.
                </p>

                <div class="store-vertical-list">
                    <?php foreach ($supportCampaigns as $camp): ?>
                        <?php
                        $campId       = (int) $camp['id'];
                        $costPts      = (int) ($camp['cost_points'] ?? 50);
                        $impactLabel  = (string) ($camp['impact_unit_label'] ?? '50 L/m³');
                        $currentVal   = (int) ($camp['current_contrib_amount'] ?? 0);
                        $targetVal    = (int) ($camp['target_contrib_amount'] ?? 5000);
                        $supporters   = (int) ($camp['supporter_count'] ?? 0);
                        $progressPct  = (float) ($camp['progress_pct'] ?? 0);
                        $canAfford    = $walletBalance >= $costPts;
                        ?>
                        <article class="store-vertical-card campaign-support-card">
                            <div class="store-card-left">
                                <div class="store-card-icon campaign-icon" aria-hidden="true">
                                    <?= e($camp['icon_emoji'] ?? '💧') ?>
                                </div>
                                <div class="store-card-body">
                                    <div class="store-card-tags">
                                        <span class="store-cat-tag"><?= e($camp['partner_name']) ?></span>
                                        <span class="store-eco-tag">💧 <?= $supporters ?> Destekçi Katkı Sağladı</span>
                                    </div>
                                    <h3 class="store-brand-heading"><?= e($camp['title']) ?></h3>
                                    <p class="store-offer-desc"><?= e($camp['description']) ?></p>

                                    <div class="campaign-progress-box">
                                        <div class="comp-row">
                                            <span>Kampanyaya Gönderilen Toplam Tasarruf: <strong><?= number_format($currentVal, 0, ',', '.') ?> L/m³</strong></span>
                                            <span>Hedef: <strong><?= number_format($targetVal, 0, ',', '.') ?> L/m³ (%<?= number_format($progressPct, 1, ',', '.') ?>)</strong></span>
                                        </div>
                                        <div class="progress-track">
                                            <div class="progress-fill fill-green" style="width: <?= min(100, max(3, $progressPct)) ?>%;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="store-card-action">
                                <div class="store-discount-badge campaign-badge">
                                    <strong>+<?= e($impactLabel) ?></strong>
                                    <span>Tasarruf Gönderimi · <?= $costPts ?> Puan</span>
                                </div>

                                <form method="post" action="store.php?tab=campaigns" class="store-action-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="support_campaign">
                                    <input type="hidden" name="campaign_id" value="<?= $campId ?>">
                                    <button type="submit"
                                            class="btn <?= $canAfford ? 'btn-primary' : 'btn-outline' ?> btn-block"
                                            <?= $canAfford ? '' : 'title="Kampanyaya destek göndermek için 50 puana ihtiyacınız var"' ?>>
                                        <?= $costPts ?> Puan Harca · <?= e($impactLabel) ?> Gönder
                                    </button>
                                </form>
                                <?php if (!$canAfford): ?>
                                    <small class="store-balance-hint">
                                        <?= max(0, $costPts - $walletBalance) ?> puan daha gerekli
                                    </small>
                                <?php endif; ?>
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
                        Henüz anlaşmalı bir kampanyaya puan desteği göndermediniz. 50 puan harcayarak 50 L/m³'lük ilk tasarruf gönderiminizi yapabilirsiniz.
                    </div>
                <?php else: ?>
                    <div class="store-redemption-list">
                        <?php foreach ($mySupports as $sup): ?>
                            <div class="store-redemption-row">
                                <div class="store-redemption-info">
                                    <strong><?= e($sup['icon_emoji'] ?? '💧') ?> <?= e($sup['campaign_title']) ?></strong>
                                    <span><?= e($sup['partner_name']) ?> · <?= e(format_date_tr($sup['created_at'])) ?></span>
                                </div>
                                <span class="metric-pill pill-good">
                                    +<?= e($sup['impact_label'] ?? '50 L/m³') ?> Tasarruf Gönderildi (-<?= (int) $sup['spent_points'] ?> Puan)
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

