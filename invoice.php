<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/modules/water/invoice_storage.php';

$name = invoice_file_name_from_reference('secure_bills/' . basename((string) ($_GET['file'] ?? '')));
if ($name === null || empty($currentUser)) {
    http_response_code(404);
    exit('Fatura bulunamadı.');
}

$stmt = db()->prepare("
    SELECT w.user_id, w.household_id, w.review_status
    FROM water_logs w
    WHERE w.bill_image_path LIKE :path
    LIMIT 1
");
$stmt->execute([':path' => 'secure_bills/' . $name . '%']);
$record = $stmt->fetch();
$pendingReference = $_SESSION['pending_invoice_reference'] ?? '';
$isOwner = $record && ((int) $record['user_id'] === (int) $currentUser['id']
    || (int) $record['household_id'] === (int) ($currentUser['household_id'] ?? 0));
$isAdmin = !empty($currentUser['is_admin']) && $record;
$isPendingOwner = $pendingReference !== '' && invoice_file_name_from_reference($pendingReference) === $name;
if (!$isOwner && !$isAdmin && !$isPendingOwner) {
    http_response_code(403);
    exit('Bu faturayı görüntüleme yetkiniz yok.');
}

try {
    $decrypted = decrypt_invoice_file($name);
    $type = (string) ($_GET['type'] ?? 'jpg');
    $mime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$type] ?? 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="debi-fatura.' . $type . '"');
    header('Cache-Control: private, no-store');
    echo $decrypted['contents'];
} catch (Throwable $exception) {
    http_response_code(404);
    exit('Fatura görüntülenemedi.');
}
