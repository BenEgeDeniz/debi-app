<?php
/**
 * DEBI - Arkadaşlık Sistemi Modülü (İstek Gönderme, Kabul Etme, Arkadaş Listesi ve Keşfet)
 */

/**
 * İki kullanıcı arasındaki arkadaşlık durumunu döndürür.
 * state: 'self' | 'none' | 'pending_sent' | 'pending_received' | 'accepted'
 */
function get_friendship_relation(int $currentUserId, int $targetUserId): array
{
    if ($currentUserId === $targetUserId) {
        return ['state' => 'self', 'id' => null];
    }

    try {
        $stmt = db()->prepare("
            SELECT id, requester_id, addressee_id, status
            FROM friendships
            WHERE (requester_id = :u1 AND addressee_id = :u2)
               OR (requester_id = :u3 AND addressee_id = :u4)
            LIMIT 1
        ");
        $stmt->execute([
            ':u1' => $currentUserId,
            ':u2' => $targetUserId,
            ':u3' => $targetUserId,
            ':u4' => $currentUserId,
        ]);
        $row = $stmt->fetch();

        if (!$row) {
            return ['state' => 'none', 'id' => null];
        }

        if ($row['status'] === 'accepted') {
            return ['state' => 'accepted', 'id' => (int) $row['id']];
        }

        if ((int) $row['requester_id'] === $currentUserId) {
            return ['state' => 'pending_sent', 'id' => (int) $row['id']];
        }

        return ['state' => 'pending_received', 'id' => (int) $row['id']];
    } catch (Throwable $e) {
        return ['state' => 'none', 'id' => null];
    }
}

/**
 * Arkadaşlık isteği gönderir (Karşı taraf zaten istek göndermişse otomatik kabul eder).
 */
function send_friend_request(int $requesterId, int $addresseeId): array
{
    if ($requesterId <= 0 || $addresseeId <= 0 || $requesterId === $addresseeId) {
        return ['ok' => false, 'error' => 'Geçersiz kullanıcı seçimi.'];
    }

    $rel = get_friendship_relation($requesterId, $addresseeId);

    if ($rel['state'] === 'accepted') {
        return ['ok' => false, 'error' => 'Bu kullanıcıyla zaten arkadaşsınız.'];
    }

    if ($rel['state'] === 'pending_sent') {
        return ['ok' => false, 'error' => 'Bu kullanıcıya zaten arkadaşlık isteği gönderdiniz.'];
    }

    if ($rel['state'] === 'pending_received') {
        return accept_friend_request($requesterId, $addresseeId);
    }

    try {
        $stmt = db()->prepare("
            INSERT INTO friendships (requester_id, addressee_id, status)
            VALUES (:req, :addr, 'pending')
        ");
        $stmt->execute([
            ':req'  => $requesterId,
            ':addr' => $addresseeId,
        ]);
        return ['ok' => true, 'message' => 'Arkadaşlık isteği gönderildi.'];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'İstek gönderilirken bir hata oluştu.'];
    }
}

/**
 * Gelen arkadaşlık isteğini kabul eder.
 */
function accept_friend_request(int $currentUserId, int $requesterUserId): array
{
    try {
        $stmt = db()->prepare("
            UPDATE friendships
            SET status = 'accepted'
            WHERE requester_id = :req AND addressee_id = :addr AND status = 'pending'
        ");
        $stmt->execute([
            ':req'  => $requesterUserId,
            ':addr' => $currentUserId,
        ]);

        if ($stmt->rowCount() > 0) {
            return ['ok' => true, 'message' => 'Arkadaşlık isteği kabul edildi.'];
        }
        return ['ok' => false, 'error' => 'Kabul edilecek bekleyen bir istek bulunamadı.'];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'İstek kabul edilirken bir hata oluştu.'];
    }
}

/**
 * Arkadaşlığı sonlandırır veya bekleyen isteği iptal eder / reddeder.
 */
function remove_friendship(int $currentUserId, int $otherUserId): array
{
    try {
        $stmt = db()->prepare("
            DELETE FROM friendships
            WHERE (requester_id = :u1 AND addressee_id = :u2)
               OR (requester_id = :u3 AND addressee_id = :u4)
        ");
        $stmt->execute([
            ':u1' => $currentUserId,
            ':u2' => $otherUserId,
            ':u3' => $otherUserId,
            ':u4' => $currentUserId,
        ]);
        return ['ok' => true, 'message' => 'İşlem tamamlandı.'];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'İşlem sırasında bir hata oluştu.'];
    }
}

/**
 * Kullanıcının kabul edilmiş arkadaşlarının ID listesini döndürür.
 */
function get_user_friend_ids(int $userId): array
{
    try {
        $stmt = db()->prepare("
            SELECT
                CASE WHEN requester_id = :u1 THEN addressee_id ELSE requester_id END AS friend_id
            FROM friendships
            WHERE (requester_id = :u2 OR addressee_id = :u3)
              AND status = 'accepted'
        ");
        $stmt->execute([
            ':u1' => $userId,
            ':u2' => $userId,
            ':u3' => $userId,
        ]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Kullanıcının toplam arkadaş sayısını hızlıca döndürür (COUNT sorgusu).
 */
function count_user_friends(int $userId): int
{
    try {
        $stmt = db()->prepare("
            SELECT COUNT(*)
            FROM friendships
            WHERE (requester_id = :u1 OR addressee_id = :u2)
              AND status = 'accepted'
        ");
        $stmt->execute([':u1' => $userId, ':u2' => $userId]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Kullanıcının kabul edilmiş arkadaşlarını sayfalı (LIMIT/OFFSET) olarak döndürür.
 */
function get_user_friends(int $userId, int $limit = 12, int $offset = 0): array
{
    $year   = (int) date('Y');
    $month  = (int) date('n');
    $limit  = max(1, min(50, $limit));
    $offset = max(0, $offset);

    try {
        $stmt = db()->prepare("
            SELECT
                u.id,
                u.full_name,
                u.nickname,
                u.avatar_color,
                u.avatar_photo,
                u.bio,
                u.role,
                h.housing_type,
                h.unit_label,
                n.city,
                n.district,
                n.name AS neighborhood_name,
                a.name AS apartment_name,
                w.daily_liters_per_person,
                w.consumption_m3
            FROM friendships f
            INNER JOIN users u
                ON u.id = (CASE WHEN f.requester_id = :u1 THEN f.addressee_id ELSE f.requester_id END)
            INNER JOIN households h ON h.id = u.household_id
            INNER JOIN neighborhoods n ON n.id = u.neighborhood_id
            LEFT JOIN apartments a ON a.id = u.apartment_id
            LEFT JOIN water_logs w ON w.household_id = h.id AND w.period_year = :y AND w.period_month = :m
            WHERE (f.requester_id = :u2 OR f.addressee_id = :u3)
              AND f.status = 'accepted'
            ORDER BY u.full_name ASC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute([
            ':u1' => $userId,
            ':u2' => $userId,
            ':u3' => $userId,
            ':y'  => $year,
            ':m'  => $month,
        ]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Kullanıcıya gelen bekleyen arkadaşlık isteklerini döndürür.
 */
function get_incoming_friend_requests(int $userId): array
{
    try {
        $stmt = db()->prepare("
            SELECT
                f.id AS friendship_id,
                f.created_at AS requested_at,
                u.id AS user_id,
                u.full_name,
                u.nickname,
                u.avatar_color,
                u.avatar_photo,
                u.bio,
                h.housing_type,
                h.unit_label,
                n.city,
                n.district,
                n.name AS neighborhood_name,
                a.name AS apartment_name
            FROM friendships f
            INNER JOIN users u ON u.id = f.requester_id
            INNER JOIN households h ON h.id = u.household_id
            INNER JOIN neighborhoods n ON n.id = u.neighborhood_id
            LEFT JOIN apartments a ON a.id = u.apartment_id
            WHERE f.addressee_id = :uid AND f.status = 'pending'
            ORDER BY f.created_at DESC
            LIMIT 20
        ");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Bekleyen gelen arkadaşlık isteği sayısı
 */
function count_incoming_friend_requests(int $userId): int
{
    try {
        $stmt = db()->prepare("
            SELECT COUNT(*) FROM friendships
            WHERE addressee_id = :uid AND status = 'pending'
        ");
        $stmt->execute([':uid' => $userId]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Yalnızca arama yapıldığında eşleşen kullanıcıları döndürür (En fazla 8 sonuç).
 */
function search_users_for_friendship(int $currentUserId, string $searchQuery = '', int $limit = 8): array
{
    $q = trim($searchQuery);
    if ($q === '') {
        return [];
    }

    $pdo    = db();
    $like   = '%' . $q . '%';
    $params = [
        ':me' => $currentUserId,
        ':q1' => $like,
        ':q2' => $like,
        ':q3' => $like,
        ':q4' => $like,
        ':q5' => $like,
    ];

    try {
        $sql = "
            SELECT
                u.id,
                u.full_name,
                u.nickname,
                u.avatar_color,
                u.avatar_photo,
                u.bio,
                h.housing_type,
                h.unit_label,
                n.city,
                n.district,
                n.name AS neighborhood_name,
                a.name AS apartment_name
            FROM users u
            INNER JOIN households h ON h.id = u.household_id
            INNER JOIN neighborhoods n ON n.id = u.neighborhood_id
            LEFT JOIN apartments a ON a.id = u.apartment_id
            WHERE u.id != :me
              AND (u.full_name LIKE :q1 OR u.nickname LIKE :q2 OR a.name LIKE :q3 OR n.district LIKE :q4 OR n.city LIKE :q5)
            ORDER BY u.full_name ASC
            LIMIT " . (int) max(1, min(15, $limit));

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();

        foreach ($users as &$u) {
            $u['relation'] = get_friendship_relation($currentUserId, (int) $u['id']);
        }
        unset($u);

        return $users;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Sağ sütun için Arkadaşlar + Apartman Komşuları arasında bu ay en az su tüketenler
 */
function get_friends_and_apartment_leaderboard(array $currentUser, int $limit = 6): array
{
    $pdo       = db();
    $year      = (int) date('Y');
    $month     = (int) date('n');
    $friendIds = get_user_friend_ids((int) $currentUser['id']);
    $userIds   = array_values(array_unique(array_merge([(int) $currentUser['id']], $friendIds)));

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $params       = $userIds;

    $aptCondition = '';
    if (!empty($currentUser['apartment_id'])) {
        $aptCondition = ' OR u.apartment_id = ?';
        $params[]     = (int) $currentUser['apartment_id'];
    }

    $params[] = $year;
    $params[] = $month;

    try {
        $sql = "
            SELECT
                u.id AS user_id,
                u.full_name,
                u.avatar_color,
                h.unit_label,
                h.housing_type,
                a.name AS apartment_name,
                w.daily_liters_per_person,
                w.consumption_m3
            FROM water_logs w
            INNER JOIN households h ON h.id = w.household_id
            INNER JOIN users u ON u.household_id = h.id
            LEFT JOIN apartments a ON a.id = h.apartment_id
            WHERE (u.id IN ({$placeholders}){$aptCondition})
              AND w.period_year = ?
              AND w.period_month = ?
            ORDER BY w.daily_liters_per_person ASC
            LIMIT " . (int) $limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}
