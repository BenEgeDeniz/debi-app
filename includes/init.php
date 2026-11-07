<?php
/**
 * DEBI - Uygulama Başlatıcı (Bootstrap)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Europe/Istanbul');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../modules/gamification/gamification_service.php';
require_once __DIR__ . '/../modules/friends/friendship_service.php';

ensure_gamification_schema();

$currentUser = null;

if (!empty($_SESSION['user_id'])) {
    $stmt = db()->prepare("
        SELECT
            u.*,
            h.housing_type,
            h.unit_label,
            h.resident_count,
            n.city AS neighborhood_city,
            n.district AS neighborhood_district,
            n.name AS neighborhood_name,
            n.slug AS neighborhood_slug,
            a.name AS apartment_name,
            a.invite_code AS apartment_invite_code,
            a.total_units AS apartment_total_units,
            a.monthly_goal_m3 AS apartment_monthly_goal_m3,
            a.manager_user_id AS apartment_manager_id
        FROM users u
        INNER JOIN households h ON h.id = u.household_id
        INNER JOIN neighborhoods n ON n.id = u.neighborhood_id
        LEFT JOIN apartments a ON a.id = u.apartment_id
        WHERE u.id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => (int) $_SESSION['user_id']]);
    $currentUser = $stmt->fetch() ?: null;

    if (!$currentUser) {
        unset($_SESSION['user_id']);
    }
}

/**
 * Oturum açmış kullanıcı zorunluluğu
 */
function require_login(): array
{
    global $currentUser;
    if (!$currentUser) {
        redirect('login.php');
    }
    return $currentUser;
}

