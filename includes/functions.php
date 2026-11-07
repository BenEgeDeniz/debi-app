<?php
/**
 * DEBI - Yardımcı Fonksiyonlar
 */

/**
 * XSS Korumalı HTML Çıktısı
 */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * CSRF Token Üretimi ve Doğrulaması
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): bool
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string) $token);
}

/**
 * Flash Mesaj Yönetimi
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message,
    ];
}

function get_flash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Yönlendirme
 */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/**
 * Türkçe Karakter Uyumlu Slug Üretici
 */
function slugify(string $text): string
{
    $map = [
        'ç' => 'c', 'Ç' => 'c',
        'ğ' => 'g', 'Ğ' => 'g',
        'ı' => 'i', 'İ' => 'i', 'I' => 'i',
        'ö' => 'o', 'Ö' => 'o',
        'ş' => 's', 'Ş' => 's',
        'ü' => 'u', 'Ü' => 'u',
    ];
    $text = strtr(trim($text), $map);
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim((string) $text, '-') ?: 'mahalle';
}

/**
 * Mahalle ve İsim Норmalizasyonu (Baş Harfler Büyük)
 */
function normalize_place_name(string $text): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text));
    return mb_convert_case($text, MB_CASE_TITLE, 'UTF-8');
}

/**
 * Benzersiz Apartman Davet Kodu Üretici (Örn: DEBI-7K4M)
 */
function generate_invite_code(string $prefix = 'DEBI'): string
{
    $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < 5; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $prefix . '-' . $code;
}

/**
 * Kullanıcı İsim Baş Harfleri
 */
function user_initials(string $fullName): string
{
    $parts = preg_split('/\s+/', trim($fullName));
    $first = mb_substr($parts[0] ?? 'D', 0, 1, 'UTF-8');
    $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1, 'UTF-8') : '';
    return mb_strtoupper($first . $last, 'UTF-8');
}

function avatar_initials(string $fullName): string
{
    return user_initials($fullName);
}

/**
 * Kullanıcı Avatarı (Fotoğraf varsa <img>, yoksa baş harfli daire döndürür)
 */
function render_avatar(array $user, string $extraClass = ''): string
{
    $cls   = trim('avatar-circle ' . $extraClass);
    $photo = trim((string) ($user['avatar_photo'] ?? ''));
    $name  = (string) ($user['full_name'] ?? 'Üye');
    $color = (string) ($user['avatar_color'] ?? '#1cb0f6');

    if ($photo !== '') {
        return '<img src="' . e($photo) . '" alt="' . e($name) . '" class="' . e($cls) . ' avatar-img">';
    }

    return '<span class="' . e($cls) . '" style="background-color: ' . e($color) . ';">'
        . e(user_initials($name))
        . '</span>';
}

/**
 * Göreli Zaman Formatı (Örn: 15 dk önce, 2 saat önce)
 */
function time_ago(string $datetime): string
{
    $timestamp = strtotime($datetime);
    if (!$timestamp) {
        return $datetime;
    }

    $diff = time() - $timestamp;
    if ($diff < 60) {
        return 'Az önce';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' dk önce';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . ' saat önce';
    }
    if ($diff < 604800) {
        return floor($diff / 86400) . ' gün önce';
    }

    $months = [
        1 => 'Oca', 2 => 'Şub', 3 => 'Mar', 4 => 'Nis',
        5 => 'May', 6 => 'Haz', 7 => 'Tem', 8 => 'Ağu',
        9 => 'Eyl', 10 => 'Eki', 11 => 'Kas', 12 => 'Ara',
    ];
    $m = (int) date('n', $timestamp);
    return date('d ', $timestamp) . ($months[$m] ?? '') . date(' Y', $timestamp);
}

/**
 * Türkçe Ay Adı
 */
function turkish_month_name(int $month): string
{
    $months = [
        1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan',
        5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos',
        9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık',
    ];
    return $months[$month] ?? (string) $month;
}

/**
 * Gönderi Kategori Bilgileri
 */
function post_category_meta(string $category): array
{
    $map = [
        'tip'          => ['label' => 'Tasarruf İpucu',    'class' => 'badge-tip'],
        'achievement'  => ['label' => 'Sayaç / Başarı',    'class' => 'badge-achievement'],
        'question'     => ['label' => 'Komşuya Sor',       'class' => 'badge-question'],
        'alert'        => ['label' => 'Su / Tesisat Uyarı','class' => 'badge-alert'],
        'announcement' => ['label' => 'Yönetici Duyurusu', 'class' => 'badge-announcement'],
    ];
    return $map[$category] ?? ['label' => 'Paylaşım', 'class' => 'badge-tip'];
}

/**
 * Konut Tipi Etiketi
 */
function housing_type_label(string $type): string
{
    return match ($type) {
        'apartment_unit'  => 'Apartman Dairesi',
        'individual_unit' => 'Bireysel Katılımcı (Daire)',
        'detached_house'  => 'Müstakil Ev',
        default           => 'Hane',
    };
}

/**
 * Kullanıcının kendi dairesinin (hanesinin) yöneticisi / kurucusu olup olmadığını döndürür
 */
function is_unit_manager(array $user): bool
{
    $ownerId = (int) ($user['household_owner_id'] ?? 0);
    return $ownerId === 0 || $ownerId === (int) ($user['id'] ?? 0);
}

/**
 * Daire / Hane etiketini okunaklı hale getirir (Örn: "5" -> "Daire 5")
 */
function format_unit_label(?string $label, string $housingType = 'apartment_unit'): string
{
    $label = trim((string) $label);
    if ($label === '') {
        return $housingType === 'detached_house' ? 'Müstakil Ev' : 'Daire';
    }
    if (preg_match('/^\d+$/', $label)) {
        return ($housingType === 'detached_house' ? 'No: ' : 'Daire ') . $label;
    }
    return $label;
}

