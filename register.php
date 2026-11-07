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
            <h1 class="auth-card-title">Debi'ye Katıl</h1>
        </div>

        <!-- Katılım Modu Seçici Sekmeler -->
        <div class="mode-tabs" role="tablist">
            <button type="button" class="mode-tab <?= $mode === 'individual' ? 'active' : '' ?>" data-mode-target="individual">
                <strong>Bireysel / Müstakil</strong>
                <span>Müstakil ev veya bireysel katılım</span>
            </button>
            <button type="button" class="mode-tab <?= $mode === 'apartment_invite' ? 'active' : '' ?>" data-mode-target="apartment_invite">
                <strong>Davet Kodu ile</strong>
                <span>Apartman veya daire koduyla</span>
            </button>
            <button type="button" class="mode-tab <?= $mode === 'apartment_manager' ? 'active' : '' ?>" data-mode-target="apartment_manager">
                <strong>Apartman Yöneticisi</strong>
                <span>Apartmanı kur</span>
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
                <h3 class="form-section-title">Kullanıcı &amp; Hane</h3>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="full_name">Ad Soyad</label>
                        <input type="text" id="full_name" name="full_name" required
                               value="<?= e($_POST['full_name'] ?? '') ?>" placeholder="Örn: Ahmet Yılmaz">
                    </div>
                    <div class="form-group">
                        <label for="email">E-posta</label>
                        <input type="email" id="email" name="email" required
                               value="<?= e($_POST['email'] ?? '') ?>" placeholder="ornek@eposta.com">
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="password">Şifre (Min. 6 karakter)</label>
                        <input type="password" id="password" name="password" required placeholder="••••••">
                    </div>
                    <div class="form-group">
                        <label for="resident_count">Hanedeki Kişi Sayısı</label>
                        <input type="number" id="resident_count" name="resident_count" min="1" max="20" required
                               value="<?= e($_POST['resident_count'] ?? '3') ?>">
                    </div>
                </div>
            </div>

            <!-- MOD 1 ve MOD 3 İçin Ortak Mahalle Seçimi (Standart Liste) -->
            <?php
            $provincesList = [
                1 => 'Adana', 2 => 'Adıyaman', 3 => 'Afyonkarahisar', 4 => 'Ağrı', 68 => 'Aksaray',
                5 => 'Amasya', 6 => 'Ankara', 7 => 'Antalya', 75 => 'Ardahan', 8 => 'Artvin',
                9 => 'Aydın', 10 => 'Balıkesir', 74 => 'Bartın', 72 => 'Batman', 69 => 'Bayburt',
                11 => 'Bilecik', 12 => 'Bingöl', 13 => 'Bitlis', 14 => 'Bolu', 15 => 'Burdur',
                16 => 'Bursa', 17 => 'Çanakkale', 18 => 'Çankırı', 19 => 'Çorum', 20 => 'Denizli',
                21 => 'Diyarbakır', 81 => 'Düzce', 22 => 'Edirne', 23 => 'Elazığ', 24 => 'Erzincan',
                25 => 'Erzurum', 26 => 'Eskişehir', 27 => 'Gaziantep', 28 => 'Giresun', 29 => 'Gümüşhane',
                30 => 'Hakkari', 31 => 'Hatay', 76 => 'Iğdır', 32 => 'Isparta', 34 => 'İstanbul',
                35 => 'İzmir', 46 => 'Kahramanmaraş', 78 => 'Karabük', 70 => 'Karaman', 36 => 'Kars',
                37 => 'Kastamonu', 38 => 'Kayseri', 71 => 'Kırıkkale', 39 => 'Kırklareli', 40 => 'Kırşehir',
                79 => 'Kilis', 41 => 'Kocaeli', 42 => 'Konya', 43 => 'Kütahya', 44 => 'Malatya',
                45 => 'Manisa', 47 => 'Mardin', 33 => 'Mersin', 48 => 'Muğla', 49 => 'Muş',
                50 => 'Nevşehir', 51 => 'Niğde', 52 => 'Ordu', 80 => 'Osmaniye', 53 => 'Rize',
                54 => 'Sakarya', 55 => 'Samsun', 56 => 'Siirt', 57 => 'Sinop', 58 => 'Sivas',
                63 => 'Şanlıurfa', 73 => 'Şırnak', 59 => 'Tekirdağ', 60 => 'Tokat', 61 => 'Trabzon',
                62 => 'Tunceli', 64 => 'Uşak', 65 => 'Van', 77 => 'Yalova', 66 => 'Yozgat', 67 => 'Zonguldak',
            ];
            $postedCity = $_POST['city'] ?? '';
            ?>
            <div class="form-section mode-panel" id="locationSection"
                 style="<?= $mode === 'apartment_invite' ? 'display:none;' : '' ?>"
                 data-prefill-city="<?= e($postedCity) ?>"
                 data-prefill-district="<?= e($_POST['district'] ?? '') ?>"
                 data-prefill-neighborhood="<?= e($_POST['neighborhood_name'] ?? '') ?>">
                <h3 class="form-section-title">Mahalle Konumu</h3>

                <div class="form-grid-3" id="autoLocationInputs">
                    <div class="form-group">
                        <label for="citySelect">İl</label>
                        <select id="citySelect" name="city" class="loc-select">
                            <option value="">İl Seçiniz...</option>
                            <?php foreach ($provincesList as $plateId => $provName): ?>
                                <option value="<?= e($provName) ?>" data-id="<?= (int) $plateId ?>"
                                    <?= ($postedCity !== '' && mb_strtolower($postedCity, 'UTF-8') === mb_strtolower($provName, 'UTF-8')) ? 'selected' : '' ?>>
                                    <?= e($provName) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="districtSelect">İlçe</label>
                        <select id="districtSelect" name="district" class="loc-select" disabled>
                            <option value="">Önce il seçin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="neighborhoodSelect">Mahalle</label>
                        <select id="neighborhoodSelect" name="neighborhood_name" class="loc-select" disabled>
                            <option value="">Önce ilçe seçin</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- MOD 1: Bireysel / Müstakil Katılım Alanları -->
            <div class="form-section mode-specific" data-mode-box="individual"
                 style="<?= $mode === 'individual' ? '' : 'display:none;' ?>">
                <h3 class="form-section-title">Konut Türü</h3>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="individual_housing_type">Tür</label>
                        <select id="individual_housing_type" name="individual_housing_type">
                            <option value="individual_unit" <?= ($_POST['individual_housing_type'] ?? '') === 'individual_unit' ? 'selected' : '' ?>>
                                Apartman Dairesi (Bireysel)
                            </option>
                            <option value="detached_house" <?= ($_POST['individual_housing_type'] ?? '') === 'detached_house' ? 'selected' : '' ?>>
                                Müstakil Ev
                            </option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="ind_unit_label">Daire / Hane Tanımı (İsteğe bağlı)</label>
                        <input type="text" id="ind_unit_label" name="unit_label"
                               value="<?= e($_POST['unit_label'] ?? '') ?>"
                               placeholder="Örn: Daire 2"
                               <?= $mode === 'individual' ? '' : 'disabled' ?>>
                    </div>
                </div>
            </div>

            <!-- MOD 2: Davet Kodu ile Apartmana veya Daireye Katılım Alanları -->
            <div class="form-section mode-specific" data-mode-box="apartment_invite"
                 style="<?= $mode === 'apartment_invite' ? '' : 'display:none;' ?>">
                <h3 class="form-section-title">Davet Kodu</h3>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="invite_code">Apartman (DEBI-...) veya Daire (DAIRE-...) Kodu</label>
                        <input type="text" id="invite_code" name="invite_code"
                               value="<?= e($prefillInvite) ?>" placeholder="Örn: DEBI-7K4M2 veya DAIRE-9P2X4">
                    </div>
                    <div class="form-group">
                        <label for="inv_unit_label">Daire No (Apartman kodu için)</label>
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
                <h3 class="form-section-title">Apartman Bilgileri</h3>
                <div class="form-grid-3">
                    <div class="form-group">
                        <label for="apartment_name">Apartman Adı</label>
                        <input type="text" id="apartment_name" name="apartment_name"
                               value="<?= e($_POST['apartment_name'] ?? '') ?>" placeholder="Örn: Çınar Apartmanı">
                    </div>
                    <div class="form-group">
                        <label for="total_units">Daire Sayısı</label>
                        <input type="number" id="total_units" name="total_units" min="2" max="500"
                               value="<?= e($_POST['total_units'] ?? '12') ?>">
                    </div>
                    <div class="form-group">
                        <label for="mgr_unit_label">Daire Numaranız</label>
                        <input type="text" id="mgr_unit_label" name="unit_label"
                               value="<?= e($_POST['unit_label'] ?? 'Daire 1') ?>"
                               placeholder="Daire 1"
                               <?= $mode === 'apartment_manager' ? '' : 'disabled' ?>>
                    </div>
                </div>
                <div class="form-group">
                    <label for="address_detail">Sokak / No (İsteğe bağlı)</label>
                    <input type="text" id="address_detail" name="address_detail"
                           value="<?= e($_POST['address_detail'] ?? '') ?>" placeholder="Örn: Çınar Sok. No: 14">
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Kayıt Ol</button>
        </form>

        <div class="auth-card-footer">
            Zaten hesabınız var mı? <a href="login.php">Giriş Yapın</a>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

