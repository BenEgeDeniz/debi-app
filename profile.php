<?php
/**
 * DEBI - Hane ve Kullanıcı Profil Ayarları
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/modules/profile/profile_service.php';

$currentUser = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('error', 'Güvenlik doğrulaması başarısız oldu.');
        redirect('profile.php');
    }

    $res = update_user_and_household_profile($currentUser, $_POST);
    if ($res['ok']) {
        set_flash('success', 'Profil ve hane bilgileriniz güncellendi.');
    } else {
        set_flash('error', $res['error']);
    }
    redirect('profile.php');
}

$pageTitle = 'Hane & Profil — Debi';
$activeNav = 'profile';
require_once __DIR__ . '/includes/header.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/includes/sidebar_left.php'; ?>

    <main class="main-column">
        <section class="panel-card page-hero-card">
            <div class="profile-header-block">
                <span class="avatar-circle avatar-lg" style="background-color: <?= e($currentUser['avatar_color']) ?>;">
                    <?= e(user_initials($currentUser['full_name'])) ?>
                </span>
                <div>
                    <h1 class="page-title"><?= e($currentUser['full_name']) ?></h1>
                    <p class="page-subtitle">
                        <?= e($currentUser['neighborhood_name']) ?> (<?= e($currentUser['neighborhood_district'] . ' / ' . $currentUser['neighborhood_city']) ?>)
                        ·
                        <?php if (!empty($currentUser['apartment_id'])): ?>
                            <strong><?= e($currentUser['apartment_name']) ?></strong> — <?= e($currentUser['unit_label']) ?>
                        <?php else: ?>
                            <strong><?= e(housing_type_label($currentUser['housing_type'])) ?></strong> — <?= e($currentUser['unit_label']) ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </section>

        <section class="panel-card">
            <h2 class="section-title">Kişisel ve Hane Ayarları</h2>
            <p class="panel-desc">
                Hanede yaşayan kişi sayısını güncel tutmanız, mahalle ve apartman su tasarruf sıralamalarında kişi başı günlük litre hesabının doğru yapılmasını sağlar.
            </p>

            <form method="post" action="profile.php" class="form-stack">
                <?= csrf_field() ?>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="full_name">Ad Soyad</label>
                        <input type="text" id="full_name" name="full_name" required
                               value="<?= e($currentUser['full_name']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="email_readonly">E-posta Adresi</label>
                        <input type="email" id="email_readonly" value="<?= e($currentUser['email']) ?>" disabled>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="unit_label">Daire / Ev Tanımı</label>
                        <input type="text" id="unit_label" name="unit_label" required
                               value="<?= e($currentUser['unit_label']) ?>" placeholder="Örn: Daire 4 veya Müstakil No:12">
                    </div>
                    <div class="form-group">
                        <label for="resident_count">Hanede Yaşayan Kişi Sayısı</label>
                        <input type="number" id="resident_count" name="resident_count" min="1" max="20" required
                               value="<?= (int) $currentUser['resident_count'] ?>">
                    </div>
                </div>

                <?php if (empty($currentUser['apartment_id'])): ?>
                    <div class="form-group">
                        <label for="individual_housing_type">Bireysel Konut Türü</label>
                        <select id="individual_housing_type" name="individual_housing_type">
                            <option value="individual_unit" <?= $currentUser['housing_type'] === 'individual_unit' ? 'selected' : '' ?>>
                                Apartman Dairesi (Mahallede bireysel katılımcı)
                            </option>
                            <option value="detached_house" <?= $currentUser['housing_type'] === 'detached_house' ? 'selected' : '' ?>>
                                Müstakil Ev
                            </option>
                        </select>
                        <span class="field-hint">
                            Bir apartmanın davet kodu ile apartmana bağlanmak isterseniz <a href="apartment.php">Apartman Bağlantısı</a> sayfasını kullanabilirsiniz.
                        </span>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="bio">Kısa Not / Biyografi (İsteğe bağlı)</label>
                    <input type="text" id="bio" name="bio"
                           value="<?= e($currentUser['bio'] ?? '') ?>"
                           placeholder="Örn: Balkonda yağmur suyu biriktiriyoruz.">
                </div>

                <div>
                    <button type="submit" class="btn btn-primary">Bilgileri Güncelle</button>
                </div>
            </form>
        </section>
    </main>

    <?php require_once __DIR__ . '/includes/sidebar_right.php'; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

