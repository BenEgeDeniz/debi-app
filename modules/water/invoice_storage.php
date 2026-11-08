<?php

function invoice_storage_key(): string
{
    $encoded = defined('DEBI_INVOICE_ENCRYPTION_KEY')
        ? (string) DEBI_INVOICE_ENCRYPTION_KEY
        : (string) (getenv('DEBI_INVOICE_ENCRYPTION_KEY') ?: '');
    $key = base64_decode($encoded, true);
    if ($key === false || strlen($key) !== 32) {
        throw new RuntimeException('Fatura şifreleme anahtarı yapılandırılmamış veya geçersiz.');
    }
    return $key;
}

function invoice_storage_dir(): string
{
    $dir = dirname(__DIR__, 2) . '/../debi-private/invoices';
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Fatura güvenli depolama klasörü oluşturulamadı.');
    }
    return $dir;
}

function encrypt_invoice_file(string $sourcePath, string $extension): string
{
    $plaintext = file_get_contents($sourcePath);
    if ($plaintext === false) {
        throw new RuntimeException('Fatura görseli okunamadı.');
    }
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt(
        $plaintext,
        'aes-256-gcm',
        invoice_storage_key(),
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );
    if ($ciphertext === false) {
        throw new RuntimeException('Fatura görseli şifrelenemedi.');
    }
    $name = bin2hex(random_bytes(24)) . '.enc';
    $path = invoice_storage_dir() . '/' . $name;
    $payload = "DEBI1\0" . $iv . $tag . $ciphertext;
    if (file_put_contents($path, $payload, LOCK_EX) === false) {
        throw new RuntimeException('Şifreli fatura kaydedilemedi.');
    }
    @chmod($path, 0600);
    return 'secure_bills/' . $name . '?type=' . rawurlencode($extension);
}

function decrypt_invoice_file(string $relativeName): array
{
    if (!preg_match('/^[a-f0-9]{48}\.enc$/', $relativeName)) {
        throw new RuntimeException('Geçersiz fatura dosyası.');
    }
    $path = invoice_storage_dir() . '/' . $relativeName;
    $payload = file_get_contents($path);
    if ($payload === false || !str_starts_with($payload, "DEBI1\0") || strlen($payload) < 70) {
        throw new RuntimeException('Fatura dosyası okunamadı.');
    }
    $iv = substr($payload, 6, 12);
    $tag = substr($payload, 18, 16);
    $ciphertext = substr($payload, 34);
    $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', invoice_storage_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($plaintext === false) {
        throw new RuntimeException('Fatura şifresi çözülemedi.');
    }
    return ['contents' => $plaintext, 'path' => $path];
}

function invoice_file_name_from_reference(string $reference): ?string
{
    $path = parse_url($reference, PHP_URL_PATH);
    if (!is_string($path) || !str_starts_with($path, 'secure_bills/')) {
        return null;
    }
    $name = substr($path, strlen('secure_bills/'));
    return preg_match('/^[a-f0-9]{48}\.enc$/', $name) ? $name : null;
}
