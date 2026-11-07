<?php

function create_event(array $user, array $input): array
{
    $title = trim((string) ($input['title'] ?? ''));
    $description = trim((string) ($input['description'] ?? ''));
    $location = trim((string) ($input['location'] ?? ''));
    $date = trim((string) ($input['event_date'] ?? ''));
    $start = trim((string) ($input['start_time'] ?? ''));
    $end = trim((string) ($input['end_time'] ?? '')) ?: null;
    $scope = ($input['scope'] ?? 'neighborhood') === 'public' ? 'public' : 'neighborhood';
    if (mb_strlen($title, 'UTF-8') < 4 || mb_strlen($description, 'UTF-8') < 10 || $location === '' || $date === '' || $start === '') {
        return ['ok' => false, 'error' => 'Başlık, açıklama, yer, tarih ve başlama saati zorunludur.'];
    }
    $dateObject = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dateObject || $dateObject->format('Y-m-d') !== $date || $date < date('Y-m-d')) {
        return ['ok' => false, 'error' => 'Etkinlik tarihi geçerli ve bugün veya daha sonrası olmalıdır.'];
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $start) || ($end !== null && !preg_match('/^\d{2}:\d{2}$/', $end))) {
        return ['ok' => false, 'error' => 'Etkinlik saati geçersiz.'];
    }
    if ($end !== null && $end <= $start) {
        return ['ok' => false, 'error' => 'Bitiş saati başlangıç saatinden sonra olmalıdır.'];
    }
    if ($scope === 'neighborhood' && empty($user['neighborhood_id'])) {
        return ['ok' => false, 'error' => 'Mahalle bilgisi olmayan kullanıcı mahalle etkinliği oluşturamaz.'];
    }
    $ai = score_event_with_ai($title, $description, $date, $start, $end);
    $stmt = db()->prepare("INSERT INTO events (creator_id, neighborhood_id, scope, title, description, location, event_date, start_time, end_time, points, points_reason) VALUES (:uid, :nid, :scope, :title, :description, :location, :date, :start, :end, :points, :reason)");
    $stmt->execute([
        ':uid' => (int) $user['id'], ':nid' => (int) $user['neighborhood_id'], ':scope' => $scope,
        ':title' => $title, ':description' => $description, ':location' => $location, ':date' => $date,
        ':start' => $start, ':end' => $end, ':points' => $ai['points'], ':reason' => $ai['reason'],
    ]);
    return ['ok' => true, 'event_id' => (int) db()->lastInsertId(), 'ai' => $ai];
}

function get_events_for_user(array $user): array
{
    $stmt = db()->prepare("
        SELECT e.*, u.full_name AS creator_name,
               ea.status AS attendee_status, ep.ai_status AS photo_status,
               (SELECT COUNT(*) FROM event_attendees ea2 WHERE ea2.event_id = e.id) AS attendee_count
        FROM events e
        INNER JOIN users u ON u.id = e.creator_id
        LEFT JOIN event_attendees ea ON ea.event_id = e.id AND ea.user_id = :uid
        LEFT JOIN event_photos ep ON ep.event_id = e.id AND ep.user_id = :uid
        WHERE e.scope = 'public' OR e.neighborhood_id = :nid
        ORDER BY e.event_date ASC, e.start_time ASC
    ");
    $stmt->execute([':uid' => (int) $user['id'], ':nid' => (int) $user['neighborhood_id']]);
    return $stmt->fetchAll();
}

function join_event(array $user, int $eventId): array
{
    $stmt = db()->prepare("SELECT id FROM events WHERE id = :id AND (scope = 'public' OR neighborhood_id = :nid) LIMIT 1");
    $stmt->execute([':id' => $eventId, ':nid' => (int) $user['neighborhood_id']]);
    if (!$stmt->fetchColumn()) return ['ok' => false, 'error' => 'Etkinlik bulunamadı.'];
    $stmt = db()->prepare("INSERT IGNORE INTO event_attendees (event_id, user_id) VALUES (:eid, :uid)");
    $stmt->execute([':eid' => $eventId, ':uid' => (int) $user['id']]);
    return ['ok' => true, 'message' => 'Etkinliğe katılımınız kaydedildi.'];
}

function mark_event_attendance(array $user, int $eventId, array $attendeeIds): array
{
    $stmt = db()->prepare("SELECT id FROM events WHERE id = :eid AND creator_id = :uid LIMIT 1");
    $stmt->execute([':eid' => $eventId, ':uid' => (int) $user['id']]);
    if (!$stmt->fetchColumn()) return ['ok' => false, 'error' => 'Bu etkinlik için yoklama yetkiniz yok.'];
    db()->prepare("UPDATE event_attendees SET status = 'absent', marked_at = NOW() WHERE event_id = :eid")->execute([':eid' => $eventId]);
    if ($attendeeIds) {
        $sql = "UPDATE event_attendees SET status = 'attended', marked_at = NOW() WHERE event_id = :eid AND user_id IN (" . implode(',', array_fill(0, count($attendeeIds), '?')) . ")";
        $stmt = db()->prepare($sql);
        $stmt->execute(array_merge([$eventId], array_map('intval', $attendeeIds)));
    }
    return ['ok' => true, 'message' => 'Yoklama kaydedildi. Fotoğrafı onaylanan katılımcılara puan verilecek.'];
}

function get_event_attendees(array $user, int $eventId): array
{
    $stmt = db()->prepare("SELECT ea.*, u.full_name FROM event_attendees ea INNER JOIN users u ON u.id = ea.user_id INNER JOIN events e ON e.id = ea.event_id WHERE ea.event_id = :eid AND e.creator_id = :uid ORDER BY u.full_name");
    $stmt->execute([':eid' => $eventId, ':uid' => (int) $user['id']]);
    return $stmt->fetchAll();
}

function upload_event_photo(array $user, int $eventId, array $file): array
{
    if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return ['ok' => false, 'error' => 'Etkinlik fotoğrafı seçin.'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime]) || ($file['size'] ?? 0) > 8 * 1024 * 1024) return ['ok' => false, 'error' => 'Fotoğraf JPG, PNG veya WebP olmalı ve 8 MB’ı geçmemeli.'];
    $stmt = db()->prepare("SELECT e.*, ea.status FROM events e INNER JOIN event_attendees ea ON ea.event_id = e.id AND ea.user_id = :uid WHERE e.id = :eid LIMIT 1");
    $stmt->execute([':uid' => (int) $user['id'], ':eid' => $eventId]);
    $event = $stmt->fetch();
    if (!$event || $event['status'] !== 'attended') return ['ok' => false, 'error' => 'Fotoğraf yüklemek için etkinlikte yoklama ile katılmış olmalısınız.'];
    $eventEnd = $event['end_time'] ?: $event['start_time'];
    $eventEndAt = strtotime($event['event_date'] . ' ' . $eventEnd);
    if ($eventEndAt !== false && time() < $eventEndAt) {
        return ['ok' => false, 'error' => 'Etkinlik sona erdikten sonra fotoğraf yükleyebilirsiniz.'];
    }
    $dir = __DIR__ . '/../../uploads/events';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) return ['ok' => false, 'error' => 'Fotoğraf klasörü oluşturulamadı.'];
    $name = 'event_' . date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . $extensions[$mime];
    $path = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $path)) return ['ok' => false, 'error' => 'Fotoğraf kaydedilemedi.'];
    $ai = verify_event_photo_with_ai($path, $event['title'], $event['description']);
    if (!$ai['ok']) return $ai;
    $relPath = 'uploads/events/' . $name;
    db()->prepare("INSERT INTO event_photos (event_id, user_id, image_path, ai_status, ai_reason, reviewed_at) VALUES (:eid, :uid, :path, :status, :reason, NOW()) ON DUPLICATE KEY UPDATE image_path = VALUES(image_path), ai_status = VALUES(ai_status), ai_reason = VALUES(ai_reason), reviewed_at = NOW()")->execute([
        ':eid' => $eventId, ':uid' => (int) $user['id'], ':path' => $relPath, ':status' => $ai['approved'] ? 'approved' : 'rejected', ':reason' => $ai['reason'],
    ]);
    if (!$ai['approved']) return ['ok' => true, 'approved' => false, 'message' => 'Fotoğraf etkinlikle ilişkili bulunmadı: ' . $ai['reason']];
    award_event_points_if_eligible($eventId, (int) $user['id'], $event);
    return ['ok' => true, 'approved' => true, 'message' => 'Fotoğraf doğrulandı; etkinlik puanınız hanenize eklendi.'];
}

function award_event_points_if_eligible(int $eventId, int $userId, array $event): void
{
    $stmt = db()->prepare("SELECT ea.status, ep.ai_status, u.household_id FROM event_attendees ea INNER JOIN users u ON u.id = ea.user_id LEFT JOIN event_photos ep ON ep.event_id = ea.event_id AND ep.user_id = ea.user_id WHERE ea.event_id = :eid AND ea.user_id = :uid");
    $stmt->execute([':eid' => $eventId, ':uid' => $userId]);
    $row = $stmt->fetch();
    if (!$row || $row['status'] !== 'attended' || $row['ai_status'] !== 'approved') return;
    $points = award_points($userId, (int) $row['household_id'], 'event_participation', (int) $event['points'], 'Etkinlik katılımı: ' . $event['title'], 'event_' . $eventId . '_user_' . $userId);
    if ($points > 0) db()->prepare("UPDATE event_attendees SET points_awarded = 1 WHERE event_id = :eid AND user_id = :uid")->execute([':eid' => $eventId, ':uid' => $userId]);
}
