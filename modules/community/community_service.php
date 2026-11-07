<?php
/**
 * DEBI - Apartman ve Daire (Hane) Topluluk Modülü
 */

/**
 * Mevcut kullanıcının:
 * - Genel Apartman Kodu (DEBI-XXXXX) ile kendi dairesini apartmana bağlaması (Daire Yöneticisi olarak)
 * - VEYA Daire Sakin Kodu (DAIRE-XXXXX) ile mevcut bir daireye Sakin olarak katılması
 */
function join_apartment_for_existing_user(array $user, string $inviteCode, string $customUnitLabel = ''): array
{
    if (($user['housing_type'] ?? '') === 'detached_house') {
        return ['ok' => false, 'error' => 'Müstakil ev olarak kayıtlı haneler apartmana bağlanamaz.'];
    }

    $pdo        = db();
    $inviteCode = strtoupper(trim($inviteCode));
    $customUnit = trim($customUnitLabel);

    if ($inviteCode === '') {
        return ['ok' => false, 'error' => 'Lütfen geçerli bir davet kodu girin.'];
    }

    try {
        $pdo->beginTransaction();

        // 1. Önce Daire Sakin Kodu (households.unit_invite_code -> DAIRE-XXXXX) kontrolü
        $hhStmt = $pdo->prepare("
            SELECT id, neighborhood_id, apartment_id, unit_label
            FROM households
            WHERE unit_invite_code = :code
            LIMIT 1
        ");
        $hhStmt->execute([':code' => $inviteCode]);
        $targetHh = $hhStmt->fetch();

        if ($targetHh) {
            $targetHid = (int) $targetHh['id'];
            if ($targetHid === (int) $user['household_id']) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Zaten bu dairede kayıtlısınız.'];
            }

            $targetNid = (int) $targetHh['neighborhood_id'];
            $targetAid = !empty($targetHh['apartment_id']) ? (int) $targetHh['apartment_id'] : null;

            // Kullanıcıyı hedef daireye Sakin olarak taşı
            $pdo->prepare("
                UPDATE users
                SET household_id    = :hid,
                    neighborhood_id = :nid,
                    apartment_id    = :aid
                WHERE id = :uid
            ")->execute([
                ':hid' => $targetHid,
                ':nid' => $targetNid,
                ':aid' => $targetAid,
                ':uid' => (int) $user['id'],
            ]);

            // Dairedeki kayıtlı kişi sayısını güncelle
            $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE household_id = :hid");
            $cntStmt->execute([':hid' => $targetHid]);
            $regMembers = (int) $cntStmt->fetchColumn();
            $pdo->prepare("UPDATE households SET resident_count = GREATEST(resident_count, :cnt) WHERE id = :hid")
                ->execute([':cnt' => $regMembers, ':hid' => $targetHid]);

            $pdo->commit();
            return ['ok' => true, 'joined_as' => 'resident', 'unit_label' => $targetHh['unit_label']];
        }

        // 2. Genel Apartman Davet Kodu (apartments.invite_code -> DEBI-XXXXX) kontrolü
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
            return ['ok' => false, 'error' => 'Davet kodu bulunamadı. Lütfen apartman kodunu (DEBI-...) veya daire sakin kodunu (DAIRE-...) kontrol edin.'];
        }

        if ($customUnit === '') {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Apartman kodu ile katılırken daire numaranızı (Örn: Daire 3) yazmalısınız.'];
        }

        $apartmentId    = (int) $apt['id'];
        $neighborhoodId = (int) $apt['neighborhood_id'];
        $unitLabel      = $customUnit;

        // Kullanıcının hanesini apartmana bağla ve kendisini Daire Yöneticisi olarak işaretle
        $updH = $pdo->prepare("
            UPDATE households
            SET neighborhood_id = :nid,
                apartment_id    = :aid,
                housing_type    = 'apartment_unit',
                unit_label      = :label,
                owner_user_id   = COALESCE(owner_user_id, :uid)
            WHERE id = :hid
        ");
        $updH->execute([
            ':nid'   => $neighborhoodId,
            ':aid'   => $apartmentId,
            ':label' => $unitLabel,
            ':uid'   => (int) $user['id'],
            ':hid'   => (int) $user['household_id'],
        ]);

        // Aynı hanedeki tüm sakinleri de apartmana bağla
        $updU = $pdo->prepare("
            UPDATE users
            SET neighborhood_id = :nid,
                apartment_id    = :aid
            WHERE household_id = :hid
        ");
        $updU->execute([
            ':nid' => $neighborhoodId,
            ':aid' => $apartmentId,
            ':hid' => (int) $user['household_id'],
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

        $pdo->commit();
        return ['ok' => true, 'joined_as' => 'unit_manager', 'unit_label' => $unitLabel];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'error' => 'Bağlantı sırasında hata oluştu: ' . $e->getMessage()];
    }
}

/**
 * Bireysel katılımcının kendi mahallesinde yeni apartman oluşturup yönetici olması
 */
function create_apartment_for_existing_user(array $user, string $name, string $address, int $totalUnits, string $unitLabel): array
{
    if (($user['housing_type'] ?? '') === 'detached_house') {
        return ['ok' => false, 'error' => 'Müstakil ev olarak kayıtlı haneler apartman oluşturamaz.'];
    }

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
            SET apartment_id  = :aid,
                housing_type  = 'apartment_unit',
                unit_label    = :label,
                owner_user_id = COALESCE(owner_user_id, :uid)
            WHERE id = :hid
        ")->execute([
            ':aid'   => $apartmentId,
            ':label' => $unitLabel,
            ':uid'   => (int) $user['id'],
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
            UPDATE users
            SET apartment_id = :aid
            WHERE household_id = :hid
        ")->execute([
            ':aid' => $apartmentId,
            ':hid' => (int) $user['household_id'],
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
        return ['ok' => true, 'apartment_id' => $apartmentId];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'error' => 'Apartman oluşturulurken hata oluştu: ' . $e->getMessage()];
    }
}

/**
 * Daire Yöneticisinin kendi dairesinin Sakin Davet Kodunu (DAIRE-XXXXX) yenilemesi
 */
function regenerate_household_invite_code(int $householdId, int $userId): array
{
    $pdo  = db();
    $stmt = $pdo->prepare("SELECT owner_user_id FROM households WHERE id = :hid LIMIT 1");
    $stmt->execute([':hid' => $householdId]);
    $ownerId = (int) $stmt->fetchColumn();

    if ($ownerId !== 0 && $ownerId !== $userId) {
        return ['ok' => false, 'error' => 'Yalnızca daire yöneticisi daire davet kodunu yenileyebilir.'];
    }

    $newCode = generate_invite_code('DAIRE');
    $upd     = $pdo->prepare("UPDATE households SET unit_invite_code = :code, owner_user_id = :uid WHERE id = :hid");
    $upd->execute([
        ':code' => $newCode,
        ':uid'  => $userId,
        ':hid'  => $householdId,
    ]);

    return ['ok' => true, 'code' => $newCode];
}

/**
 * Belirli bir dairede (household) yaşayan kayıtlı sakinleri döndürür
 */
function get_household_residents(int $householdId): array
{
    $stmt = db()->prepare("
        SELECT
            u.id,
            u.full_name,
            u.role,
            u.avatar_color,
            u.avatar_photo,
            h.owner_user_id
        FROM users u
        INNER JOIN households h ON h.id = u.household_id
        WHERE u.household_id = :hid
        ORDER BY
            CASE WHEN h.owner_user_id = u.id THEN 0 ELSE 1 END ASC,
            u.id ASC
    ");
    $stmt->execute([':hid' => $householdId]);
    return $stmt->fetchAll();
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
 * Apartman Detayları ve Kayıtlı Daireler (Her daire kendi sakin listesiyle birlikte)
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

    // Apartmandaki kayıtlı daireler (Her household tek satırdır)
    $unitsStmt = $pdo->prepare("
        SELECT
            h.id AS household_id,
            h.unit_label,
            h.resident_count,
            h.owner_user_id,
            h.unit_invite_code,
            w.consumption_m3,
            w.daily_liters_per_person,
            w.bill_amount,
            w.notes
        FROM households h
        LEFT JOIN water_logs w ON w.household_id = h.id AND w.period_year = :y AND w.period_month = :m
        WHERE h.apartment_id = :aid
        ORDER BY
            CASE WHEN w.daily_liters_per_person IS NULL THEN 1 ELSE 0 END ASC,
            w.daily_liters_per_person ASC,
            h.unit_label ASC
    ");
    $unitsStmt->execute([
        ':y'   => $year,
        ':m'   => $month,
        ':aid' => $apartmentId,
    ]);
    $units = $unitsStmt->fetchAll();

    // Apartmandaki tüm kullanıcıları çekip kendi dairelerine (household_id) eşle
    $resStmt = $pdo->prepare("
        SELECT
            u.id,
            u.household_id,
            u.full_name,
            u.role,
            u.avatar_color,
            u.avatar_photo
        FROM users u
        WHERE u.apartment_id = :aid
        ORDER BY u.id ASC
    ");
    $resStmt->execute([':aid' => $apartmentId]);
    $allResidents = $resStmt->fetchAll();

    $residentsByHousehold = [];
    foreach ($allResidents as $r) {
        $hid = (int) $r['household_id'];
        $residentsByHousehold[$hid][] = $r;
    }

    foreach ($units as &$unit) {
        $hid       = (int) $unit['household_id'];
        $ownerId   = (int) ($unit['owner_user_id'] ?? 0);
        $list      = $residentsByHousehold[$hid] ?? [];
        // Daire yöneticisi ilk sırada görünsün
        usort($list, static function (array $a, array $b) use ($ownerId): int {
            $aOwner = ((int) $a['id'] === $ownerId) ? 0 : 1;
            $bOwner = ((int) $b['id'] === $ownerId) ? 0 : 1;
            return $aOwner <=> $bOwner ?: ((int) $a['id'] <=> (int) $b['id']);
        });
        $unit['residents'] = $list;
    }
    unset($unit);

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

    return [
        'apartment' => $apartment,
        'units'     => $units,
        'stats'     => $stats,
    ];
}

/**
 * 1. Arkadaş Sıralaması: Arkadaşların ve kullanıcının dairelerinin puanları (toplam damlalar)
 */
function get_friends_drops_leaderboard(array $currentUser, int $limit = 6): array
{
    $pdo       = db();
    $currentId = (int) ($currentUser['id'] ?? 0);
    if ($currentId <= 0) {
        return [];
    }

    require_once __DIR__ . '/../friends/friendship_service.php';
    $friendIds = get_user_friend_ids($currentId);
    $userIds   = array_values(array_unique(array_merge([$currentId], $friendIds)));

    if (empty($userIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));

    try {
        $sql = "
            SELECT
                u.id AS user_id,
                u.full_name,
                u.avatar_color,
                u.avatar_photo,
                h.id AS household_id,
                h.unit_label,
                h.housing_type,
                a.name AS apartment_name,
                COALESCE(hp.total_drops, 0) AS total_drops
            FROM users u
            INNER JOIN households h ON h.id = u.household_id
            LEFT JOIN household_ponds hp ON hp.household_id = h.id
            LEFT JOIN apartments a ON a.id = h.apartment_id
            WHERE u.id IN ({$placeholders})
              AND h.housing_type IN ('apartment_unit', 'detached_house')
            ORDER BY total_drops DESC, u.full_name ASC
            LIMIT " . (int) $limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($userIds);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * 2. Apartman Sıralaması: Kullanıcının apartmanındaki dairelerin puanları
 */
function get_apartment_drops_leaderboard(array $currentUser, int $limit = 6): array
{
    $apartmentId = (int) ($currentUser['apartment_id'] ?? 0);
    if ($apartmentId <= 0) {
        return [];
    }

    $pdo = db();
    try {
        $sql = "
            SELECT
                h.id AS household_id,
                h.unit_label,
                h.housing_type,
                h.owner_user_id,
                COALESCE(u.full_name, 'Daire Sakini') AS display_name,
                u.id AS user_id,
                u.avatar_color,
                u.avatar_photo,
                COALESCE(hp.total_drops, 0) AS total_drops
            FROM households h
            LEFT JOIN users u ON u.id = h.owner_user_id
            LEFT JOIN household_ponds hp ON hp.household_id = h.id
            WHERE h.apartment_id = :aid
            ORDER BY total_drops DESC, h.unit_label ASC
            LIMIT " . (int) $limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':aid' => $apartmentId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * 3. Genel Sıralama: Apartmanların içindeki dairelerin puan toplamları sıralaması
 */
function get_general_apartments_drops_leaderboard(int $limit = 6): array
{
    $pdo = db();
    try {
        $sql = "
            SELECT
                a.id AS apartment_id,
                a.name AS apartment_name,
                n.district,
                n.city,
                COUNT(DISTINCT h.id) AS daire_count,
                COALESCE(SUM(hp.total_drops), 0) AS total_drops
            FROM apartments a
            INNER JOIN neighborhoods n ON n.id = a.neighborhood_id
            LEFT JOIN households h ON h.apartment_id = a.id
            LEFT JOIN household_ponds hp ON hp.household_id = h.id
            GROUP BY a.id, a.name, n.district, n.city
            ORDER BY total_drops DESC, daire_count DESC, a.name ASC
            LIMIT " . (int) $limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}
