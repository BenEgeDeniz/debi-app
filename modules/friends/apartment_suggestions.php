<?php
/**
 * Apartman içinden arkadaş önerileri.
 */

function get_apartment_friend_suggestions(array $currentUser, int $limit = 50): array
{
    $apartmentId = (int) ($currentUser['apartment_id'] ?? 0);
    $userId = (int) ($currentUser['id'] ?? 0);
    if ($apartmentId <= 0 || $userId <= 0) {
        return [];
    }

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
                a.name AS apartment_name
            FROM users u
            INNER JOIN households h ON h.id = u.household_id
            INNER JOIN neighborhoods n ON n.id = u.neighborhood_id
            INNER JOIN apartments a ON a.id = u.apartment_id
            WHERE u.apartment_id = :apartment_id
              AND u.id != :user_id
            ORDER BY u.full_name ASC
            LIMIT " . (int) max(1, min(100, $limit)));
        $stmt->execute([
            ':apartment_id' => $apartmentId,
            ':user_id' => $userId,
        ]);
        $users = $stmt->fetchAll();

        foreach ($users as &$user) {
            $user['relation'] = get_friendship_relation($userId, (int) $user['id']);
        }
        unset($user);

        return $users;
    } catch (Throwable $e) {
        return [];
    }
}
