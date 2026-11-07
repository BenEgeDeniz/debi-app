<?php
/**
 * DEBI - Profil ve Hane Ayarları Modülü
 */

function update_user_and_household_profile(array $user, array $input): array
{
    $fullName      = trim($input['full_name'] ?? '');
    $bio           = trim($input['bio'] ?? '');
    $unitLabel     = trim($input['unit_label'] ?? '');
    $residentCount = max(1, min(20, (int) ($input['resident_count'] ?? 2)));
    $housingType   = $user['housing_type'];

    if (mb_strlen($fullName, 'UTF-8') < 3) {
        return ['ok' => false, 'error' => 'Ad Soyad en az 3 karakter olmalıdır.'];
    }

    if ($unitLabel === '') {
        return ['ok' => false, 'error' => 'Hane / Daire etiketi boş bırakılamaz.'];
    }

    // Eğer bir apartmana bağlı değilse Bireysel Daire ve Müstakil Ev arasında geçiş yapabilir
    if (empty($user['apartment_id']) && !empty($input['individual_housing_type'])) {
        $reqType = $input['individual_housing_type'];
        if (in_array($reqType, ['individual_unit', 'detached_house'], true)) {
            $housingType = $reqType;
        }
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $uStmt = $pdo->prepare("
            UPDATE users
            SET full_name = :name, bio = :bio
            WHERE id = :uid
        ");
        $uStmt->execute([
            ':name' => $fullName,
            ':bio'  => $bio !== '' ? $bio : null,
            ':uid'  => (int) $user['id'],
        ]);

        $hStmt = $pdo->prepare("
            UPDATE households
            SET unit_label = :label, resident_count = :rcount, housing_type = :htype
            WHERE id = :hid
        ");
        $hStmt->execute([
            ':label'  => $unitLabel,
            ':rcount' => $residentCount,
            ':htype'  => $housingType,
            ':hid'    => (int) $user['household_id'],
        ]);

        $pdo->commit();
        return ['ok' => true];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'error' => 'Profil güncellenemedi: ' . $e->getMessage()];
    }
}

/**
 * Herhangi bir kullanıcının Zaman Tüneli (Timeline) profil bilgilerini döndürür.
 */
function get_user_public_profile(int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }

    $pdo   = db();
    $year  = (int) date('Y');
    $month = (int) date('n');

    $stmt = $pdo->prepare("
        SELECT
            u.*,
            h.housing_type,
            h.unit_label,
            h.resident_count,
            n.city AS neighborhood_city,
            n.district AS neighborhood_district,
            n.name AS neighborhood_name,
            a.name AS apartment_name,
            w.daily_liters_per_person AS current_daily_liters,
            w.consumption_m3 AS current_consumption_m3
        FROM users u
        INNER JOIN households h ON h.id = u.household_id
        INNER JOIN neighborhoods n ON n.id = u.neighborhood_id
        LEFT JOIN apartments a ON a.id = u.apartment_id
        LEFT JOIN water_logs w ON w.household_id = h.id AND w.period_year = :y AND w.period_month = :m
        WHERE u.id = :id
        LIMIT 1
    ");
    $stmt->execute([
        ':id' => $userId,
        ':y'  => $year,
        ':m'  => $month,
    ]);
    $profile = $stmt->fetch();

    return $profile ?: null;
}


