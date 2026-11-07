<?php
/**
 * DEBI - Kayıt Sayfası (3 Katılım Modu)
 * 1) Bireysel / Müstakil Ev (Mahallede bireysel katılım)
 * 2) Davet Koduyla Apartmana Katılım
 * 3) Apartman Yöneticisi (Yeni Apartman Oluşturma)
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/modules/auth/auth_service.php';

if ($currentUser) {
    redirect('index.php');
}

$error = null;
$mode  = $_POST['register_mode'] ?? ($_GET['mode'] ?? 'individual');
if (!in_array($mode, ['individual', 'apartment_invite', 'apartment_manager'], true)) {
    $mode = 'individual';
}

$prefillInvite = strtoupper(trim($_GET['code'] ?? ($_POST['invite_code'] ?? '')));
if ($prefillInvite !== '' && !isset($_POST['register_mode'])) {
    $mode = 'apartment_invite';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Oturum doğrulaması başarısız oldu, lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        $res = register_user($_POST);
        if ($res['ok']) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $res['user_id'];
            set_flash('success', 'Debi ağına hoş geldiniz! Hane ve mahalle bağlantınız oluşturuldu.');
            redirect('index.php');
        } else {
            $error = $res['error'];
        }
    }
}

$pageTitle = 'Kayıt Ol — Debi';
require_once __DIR__ . '/includes/header.php';
?>

<main class="auth-layout auth-layout-wide">
    <div class="auth-card auth-card-wide">
        <div class="auth-header-block">
            <h1 class="auth-card-title">Debi Ağına Katılın</h1>
            <p class="auth-card-subtitle">
                Nasıl katılmak istediğinizi seçin. Apartmanınız henüz sistemde yoksa <strong>Bireysel / Müstakil</strong> seçeneğiyle doğrudan mahallenizde yer alabilirsiniz.
            </p>
        </div>

        <!-- Katılım Modu Seçici Sekmeler -->
        <div class="mode-tabs" role="tablist">
            <button type="button" class="mode-tab <?= $mode === 'individual' ? 'active' : '' ?>" data-mode-target="individual">
                <strong>1. Bireysel / Müstakil</strong>
                <span>Mahallede bireysel yer al</span>
            </button>
            <button type="button" class="mode-tab <?= $mode === 'apartment_invite' ? 'active' : '' ?>" data-mode-target="apartment_invite">
                <strong>2. Apartman Davetli</strong>
                <span>Davet kodu ile dairene katıl</span>
            </button>
            <button type="button" class="mode-tab <?= $mode === 'apartment_manager' ? 'active' : '' ?>" data-mode-target="apartment_manager">
                <strong>3. Apartman Yöneticisi</strong>
                <span>Apartmanını mahalleye kaydet</span>
            </button>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="register.php" class="form-stack" id="registerForm">
            <?= csrf_field() ?>
            <input type="hidden" name="register_mode" id="registerModeInput" value="<?= e($mode) ?>">

            <!-- Ortak Kişisel Bilgiler -->
            <div class="form-section">
                <h3 class="form-section-title">Kullanıcı ve Hane Bilgileri</h3>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="full_name">Ad Soyad</label>
                        <input type="text" id="full_name" name="full_name" required
                               value="<?= e($_POST['full_name'] ?? '') ?>" placeholder="Örn: Ahmet Yılmaz">
                    </div>
                    <div class="form-group">
                        <label for="email">E-posta Adresi</label>
                        <input type="email" id="email" name="email" required
                               value="<?= e($_POST['email'] ?? '') ?>" placeholder="ornek@eposta.com">
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="password">Şifre (En az 6 karakter)</label>
                        <input type="password" id="password" name="password" required placeholder="••••••">
                    </div>
                    <div class="form-group">
                        <label for="resident_count">Hanede Yaşayan Kişi Sayısı</label>
                        <input type="number" id="resident_count" name="resident_count" min="1" max="20" required
                               value="<?= e($_POST['resident_count'] ?? '3') ?>">
                        <span class="field-hint">Kişi başı günlük su tüketimi hesabı için kullanılır.</span>
                    </div>
                </div>
            </div>

            <!-- MOD 1 ve MOD 3 İçin Ortak Mahalle Seçimi (Otomatik Çekilen Liste) -->
            <div class="form-section mode-panel" id="locationSection"
                 style="<?= $mode === 'apartment_invite' ? 'display:none;' : '' ?>"
                 data-prefill-city="<?= e($_POST['city'] ?? '') ?>"
                 data-prefill-district="<?= e($_POST['district'] ?? '') ?>"
                 data-prefill-neighborhood="<?= e($_POST['neighborhood_name'] ?? '') ?>">
                <div class="location-header-row">
                    <div>
                        <h3 class="form-section-title">Mahalle Konumu</h3>
                        <p class="section-note">
                            İl, ilçe ve mahallenizi seçin. Seçtiğiniz mahalledeki apartmanlar ve komşularınızla otomatik eşleşirsiniz.
                        </p>
                    </div>
                    <button type="button" class="btn-link-subtle" id="toggleManualLocation">
                        Listede bulamadım, elle yaz
                    </button>
                </div>

                <!-- Otomatik İl > İlçe > Mahalle Seçim Menüleri -->
                <div class="form-grid-3" id="autoLocationInputs">
                    <div class="form-group">
                        <label for="citySelect">İl Seçin</label>
                        <select id="citySelect" name="city" class="loc-select">
                            <option value="">İller yükleniyor...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="districtSelect">İlçe Seçin</label>
                        <select id="districtSelect" name="district" class="loc-select" disabled>
                            <option value="">Önce il seçin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="neighborhoodSelect">Mahalle Seçin</label>
                        <select id="neighborhoodSelect" name="neighborhood_name" class="loc-select" disabled>
                            <option value="">Önce ilçe seçin</option>
                        </select>
                    </div>
                </div>

                <!-- İsteğe Bağlı Manuel Giriş Alanları (Gerektiğinde Açılır) -->
                <div class="form-grid-3" id="manualLocationInputs" style="display: none;">
                    <div class="form-group">
                        <label for="manualCity">İl (Elle Giriş)</label>
                        <input type="text" id="manualCity" placeholder="Örn: İstanbul" disabled>
                    </div>
                    <div class="form-group">
                        <label for="manualDistrict">İlçe (Elle Giriş)</label>
                        <input type="text" id="manualDistrict" placeholder="Örn: Kadıköy" disabled>
                    </div>
                    <div class="form-group">
                        <label for="manualNeighborhood">Mahalle (Elle Giriş)</label>
                        <input type="text" id="manualNeighborhood" placeholder="Örn: Moda Mah." disabled>
                    </div>
                </div>
            </div>

            <!-- MOD 1: Bireysel / Müstakil Katılım Alanları -->
            <div class="form-section mode-specific" data-mode-box="individual"
                 style="<?= $mode === 'individual' ? '' : 'display:none;' ?>">
                <h3 class="form-section-title">Bireysel / Müstakil Konut Detayı</h3>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="individual_housing_type">Konut Türünüz</label>
                        <select id="individual_housing_type" name="individual_housing_type">
                            <option value="individual_unit" <?= ($_POST['individual_housing_type'] ?? '') === 'individual_unit' ? 'selected' : '' ?>>
                                Apartman Dairesi (Apartmanım henüz katılmadı, bireysel katılıyorum)
                            </option>
                            <option value="detached_house" <?= ($_POST['individual_housing_type'] ?? '') === 'detached_house' ? 'selected' : '' ?>>
                                Müstakil Ev (Mahallede müstakil hane)
                            </option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="ind_unit_label">Hane / Ev Tanımı (İsteğe bağlı)</label>
                        <input type="text" id="ind_unit_label" name="unit_label"
                               value="<?= e($_POST['unit_label'] ?? '') ?>"
                               placeholder="Örn: Gül Sokak No:8 veya Bireysel Daire"
                               <?= $mode === 'individual' ? '' : 'disabled' ?>>
                    </div>
                </div>
            </div>

            <!-- MOD 2: Davet Kodu ile Apartmana Katılım Alanları -->
            <div class="form-section mode-specific" data-mode-box="apartment_invite"
                 style="<?= $mode === 'apartment_invite' ? '' : 'display:none;' ?>">
                <h3 class="form-section-title">Apartman Davet Bilgisi</h3>
                <p class="section-note">
                    Apartman yöneticinizin paylaştığı genel apartman kodunu (<code>DEBI-XXXXX</code>) veya dairenize özel üretilen davet kodunu (<code>DAIRE-XXXXX</code>) girin. Apartmanınızın bağlı olduğu mahalleye otomatik dahil olursunuz.
                </p>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="invite_code">Davet Kodu</label>
                        <input type="text" id="invite_code" name="invite_code"
                               value="<?= e($prefillInvite) ?>" placeholder="Örn: DEBI-7K4M2">
                    </div>
                    <div class="form-group">
                        <label for="inv_unit_label">Daire Numaranız</label>
                        <input type="text" id="inv_unit_label" name="unit_label"
                               value="<?= e($_POST['unit_label'] ?? '') ?>"
                               placeholder="Örn: Daire 5"
                               <?= $mode === 'apartment_invite' ? '' : 'disabled' ?>>
                    </div>
                </div>
            </div>

            <!-- MOD 3: Apartman Yöneticisi Alanları -->
            <div class="form-section mode-specific" data-mode-box="apartment_manager"
                 style="<?= $mode === 'apartment_manager' ? '' : 'display:none;' ?>">
                <h3 class="form-section-title">Apartman Bilgileri (Yönetici)</h3>
                <p class="section-note">
                    Apartmanınızı mahallenize kaydedin. Kayıt sonrasında tüm dairelere gönderebileceğiniz davet kodunuz hazır olacaktır.
                </p>
                <div class="form-grid-3">
                    <div class="form-group">
                        <label for="apartment_name">Apartman / Site Adı</label>
                        <input type="text" id="apartment_name" name="apartment_name"
                               value="<?= e($_POST['apartment_name'] ?? '') ?>" placeholder="Örn: Çınar Apartmanı">
                    </div>
                    <div class="form-group">
                        <label for="total_units">Toplam Daire Sayısı</label>
                        <input type="number" id="total_units" name="total_units" min="2" max="500"
                               value="<?= e($_POST['total_units'] ?? '12') ?>">
                    </div>
                    <div class="form-group">
                        <label for="mgr_unit_label">Sizin Daire Numaranız</label>
                        <input type="text" id="mgr_unit_label" name="unit_label"
                               value="<?= e($_POST['unit_label'] ?? 'Daire 1') ?>"
                               placeholder="Örn: Daire 1"
                               <?= $mode === 'apartment_manager' ? '' : 'disabled' ?>>
                    </div>
                </div>
                <div class="form-group">
                    <label for="address_detail">Sokak / Bina No (İsteğe bağlı)</label>
                    <input type="text" id="address_detail" name="address_detail"
                           value="<?= e($_POST['address_detail'] ?? '') ?>" placeholder="Örn: Bahariye Cad. Çınar Sok. No: 14">
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Kaydı Tamamla ve Başla</button>
        </form>

        <div class="auth-card-footer">
            Zaten hesabınız var mı? <a href="login.php">Giriş Yapın</a>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

