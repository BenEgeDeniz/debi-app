<?php
/**
 * DEBI - Profil, Fotoğraf (Avatar & Kapak) ve Hane Ayarları Modülü
 */

function update_user_and_household_profile(array $user, array $input, array $files = []): array
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

    if (empty($user['apartment_id']) && !empty($input['individual_housing_type'])) {
        $reqType = $input['individual_housing_type'];
        if (in_array($reqType, ['individual_unit', 'detached_house'], true)) {
            $housingType = $reqType;
        }
    }

    $avatarPath = $user['avatar_photo'] ?? null;
    $coverPath  = $user['cover_photo'] ?? null;

    // 1. Profil Fotoğrafı Yükleme
    if (!empty($files['avatar_photo']['tmp_name']) && ($files['avatar_photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $upRes = save_profile_media_upload($files['avatar_photo'], 'avatars', 'avatar_' . (int) $user['id']);
        if (!$upRes['ok']) {
            return $upRes;
        }
        $avatarPath = $upRes['path'];
    }

    // 2. Kapak Fotoğrafı Yükleme
    if (!empty($files['cover_photo']['tmp_name']) && ($files['cover_photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $upRes = save_profile_media_upload($files['cover_photo'], 'covers', 'cover_' . (int) $user['id']);
        if (!$upRes['ok']) {
            return $upRes;
        }
        $coverPath = $upRes['path'];
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $uStmt = $pdo->prepare("
            UPDATE users
            SET full_name    = :name,
                bio          = :bio,
                avatar_photo = :avatar,
                cover_photo  = :cover
            WHERE id = :uid
        ");
        $uStmt->execute([
            ':name'   => $fullName,
            ':bio'    => $bio !== '' ? $bio : null,
            ':avatar' => $avatarPath,
            ':cover'  => $coverPath,
            ':uid'    => (int) $user['id'],
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
 * Profil veya Kapak görselini doğrular ve kaydeder
 */
function save_profile_media_upload(array $file, string $subDir, string $prefix): array
{
    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowedMimes[$mime])) {
        return ['ok' => false, 'error' => 'Lütfen JPG, PNG veya WebP formatında bir görsel seçin.'];
    }

    if (($file['size'] ?? 0) > 6 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'Görsel boyutu en fazla 6 MB olabilir.'];
    }

    $uploadDir = __DIR__ . '/../../uploads/' . $subDir;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileName = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowedMimes[$mime];
    $target   = $uploadDir . '/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        return ['ok' => false, 'error' => 'Görsel sunucuya kaydedilemedi.'];
    }

    return ['ok' => true, 'path' => 'uploads/' . $subDir . '/' . $fileName];
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
            h.owner_user_id AS household_owner_id,
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
