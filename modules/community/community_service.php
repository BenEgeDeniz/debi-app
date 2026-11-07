<?php
/**
 * DEBI - Mahalle ve Apartman Topluluk Modülü
 */

/**
 * Bireysel katılımcının sonradan davet koduyla bir apartmana katılması
 */
function join_apartment_for_existing_user(array $user, string $inviteCode, string $customUnitLabel = ''): array
{
    $pdo        = db();
    $inviteCode = strtoupper(trim($inviteCode));
    $customUnit = trim($customUnitLabel);

    if ($inviteCode === '') {
        return ['ok' => false, 'error' => 'Lütfen geçerli bir davet kodu girin.'];
    }

    try {
        $pdo->beginTransaction();

        $apartmentId    = null;
        $neighborhoodId = null;
        $unitLabel      = $customUnit !== '' ? $customUnit : $user['unit_label'];
        $invitationId   = null;

        // 1. Daireye özel davet kodu kontrolü
        $invStmt = $pdo->prepare("
            SELECT i.*, a.neighborhood_id
            FROM invitations i
            INNER JOIN apartments a ON a.id = i.apartment_id
            WHERE i.invite_token = :token AND i.is_used = 0
            LIMIT 1
        ");
        $invStmt->execute([':token' => $inviteCode]);
        $inv = $invStmt->fetch();

        if ($inv) {
            $apartmentId    = (int) $inv['apartment_id'];
            $neighborhoodId = (int) $inv['neighborhood_id'];
            $unitLabel      = $inv['unit_label'];
            $invitationId   = (int) $inv['id'];
        } else {
            // 2. Apartman genel davet kodu kontrolü
            $aptStmt = $pdo->prepare("
                SELECT id, neighborhood_id
                FROM apartments
                WHERE invite_code = :code
                LIMIT 1
            ");
            $aptStmt->execute([':code' => $inviteCode]);
            $apt = $aptStmt->fetch();

            if (!$apt) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Davet kodu bulunamadı veya daha önce kullanılmış.'];
            }

            if ($customUnit === '') {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Genel apartman kodu ile katılırken daire numaranızı (Örn: Daire 3) yazmalısınız.'];
            }

            $apartmentId    = (int) $apt['id'];
            $neighborhoodId = (int) $apt['neighborhood_id'];
            $unitLabel      = $customUnit;
        }

        // Hane bilgilerini apartmana bağla
        $updH = $pdo->prepare("
            UPDATE households
            SET neighborhood_id = :nid,
                apartment_id    = :aid,
                housing_type    = 'apartment_unit',
                unit_label      = :label
            WHERE id = :hid
        ");
        $updH->execute([
            ':nid'   => $neighborhoodId,
            ':aid'   => $apartmentId,
            ':label' => $unitLabel,
            ':hid'   => (int) $user['household_id'],
        ]);

        // Kullanıcıyı apartmana bağla
        $updU = $pdo->prepare("
            UPDATE users
            SET neighborhood_id = :nid,
                apartment_id    = :aid
            WHERE id = :uid
        ");
        $updU->execute([
            ':nid' => $neighborhoodId,
            ':aid' => $apartmentId,
            ':uid' => (int) $user['id'],
        ]);

        // Geçmiş su kayıtlarını da apartman istatistiğine yansıt
        $updW = $pdo->prepare("
            UPDATE water_logs
            SET neighborhood_id = :nid,
                apartment_id    = :aid
            WHERE household_id = :hid
        ");
        $updW->execute([
            ':nid' => $neighborhoodId,
            ':aid' => $apartmentId,
            ':hid' => (int) $user['household_id'],
        ]);

        if ($invitationId) {
            $updInv = $pdo->prepare("
                UPDATE invitations
                SET is_used = 1, used_by_user_id = :uid, used_at = NOW()
                WHERE id = :id
            ");
            $updInv->execute([':uid' => (int) $user['id'], ':id' => $invitationId]);
        }

        $pdo->commit();
        if (function_exists('award_points')) {
            award_points((int) $user['id'], (int) $user['household_id'], 'apartment_join', 50, '🏠 Apartmanına bağlandın!', 'apt_join_' . $user['id']);
        }
        return ['ok' => true];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'error' => 'Apartmana katılırken hata oluştu: ' . $e->getMessage()];
    }
}

/**
 * Bireysel katılımcının kendi mahallesinde yeni apartman oluşturup yönetici olması
 */
function create_apartment_for_existing_user(array $user, string $name, string $address, int $totalUnits, string $unitLabel): array
{
    $pdo        = db();
    $name       = trim($name);
    $address    = trim($address);
    $unitLabel  = trim($unitLabel) ?: 'Daire 1';
    $totalUnits = max(2, min(500, $totalUnits));

    if ($name === '') {
        return ['ok' => false, 'error' => 'Lütfen apartman adını girin.'];
    }

    try {
        $pdo->beginTransaction();

        $inviteCode = generate_invite_code('DEBI');
        $ins = $pdo->prepare("
            INSERT INTO apartments (neighborhood_id, name, address_detail, invite_code, total_units, manager_user_id)
            VALUES (:nid, :name, :addr, :code, :units, :uid)
        ");
        $ins->execute([
            ':nid'   => (int) $user['neighborhood_id'],
            ':name'  => $name,
            ':addr'  => $address ?: null,
            ':code'  => $inviteCode,
            ':units' => $totalUnits,
            ':uid'   => (int) $user['id'],
        ]);
        $apartmentId = (int) $pdo->lastInsertId();

        $pdo->prepare("
            UPDATE households
            SET apartment_id = :aid, housing_type = 'apartment_unit', unit_label = :label
            WHERE id = :hid
        ")->execute([
            ':aid'   => $apartmentId,
            ':label' => $unitLabel,
            ':hid'   => (int) $user['household_id'],
        ]);

        $pdo->prepare("
            UPDATE users
            SET apartment_id = :aid, role = 'manager'
            WHERE id = :uid
        ")->execute([
            ':aid' => $apartmentId,
            ':uid' => (int) $user['id'],
        ]);

        $pdo->prepare("
            UPDATE water_logs
            SET apartment_id = :aid
            WHERE household_id = :hid
        ")->execute([
            ':aid' => $apartmentId,
            ':hid' => (int) $user['household_id'],
        ]);

        $pdo->commit();
        if (function_exists('award_points')) {
            award_points((int) $user['id'], (int) $user['household_id'], 'apartment_create', 100, '🏢 Mahallende apartmanını kurdun!', 'apt_create_' . $apartmentId);
        }
        return ['ok' => true, 'apartment_id' => $apartmentId];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'error' => 'Apartman oluşturulurken hata oluştu: ' . $e->getMessage()];
    }
}

/**
 * Apartman yöneticisinin belirli bir daire için tek kullanımlık davet kodu üretmesi
 */
function create_unit_invitation(int $apartmentId, string $unitLabel): array
{
    $unitLabel = trim($unitLabel);
    if ($unitLabel === '') {
        return ['ok' => false, 'error' => 'Lütfen davet oluşturulacak daire bilgisini girin (Örn: Daire 7).'];
    }

    $token = generate_invite_code('DAIRE');
    $stmt  = db()->prepare("
        INSERT INTO invitations (apartment_id, unit_label, invite_token)
        VALUES (:aid, :label, :token)
    ");
    $stmt->execute([
        ':aid'   => $apartmentId,
        ':label' => $unitLabel,
        ':token' => $token,
    ]);

    return ['ok' => true, 'token' => $token];
}

/**
 * Apartman aylık su hedefini güncelleme
 */
function update_apartment_goal(int $apartmentId, ?float $monthlyGoalM3): void
{
    $stmt = db()->prepare("
        UPDATE apartments
        SET monthly_goal_m3 = :goal
        WHERE id = :aid
    ");
    $stmt->execute([
        ':goal' => ($monthlyGoalM3 !== null && $monthlyGoalM3 > 0) ? $monthlyGoalM3 : null,
        ':aid'  => $apartmentId,
    ]);
}

/**
 * Mahalle Detayları, Apartman Listesi ve Bireysel/Müstakil Katılımcılar
 */
function get_neighborhood_overview(int $neighborhoodId, int $year, int $month): array
{
    $pdo = db();

    // Mahalle temel bilgisi
    $nStmt = $pdo->prepare("SELECT * FROM neighborhoods WHERE id = :id LIMIT 1");
    $nStmt->execute([':id' => $neighborhoodId]);
    $neighborhood = $nStmt->fetch();

    // Mahalledeki apartmanlar ve o ayki ortalama kişi başı günlük litre tüketimleri
    $aptStmt = $pdo->prepare("
        SELECT
            a.*,
            u.full_name AS manager_name,
            COUNT(DISTINCT h.id) AS registered_households,
            AVG(w.daily_liters_per_person) AS avg_daily_liters,
            SUM(w.consumption_m3) AS total_m3
        FROM apartments a
        LEFT JOIN users u ON u.id = a.manager_user_id
        LEFT JOIN households h ON h.apartment_id = a.id
        LEFT JOIN water_logs w ON w.apartment_id = a.id AND w.period_year = :y AND w.period_month = :m
        WHERE a.neighborhood_id = :nid
        GROUP BY a.id
        ORDER BY avg_daily_liters IS NULL ASC, avg_daily_liters ASC, a.name ASC
    ");
    $aptStmt->execute([
        ':y'   => $year,
        ':m'   => $month,
        ':nid' => $neighborhoodId,
    ]);
    $apartments = $aptStmt->fetchAll();

    // Mahallede apartmana bağlı OLMAYAN (Bireysel daire + Müstakil ev) haneler
    $indStmt = $pdo->prepare("
        SELECT
            h.id AS household_id,
            h.housing_type,
            h.unit_label,
            h.resident_count,
            u.id AS user_id,
            u.full_name,
            u.avatar_color,
            u.total_points,
            w.consumption_m3,
            w.daily_liters_per_person
        FROM households h
        INNER JOIN users u ON u.household_id = h.id
        LEFT JOIN water_logs w ON w.household_id = h.id AND w.period_year = :y AND w.period_month = :m
        WHERE h.neighborhood_id = :nid AND h.apartment_id IS NULL
        ORDER BY w.daily_liters_per_person IS NULL ASC, w.daily_liters_per_person ASC, u.full_name ASC
    ");
    $indStmt->execute([
        ':y'   => $year,
        ':m'   => $month,
        ':nid' => $neighborhoodId,
    ]);
    $individualHouseholds = $indStmt->fetchAll();

    // Mahalle genel istatistikleri (Tüm haneler: apartman daireleri + bireysel + müstakil)
    $statStmt = $pdo->prepare("
        SELECT
            (SELECT COUNT(*) FROM households WHERE neighborhood_id = :nid1) AS total_households,
            (SELECT COUNT(*) FROM apartments WHERE neighborhood_id = :nid2) AS total_apartments,
            AVG(w.daily_liters_per_person) AS avg_daily_liters,
            SUM(w.consumption_m3) AS total_m3
        FROM water_logs w
        WHERE w.neighborhood_id = :nid3 AND w.period_year = :y AND w.period_month = :m
    ");
    $statStmt->execute([
        ':nid1' => $neighborhoodId,
        ':nid2' => $neighborhoodId,
        ':nid3' => $neighborhoodId,
        ':y'    => $year,
        ':m'    => $month,
    ]);
    $stats = $statStmt->fetch() ?: [];

    // Mahallede bu ay en az tüketen ilk 8 hane (Hem apartman daireleri hem bireysel/müstakil evler)
    $leaderStmt = $pdo->prepare("
        SELECT
            w.daily_liters_per_person,
            w.consumption_m3,
            h.unit_label,
            h.housing_type,
            h.resident_count,
            a.name AS apartment_name,
            u.full_name,
            u.avatar_color
        FROM water_logs w
        INNER JOIN households h ON h.id = w.household_id
        INNER JOIN users u ON u.household_id = h.id
        LEFT JOIN apartments a ON a.id = h.apartment_id
        WHERE w.neighborhood_id = :nid AND w.period_year = :y AND w.period_month = :m
        ORDER BY w.daily_liters_per_person ASC
        LIMIT 8
    ");
    $leaderStmt->execute([
        ':nid' => $neighborhoodId,
        ':y'   => $year,
        ':m'   => $month,
    ]);
    $leaderboard = $leaderStmt->fetchAll();

    // Aktif mahalle su kesintisi / arıza bildirimleri
    $alertStmt = $pdo->prepare("
        SELECT na.*, u.full_name AS author_name
        FROM neighborhood_alerts na
        INNER JOIN users u ON u.id = na.user_id
        WHERE na.neighborhood_id = :nid AND na.is_active = 1
        ORDER BY na.created_at DESC
        LIMIT 3
    ");
    $alertStmt->execute([':nid' => $neighborhoodId]);
    $activeAlerts = $alertStmt->fetchAll();

    return [
        'neighborhood'          => $neighborhood,
        'apartments'            => $apartments,
        'individual_households' => $individualHouseholds,
        'stats'                 => $stats,
        'leaderboard'           => $leaderboard,
        'alerts'                => $activeAlerts,
    ];
}

/**
 * Apartman Detayları, Kayıtlı Daireler ve Davet Listesi
 */
function get_apartment_overview(int $apartmentId, int $year, int $month): array
{
    $pdo = db();

    $aptStmt = $pdo->prepare("
        SELECT a.*, n.city, n.district, n.name AS neighborhood_name, u.full_name AS manager_name
        FROM apartments a
        INNER JOIN neighborhoods n ON n.id = a.neighborhood_id
        LEFT JOIN users u ON u.id = a.manager_user_id
        WHERE a.id = :id
        LIMIT 1
    ");
    $aptStmt->execute([':id' => $apartmentId]);
    $apartment = $aptStmt->fetch();

    if (!$apartment) {
        return [];
    }

    // Apartmandaki kayıtlı daireler ve bu ayki tüketimleri
    $unitsStmt = $pdo->prepare("
        SELECT
            h.id AS household_id,
            h.unit_label,
            h.resident_count,
            u.id AS user_id,
            u.full_name,
            u.role,
            u.avatar_color,
            u.total_points,
            w.consumption_m3,
            w.daily_liters_per_person,
            w.bill_amount,
            w.notes
        FROM households h
        INNER JOIN users u ON u.household_id = h.id
        LEFT JOIN water_logs w ON w.household_id = h.id AND w.period_year = :y AND w.period_month = :m
        WHERE h.apartment_id = :aid
        ORDER BY w.daily_liters_per_person IS NULL ASC, w.daily_liters_per_person ASC, h.unit_label ASC
    ");
    $unitsStmt->execute([
        ':y'   => $year,
        ':m'   => $month,
        ':aid' => $apartmentId,
    ]);
    $units = $unitsStmt->fetchAll();

    // Apartman bu ayki özet istatistik
    $statStmt = $pdo->prepare("
        SELECT
            COUNT(w.id) AS logged_units_count,
            SUM(w.consumption_m3) AS total_m3,
            AVG(w.daily_liters_per_person) AS avg_daily_liters
        FROM water_logs w
        WHERE w.apartment_id = :aid AND w.period_year = :y AND w.period_month = :m
    ");
    $statStmt->execute([
        ':aid' => $apartmentId,
        ':y'   => $year,
        ':m'   => $month,
    ]);
    $stats = $statStmt->fetch() ?: [];

    // Daire bazlı üretilmiş davet kodları
    $invStmt = $pdo->prepare("
        SELECT i.*, u.full_name AS used_by_name
        FROM invitations i
        LEFT JOIN users u ON u.id = i.used_by_user_id
        WHERE i.apartment_id = :aid
        ORDER BY i.is_used ASC, i.created_at DESC
    ");
    $invStmt->execute([':aid' => $apartmentId]);
    $invitations = $invStmt->fetchAll();

    return [
        'apartment'   => $apartment,
        'units'       => $units,
        'stats'       => $stats,
        'invitations' => $invitations,
    ];
}

