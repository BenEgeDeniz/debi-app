<?php
/**
 * DEBI - PDO Veritabanı Bağlantı Dosyası
 * Yerel şifre ve bilgilerinizi config/database.local.php dosyasından yönetebilirsiniz.
 */

if (file_exists(__DIR__ . '/database.local.php')) {
    require_once __DIR__ . '/database.local.php';
} else {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'debi_db');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_CHARSET', 'utf8mb4');
}

// Gemini API Anahtarı (Fatura fotoğrafı okuma için)
// Anahtarınızı config/database.local.php içinde define('GEMINI_API_KEY', 'AIza...'); olarak tanımlayabilirsiniz.
if (!defined('GEMINI_API_KEY')) {
    define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
}
if (!defined('DEBBIE_GEMINI_MODEL')) {
    define('DEBBIE_GEMINI_MODEL', getenv('DEBBIE_GEMINI_MODEL') ?: 'gemini-3.5-flash-lite');
}
if (!defined('DEBI_INVOICE_ENCRYPTION_KEY')) {
    define('DEBI_INVOICE_ENCRYPTION_KEY', getenv('DEBI_INVOICE_ENCRYPTION_KEY') ?: '');
}

/**
 * Tekil (Singleton) PDO bağlantısı döndürür.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            exit('Veritabanı bağlantısı kurulamadı. Lütfen config/database.local.php dosyasındaki bilgileri ve sql-dum tablolarını kontrol edin.');
        }
    }

    return $pdo;
}
