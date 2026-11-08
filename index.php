<?php
/**
 * DEBI - Resmi Landing Page (Giriş / Karşılama Sayfası)
 * Sosyal Su Tasarrufu, Yapay Zeka Destekli Fatura Okuma ve Mahalle İmecesi
 */
require_once __DIR__ . '/includes/init.php';

$statHouseholds = 24;
$statApartments = 6;
$statEvents = 8;
try {
    $hCount = (int) db()->query("SELECT COUNT(*) FROM households")->fetchColumn();
    if ($hCount > 0) $statHouseholds = $hCount;
    $aCount = (int) db()->query("SELECT COUNT(*) FROM apartments")->fetchColumn();
    if ($aCount > 0) $statApartments = $aCount;
    $eCount = (int) db()->query("SELECT COUNT(*) FROM events")->fetchColumn();
    if ($eCount > 0) $statEvents = $eCount;
} catch (Throwable $e) {}

$pageTitle = 'Debi — Mahalle ve Apartman Ölçekli Sosyal Su Ağı';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" type="image/png" href="<?= e(brand_asset_path('logo')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?: 9 ?>">
</head>
<body class="landing-body">

    <!-- ÜST NAVİGASYON (LANDING BAR) -->
    <header class="landing-nav-bar">
        <div class="landing-nav-inner">
            <a href="index.php" class="brand-logo">
                <?= render_brand_logo('brand-mascot') ?>
                <div class="brand-text">
                    <span class="brand-mark">debi</span>
                    <span class="brand-sub">sosyal su ağı</span>
                </div>
            </a>

            <nav class="landing-nav-links">
                <a href="#nasil-calisir" class="landing-nav-link">Nasıl Çalışır?</a>
                <a href="#ozellikler" class="landing-nav-link">Özellikler</a>
                <a href="#golet-sistemi" class="landing-nav-link">Daire Göleti</a>
                <a href="#imece-etkinlikler" class="landing-nav-link">İmece &amp; Etkinlikler</a>
            </nav>

            <div class="landing-nav-actions">
                <?php if (!empty($currentUser)): ?>
                    <a href="feed.php" class="btn btn-primary btn-sm">
                        <span>Akışa Git</span>
                        <span style="margin-left: 4px;">💧</span>
                    </a>
                    <a href="profile.php" class="user-chip" title="Profilim" style="text-decoration:none;">
                        <?= render_avatar($currentUser, 'avatar-sm') ?>
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline btn-sm">Giriş Yap</a>
                    <a href="register.php" class="btn btn-primary btn-sm">Aramıza Katıl</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- HERO SECTION -->
    <section class="landing-hero">
        <div class="landing-hero-content">
            <div class="landing-hero-kicker">
                <span>💧</span>
                <span>Sosyal Su Tasarrufu ve Dayanışma Ağı</span>
            </div>

            <h1 class="landing-hero-title">
                Suyunu Koru, Mahallenle Yarış, <span class="highlight-water">Göletini Yeşert!</span>
            </h1>

            <p class="landing-hero-desc">
                Debi; su faturanızı <strong>Gemini Yapay Zeka</strong> ile analiz eden, tasarrufunuzu eğlenceli puanlara dönüştüren ve mahalle sakinlerini gerçek çevre imecelerinde buluşturan yeni nesil sosyal su platformudur.
            </p>

            <div class="landing-hero-actions">
                <?php if (!empty($currentUser)): ?>
                    <a href="feed.php" class="btn btn-primary btn-lg">Sosyal Akışa Geç 🚀</a>
                    <a href="events.php" class="btn btn-outline btn-lg">Etkinlikleri Gör</a>
                <?php else: ?>
                    <a href="register.php" class="btn btn-primary btn-lg">Hemen Başla (Ücretsiz)</a>
                    <a href="#nasil-calisir" class="btn btn-outline btn-lg">Nasıl Çalışır?</a>
                <?php endif; ?>
            </div>

            <div class="landing-hero-trust">
                <div class="landing-trust-item">
                    <span class="landing-trust-icon">✨</span>
                    <span>Gemini 3.8 Flash AI Doğrulaması</span>
                </div>
                <div class="landing-trust-item">
                    <span class="landing-trust-icon">🏘️</span>
                    <span>Apartman ve Mahalle Ligleri</span>
                </div>
                <div class="landing-trust-item">
                    <span class="landing-trust-icon">🎁</span>
                    <span>Debi Puanı (DP) Yerel Ödülleri</span>
                </div>
            </div>
        </div>

        <div class="landing-hero-visual">
            <div class="landing-visual-card">
                <div class="landing-visual-card-header">
                    <div class="landing-visual-mascot-row">
                        <?= render_mascot('thumbsup', 'brand-mascot', 'Debi Maskot') ?>
                        <div>
                            <h3 class="landing-visual-title">Daire Su Göleti</h3>
                            <span class="landing-visual-sub">Canlı Ekosistem Simülasyonu</span>
                        </div>
                    </div>
                    <span class="drop-motivation-badge">
                        <span>💧</span>
                        <strong>+35 DP</strong>
                    </span>
                </div>

                <div class="landing-pond-preview-wrap">
                    <div class="landing-floating-pill">
                        <span>🌱</span>
                        <span>%85 Doluluk Oranı</span>
                    </div>
                    <img src="<?= e(brand_asset_path('pond_full')) ?>" alt="Canlı Debi Su Göleti" class="landing-pond-preview-img">
                </div>

                <div class="landing-visual-stats">
                    <div class="landing-mini-stat">
                        <strong>94.2 L</strong>
                        <span>Kişi Başı Günlük</span>
                    </div>
                    <div class="landing-mini-stat">
                        <strong style="color: var(--brand-green-dark);">%24 Azalım</strong>
                        <span>Geçen Aya Göre</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- NASIL ÇALIŞIR? (3 BASİT ADIMDA DEBİ) -->
    <section class="landing-section-alt" id="nasil-calisir">
        <div class="landing-section landing-section-inner">
            <div class="landing-section-header">
                <span class="landing-section-kicker">3 BASİT ADIMDA BAŞLAYIN</span>
                <h2 class="landing-section-title">Su Tasarrufu Nasıl Sosyal Bir Harekete Dönüşüyor?</h2>
                <p class="landing-section-desc">
                    Debi, karmaşık fatura tablolarını ve sıkıcı sayaç okumalarını mahalle içi dinamik bir dayanışma oyununa çevirir.
                </p>
            </div>

            <div class="landing-steps-grid">
                <!-- Adım 1 -->
                <div class="landing-step-card">
                    <div class="landing-step-number">1</div>
                    <div class="landing-step-icon">📸</div>
                    <h3 class="landing-step-title">Faturanı Yapay Zekaya Okut</h3>
                    <p class="landing-step-desc">
                        Aylık İSKİ veya yerel su idaresi faturanın fotoğrafını çekip yükle. Gemini 3.8 Flash, faturadaki m³ ve fatura bedelini saniyeler içinde hatasız okusun.
                    </p>
                </div>

                <!-- Adım 2 -->
                <div class="landing-step-card">
                    <div class="landing-step-number">2</div>
                    <div class="landing-step-icon">💧</div>
                    <h3 class="landing-step-title">Göletini Doldur, DP Kazan</h3>
                    <p class="landing-step-desc">
                        Hane nüfusuna göre kişi başı tüketimin hesaplansın. 110 litrelik ideal eşiğin altında kaldıkça göletin canlansın, hanene Debi Puanı (DP) aksın.
                    </p>
                </div>

                <!-- Adım 3 -->
                <div class="landing-step-card">
                    <div class="landing-step-number">3</div>
                    <div class="landing-step-icon">🤝</div>
                    <h3 class="landing-step-title">İmeceye Katıl &amp; Ödülünü Al</h3>
                    <p class="landing-step-desc">
                        Mahallede fidan dikimi ve park temizliği gibi fiziksel etkinliklerde yer al. Biriken puanlarını anlaşmalı yerel esnafta gerçek indirimlere çevir.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- ÖZELLİKLER GRID -->
    <section class="landing-section" id="ozellikler">
        <div class="landing-section-header">
            <span class="landing-section-kicker">GÜÇLÜ ÖZELLİKLER</span>
            <h2 class="landing-section-title">Hem Gezegeni Hem Cebinizi Koruyan Araçlar</h2>
            <p class="landing-section-desc">
                Apartman yöneticilerinden bireysel hanelere kadar herkes için tasarlanmış şeffaf ve güvenilir modüller.
            </p>
        </div>

        <div class="landing-features-grid">
            <!-- Kart 1 -->
            <div class="landing-feature-card">
                <div class="landing-feature-icon-wrap">
                    <span>🤖</span>
                </div>
                <h3 class="landing-feature-title">Gemini AI Fatura Okuyucu</h3>
                <p class="landing-feature-desc">
                    Karmaşık faturalarla uğraşmayın. Gelişmiş multimodal yapay zeka sayacınızı analiz eder, manuel veri girişine gerek bırakmaz.
                </p>
            </div>

            <!-- Kart 2 -->
            <div class="landing-feature-card">
                <div class="landing-feature-icon-wrap icon-green">
                    <span>🌱</span>
                </div>
                <h3 class="landing-feature-title">Canlı Gölet Oyunlaştırması</h3>
                <p class="landing-feature-desc">
                    Tasarrufunuz soyut bir grafik değil, canlı bir gölet! Kurumuş topraktan nilüferli ve balıklı berrak gölete geçişin keyfini yaşayın.
                </p>
            </div>

            <!-- Kart 3 -->
            <div class="landing-feature-card">
                <div class="landing-feature-icon-wrap icon-amber">
                    <span>🏆</span>
                </div>
                <h3 class="landing-feature-title">Apartman &amp; Mahalle Ligleri</h3>
                <p class="landing-feature-desc">
                    Apartmanınız mahallenin en tasarruflu binası olabilir mi? Komşularınızla tatlı bir yarışa girin, apartman hedeflerine birlikte ulaşın.
                </p>
            </div>

            <!-- Kart 4 -->
            <div class="landing-feature-card">
                <div class="landing-feature-icon-wrap">
                    <span>🌲</span>
                </div>
                <h3 class="landing-feature-title">Fiziksel İmece &amp; Etkinlikler</h3>
                <p class="landing-feature-desc">
                    Sadece dijital değil, sokakta da birlikteyiz! Çevre temizliği ve yağmur suyu hasadı çalışmalarına katılın, fotoğrafla DP kazanın.
                </p>
            </div>

            <!-- Kart 5 -->
            <div class="landing-feature-card">
                <div class="landing-feature-icon-wrap icon-green">
                    <span>☕</span>
                </div>
                <h3 class="landing-feature-title">Yerel Mağaza İndirimleri</h3>
                <p class="landing-feature-desc">
                    Kazandığınız Debi Puanları boşa gitmez. Mahallenizdeki fırın, kafe ve tesisatçılarda geçerli özel kuponlar ve indirimler elde edin.
                </p>
            </div>

            <!-- Kart 6 -->
            <div class="landing-feature-card">
                <div class="landing-feature-icon-wrap icon-amber">
                    <span>📣</span>
                </div>
                <h3 class="landing-feature-title">Acil Su &amp; Arıza Uyarıları</h3>
                <p class="landing-feature-desc">
                    Apartmanda vana mı arızalandı? Mahallede su kesintisi mi var? Komşularınızı hemen haberdar edin, sorunları el birliğiyle çözün.
                </p>
            </div>
        </div>
    </section>

    <!-- GÖLET SİSTEMİ VİTRİNİ -->
    <section class="landing-section-alt" id="golet-sistemi">
        <div class="landing-section landing-section-inner">
            <div class="landing-section-header">
                <span class="landing-section-kicker">GÖLET EKOSİSTEMİ</span>
                <h2 class="landing-section-title">Her Damla Bir Yaşama Dönüşür</h2>
                <p class="landing-section-desc">
                    Tasarruf seviyenize göre hanenizin göleti anlık olarak değişir. Ailenizle birlikte göleti yeşertin!
                </p>
            </div>

            <div class="landing-pond-stages-grid">
                <div class="landing-pond-stage-item">
                    <img src="<?= e(brand_asset_path('pond_dry')) ?>" alt="Kurumuş Gölet">
                    <span class="landing-pond-stage-badge" style="background:#fee2e2; color:#b91c1c;">1. Aşama</span>
                    <h4>Kurumuş Gölet</h4>
                    <p>Yüksek tüketim ve tasarrufa henüz başlanmamış başlangıç aşaması.</p>
                </div>

                <div class="landing-pond-stage-item">
                    <img src="<?= e(brand_asset_path('pond_half')) ?>" alt="Yarı Dolu Gölet">
                    <span class="landing-pond-stage-badge" style="background:#fef3c7; color:#b45309;">2. Aşama</span>
                    <h4>Canlanan Gölet</h4>
                    <p>Tasarruf adımlarıyla suyun geri döndüğü ve yeşilliklerin başladığı evre.</p>
                </div>

                <div class="landing-pond-stage-item">
                    <img src="<?= e(brand_asset_path('pond_full')) ?>" alt="Canlı Gölet">
                    <span class="landing-pond-stage-badge" style="background:#dcfce7; color:#15803d;">3. Aşama</span>
                    <h4>Zengin Su Göleti</h4>
                    <p>Sürdürülebilir su bilinci ve maksimum Debi Puanı kazanımı.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ETKİNLİKLER VE İMECE BÖLÜMÜ -->
    <section class="landing-section" id="imece-etkinlikler">
        <div class="landing-section-header">
            <span class="landing-section-kicker">MAHALLE DAYANIŞMASI</span>
            <h2 class="landing-section-title">Klavye Başında Değil, Mahallede Birlikteyiz</h2>
            <p class="landing-section-desc">
                Debi kullanıcıları mahallelerini güzelleştirmek için fiziksel çalışmalarda buluşur.
            </p>
        </div>

        <div class="landing-steps-grid" style="grid-template-columns: repeat(3, 1fr);">
            <div class="landing-step-card">
                <div class="landing-step-icon">🌳</div>
                <h3 class="landing-step-title">Park &amp; Koruluk İmecesi</h3>
                <p class="landing-step-desc">
                    Mahalle sakinleriyle birlikte çevre temizliği ve yeşil alan bakımı yapın, komşuluk bağlarınızı güçlendirin.
                </p>
            </div>

            <div class="landing-step-card">
                <div class="landing-step-icon">🌧️</div>
                <h3 class="landing-step-title">Yağmur Suyu Hasadı</h3>
                <p class="landing-step-desc">
                    Apartman bahçelerinde yağmur suyu biriktirme ve damla sulama projeleri geliştirerek şebeke suyunu koruyun.
                </p>
            </div>

            <div class="landing-step-card">
                <div class="landing-step-icon">📸</div>
                <h3 class="landing-step-title">AI Kanıt Doğrulaması</h3>
                <p class="landing-step-desc">
                    Etkinlik sonrası çektiğiniz çalışma fotoğrafı Gemini AI tarafından analiz edilir ve hak edilen Debi Puanı otomatik yüklenir.
                </p>
            </div>
        </div>

        <!-- BÜYÜK CTA ÇAĞRI BANDI -->
        <div class="landing-cta-banner">
            <div class="landing-cta-content">
                <h2>Gelecek Nesillere Bir Damla Bırakmaya Hazır mısınız?</h2>
                <p>
                    Apartmanınızla veya bireysel olarak bugün Debi'ye katılın. Faturanızı yapay zeka ile okutun, mahallenizin lideri olun!
                </p>
            </div>
            <div class="landing-cta-actions">
                <?php if (!empty($currentUser)): ?>
                    <a href="feed.php" class="btn btn-primary">Akışa Geç 💧</a>
                <?php else: ?>
                    <a href="register.php" class="btn btn-primary">Hemen Ücretsiz Katıl</a>
                    <a href="login.php" class="btn btn-outline">Giriş Yap</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="site-footer" style="margin-top: 40px;">
        <div class="footer-inner">
            <span><strong>debi</strong> — Mahalle ve apartman ölçekli sosyal su tasarrufu ağı &copy; <?= date('Y') ?></span>
            <span class="footer-links">
                <a href="<?= !empty($currentUser) ? 'feed.php' : 'login.php' ?>">Sosyal Akış</a> ·
                <a href="events.php">Etkinlikler</a> ·
                <a href="water.php">Faturalar</a> ·
                <a href="legal.php?doc=kvkk">KVKK</a> ·
                <a href="legal.php?doc=privacy">Gizlilik</a> ·
                <a href="legal.php?doc=terms">Kullanım Şartları</a>
            </span>
        </div>
    </footer>

</body>
</html>
