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
            <?= render_brand_logo('auth-hero-logo') ?>
            <?= render_mascot('smile', 'auth-hero-mascot', 'Debi Damla Maskotu') ?>
        </div>

        <h1>Su tasarrufunu sosyal ağa dönüştürün</h1>
        <p>
            Faturanızı yapay zeka ile okutun, arkadaşlarınız ve komşularınızla su tüketiminizi takip edin, daire göletinizi canlandırın.
        </p>
        <div class="auth-feature-cards">
            <div class="auth-feat-item">
                <div>
                    <strong>💧 Daire Göleti &amp; Damla Ödülleri</strong>
                </div>
            </div>
            <div class="auth-feat-item">
                <div>
                    <strong>📸 Yapay Zeka ile Fatura Okuma</strong>
                </div>
            </div>
            <div class="auth-feat-item">
                <div>
                    <strong>🏢 Apartman &amp; Arkadaş Akışı</strong>
                </div>
            </div>
        </div>
    </div>

    <div class="auth-card">
        <h2 class="auth-card-title">Giriş Yap</h2>

        <?php if ($error): ?>
            <div class="alert alert-error">
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

            <button type="submit" class="btn btn-primary btn-block">Giriş Yap</button>
        </form>

        <div class="auth-card-footer">
            Henüz hesabınız yok mu? <a href="register.php">Hemen Kayıt Olun</a>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
