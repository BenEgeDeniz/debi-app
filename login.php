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
        <div class="auth-kicker">YEREL SU TASARRUFU AĞI</div>
        <h1>Mahallenizde ve apartmanınızda suyun debisini birlikte düşürün.</h1>
        <p>
            Debi; apartman dairelerini, müstakil evleri ve mahallede bireysel olarak yer almak isteyen haneleri ortak bir su tasarrufu ağında buluşturur.
        </p>
        <ul class="auth-feature-list">
            <li><strong>Mahalle Çatısı:</strong> Tüm apartmanlar ve bireysel katılımcılar kendi mahallesinde buluşur.</li>
            <li><strong>Adil Kıyaslama:</strong> Hane kişi sayısına göre kişi başı günlük litre hesabı yapılır.</li>
            <li><strong>Apartman &amp; Bireysel Esneklik:</strong> Apartmanınız sistemde yoksa mahallenize bireysel katılabilir, apartmanınız katıldığında davet koduyla bağlanabilirsiniz.</li>
        </ul>
    </div>

    <div class="auth-card">
        <h2 class="auth-card-title">Hesabınıza Giriş Yapın</h2>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
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
            Henüz hesabınız yok mu? <a href="register.php">Mahallenize veya Apartmanınıza Katılın</a>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

