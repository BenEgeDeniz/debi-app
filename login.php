<?php
/**
 * DEBI - Giriş Sayfası
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/modules/auth/auth_service.php';

if ($currentUser) {
    redirect('index.php');
}

$error = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Oturum doğrulaması başarısız oldu, lütfen tekrar deneyin.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $result   = login_user($email, $password);

        if ($result['ok']) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $result['user_id'];
            redirect('index.php');
        } else {
            $error = $result['error'];
        }
    }
}

$pageTitle = 'Giriş Yap — Debi';
require_once __DIR__ . '/includes/header.php';
?>

<main class="auth-layout">
    <div class="auth-intro">
        <div class="auth-mascot-hero">
            <svg width="84" height="84" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M22 3C22 3 7 18.2 7 28.5C7 36.5 13.7 42 22 42C30.3 42 37 36.5 37 28.5C37 18.2 22 3 22 3Z" fill="#1CB0F6" stroke="#118AB2" stroke-width="2.5"/>
                <path d="M13 24C12 19 16 12 20 8" stroke="#72D6FF" stroke-width="2.5" stroke-linecap="round"/>
                <circle cx="16.5" cy="27" r="2.3" fill="#1D3557"/>
                <circle cx="27.5" cy="27" r="2.3" fill="#1D3557"/>
                <circle cx="15.8" cy="26.2" r="0.8" fill="#FFFFFF"/>
                <circle cx="26.8" cy="26.2" r="0.8" fill="#FFFFFF"/>
                <ellipse cx="13.5" cy="30" rx="2.2" ry="1.2" fill="#FF8FA3"/>
                <ellipse cx="30.5" cy="30" rx="2.2" ry="1.2" fill="#FF8FA3"/>
                <path d="M18.5 31.2C19.6 33 24.4 33 25.5 31.2" stroke="#1D3557" stroke-width="2.2" stroke-linecap="round"/>
            </svg>
            <span class="auth-bonus-bubble">💧 Görevleri yap, Damla Puanı (DP) topla!</span>
        </div>

        <div class="auth-kicker">EĞLENCELİ KOMŞU SU AĞI</div>
        <h1>Mahallende ve apartmanında su tasarrufunu oyuna dönüştür!</h1>
        <p>
            Debi; apartman dairelerini, müstakil evleri ve mahallede bireysel yer almak isteyen komşuları neşeli bir su tasarrufu yarışında buluşturur.
        </p>
        <div class="auth-feature-cards">
            <div class="auth-feat-item">
                <span class="auth-feat-icon">🏘️</span>
                <div>
                    <strong>Ortak Mahalle Çatısı</strong>
                    <span>Apartmanlar ve bireysel evler kendi mahallesinde buluşur.</span>
                </div>
            </div>
            <div class="auth-feat-item">
                <span class="auth-feat-icon">🎯</span>
                <div>
                    <strong>Günlük Görevler &amp; Damla Puanı (DP)</strong>
                    <span>Kısa duş al, perlatör tak, sayaç gir; puanları ve rozetleri topla!</span>
                </div>
            </div>
            <div class="auth-feat-item">
                <span class="auth-feat-icon">🏆</span>
                <div>
                    <strong>Tatlı Komşu Rekabeti</strong>
                    <span>Kişi sayısına göre adil hesaplanan liderlik tablosunda zirveye çık!</span>
                </div>
            </div>
        </div>
    </div>

    <div class="auth-card">
        <h2 class="auth-card-title">👋 Tekrar Hoş Geldin!</h2>
        <p class="auth-card-subtitle">Hesabına giriş yap ve bugünkü su görevlerini tamamla.</p>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <span class="alert-icon">⚠️</span>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="post" action="login.php" class="form-stack">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="email">E-posta Adresi</label>
                <input type="email" id="email" name="email" value="<?= e($email) ?>" required placeholder="ornek@eposta.com">
            </div>

            <div class="form-group">
                <label for="password">Şifre</label>
                <input type="password" id="password" name="password" required placeholder="••••••">
            </div>

            <button type="submit" class="btn btn-primary btn-block">🚀 Giriş Yap</button>
        </form>

        <div class="auth-card-footer">
            Henüz hesabın yok mu? <a href="register.php">Hemen Katıl ve +50 DP Kazan!</a>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
