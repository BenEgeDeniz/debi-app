<?php
/**
 * DEBI - Migration yönetim ekranı
 *
 * Migration'lar uygulama açılışında çalışmaz. Bu ekran geçici bir parola ile
 * korunur ve normal uygulama kullanıcı oturumuna bağlı değildir.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/modules/database/migration_service.php';

$error = null;
$results = [];
$migrationAccessPassword = '965edk';
$isUnlocked = !empty($_SESSION['migration_access_granted']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['access_password'])) {
    if (hash_equals($migrationAccessPassword, (string) $_POST['access_password'])) {
        $_SESSION['migration_access_granted'] = true;
        $isUnlocked = true;
    } else {
        $error = 'Geçersiz migration parolası.';
    }
}

if ($isUnlocked) {
    $pdo = db();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migrations'])) {
        if (!verify_csrf()) {
            $error = 'Geçersiz CSRF token.';
        } else {
            $from = filter_input(INPUT_POST, 'from', FILTER_VALIDATE_INT);
            $to = filter_input(INPUT_POST, 'to', FILTER_VALIDATE_INT);
            $force = isset($_POST['force']);

            if ($from !== false && $from !== null && $from < 1) {
                $error = 'Başlangıç sıra numarası geçersiz.';
            } elseif ($to !== false && $to !== null && $to < 1) {
                $error = 'Bitiş sıra numarası geçersiz.';
            } elseif ($from !== false && $to !== false && $from !== null && $to !== null && $from > $to) {
                $error = 'Başlangıç sıra numarası bitişten büyük olamaz.';
            } else {
                try {
                    $results = run_database_migrations(
                        $pdo,
                        $from ?: null,
                        $to ?: null,
                        $force
                    );
                } catch (Throwable $exception) {
                    $error = $exception->getMessage();
                }
            }
        }
    }

    try {
        $status = get_database_migration_status($pdo);
    } catch (Throwable $exception) {
        $status = [];
        $error ??= $exception->getMessage();
    }
} else {
    $status = [];
}

function migration_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DEBI Migration Yönetimi</title>
    <style>
        body { max-width: 900px; margin: 40px auto; padding: 0 20px; font: 16px system-ui, sans-serif; color: #17212b; }
        h1 { margin-bottom: 8px; }
        form, table { width: 100%; margin-top: 24px; }
        label { display: inline-block; margin: 0 16px 12px 0; }
        input { width: 100px; padding: 8px; }
        button { padding: 9px 16px; cursor: pointer; }
        .force { margin-top: 8px; }
        .error { padding: 12px; background: #fee2e2; color: #991b1b; }
        .ok { padding: 12px; background: #dcfce7; color: #166534; }
        table { border-collapse: collapse; }
        th, td { padding: 10px 8px; border-bottom: 1px solid #ddd; text-align: left; }
        .pending { color: #92400e; }
        .applied { color: #166534; }
        .changed { color: #991b1b; font-weight: 700; }
    </style>
</head>
<body>
    <h1>Migration yönetimi</h1>
    <?php if (!$isUnlocked): ?>
        <p>Devam etmek için migration parolasını girin.</p>
        <?php if ($error !== null): ?>
            <div class="error"><?= migration_html($error) ?></div>
        <?php endif; ?>
        <form method="post">
            <label>Migration parolası
                <input type="password" name="access_password" required autofocus>
            </label>
            <button type="submit">Devam et</button>
        </form>
    <?php else: ?>
    <p>Migration'lar sıra numarasına göre çalışır ve yalnızca bir kez uygulanır.</p>

    <?php if ($error !== null): ?>
        <div class="error"><?= migration_html($error) ?></div>
    <?php endif; ?>
    <?php if ($results): ?>
        <div class="ok">
            <?php foreach ($results as $result): ?>
                <?= migration_html($result['file']) ?>: <?= migration_html($result['status']) ?><br>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="run_migrations" value="1">
        <label>Başlangıç sıra no
            <input type="number" name="from" min="1" placeholder="İlk">
        </label>
        <label>Bitiş sıra no
            <input type="number" name="to" min="1" placeholder="Son">
        </label>
        <label class="force">
            <input type="checkbox" name="force" value="1">
            Force (yalnızca istisnai checksum durumları için)
        </label>
        <br>
        <button type="submit">Migration çalıştır</button>
    </form>

    <table>
        <thead><tr><th>Sıra</th><th>Dosya</th><th>Durum</th><th>Batch</th></tr></thead>
        <tbody>
        <?php foreach ($status as $migration): ?>
            <tr>
                <td><?= (int) $migration['number'] ?></td>
                <td><?= migration_html($migration['file']) ?></td>
                <td class="<?= $migration['checksum_changed'] ? 'changed' : migration_html($migration['status']) ?>">
                    <?= $migration['checksum_changed'] ? 'checksum değişti' : migration_html($migration['status']) ?>
                </td>
                <td><?= $migration['batch'] === null ? '—' : (int) $migration['batch'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</body>
</html>
