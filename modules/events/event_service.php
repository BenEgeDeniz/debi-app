<?php

function create_event(array $user, array $input): array
{
    $title = trim((string) ($input['title'] ?? ''));
    $description = trim((string) ($input['description'] ?? ''));
    $location = trim((string) ($input['location'] ?? ''));
    $date = trim((string) ($input['event_date'] ?? ''));
    $start = trim((string) ($input['start_time'] ?? ''));
    $scope = ($input['scope'] ?? 'neighborhood') === 'public' ? 'public' : 'neighborhood';
    if (mb_strlen($title, 'UTF-8') < 4 || mb_strlen($description, 'UTF-8') < 10 || $location === '' || $date === '' || $start === '') {
        return ['ok' => false, 'error' => 'Başlık, açıklama, yer, tarih ve başlama saati zorunludur.'];
    }
    $dateObject = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dateObject || $dateObject->format('Y-m-d') !== $date || $date < date('Y-m-d')) {
        return ['ok' => false, 'error' => 'Etkinlik tarihi geçerli ve bugün veya daha sonrası olmalıdır.'];
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $start)) {
        return ['ok' => false, 'error' => 'Etkinlik saati geçersiz.'];
    }
    if ($scope === 'neighborhood' && empty($user['neighborhood_id'])) {
        return ['ok' => false, 'error' => 'Mahalle bilgisi olmayan kullanıcı mahalle etkinliği oluşturamaz.'];
    }
    $ai = score_event_with_ai($title, $description, $date, $start);
    $stmt = db()->prepare("INSERT INTO events (creator_id, neighborhood_id, scope, title, description, location, event_date, start_time, points, points_reason, criteria_sources) VALUES (:uid, :nid, :scope, :title, :description, :location, :date, :start, :points, :reason, :sources)");
    $stmt->execute([
        ':uid' => (int) $user['id'], ':nid' => (int) $user['neighborhood_id'], ':scope' => $scope,
        ':title' => $title, ':description' => $description, ':location' => $location, ':date' => $date,
        ':start' => $start, ':points' => $ai['points'], ':reason' => $ai['reason'],
        ':sources' => $ai['criteria_sources'] ?? null,
    ]);
    $eventId = (int) db()->lastInsertId();
    db()->prepare("INSERT INTO event_attendees (event_id, user_id) VALUES (:eid, :uid)")
        ->execute([':eid' => $eventId, ':uid' => (int) $user['id']]);
    return ['ok' => true, 'event_id' => $eventId, 'ai' => $ai];
}

function get_event_time_state(array $event): array
{
    $startAt = strtotime($event['event_date'] . ' ' . $event['start_time']);
    $now = time();
    $attendanceRecorded = !empty($event['attendance_recorded']);
    $missedAttendanceWindow = !empty($event['cancelled_at'])
        || (empty($event['ended_at'])
        && !$attendanceRecorded
        && $startAt !== false
        && $now >= ($startAt + 3600));

    return [
        'started' => $startAt !== false && $now >= $startAt,
        'ended' => !empty($event['ended_at']) || $missedAttendanceWindow,
        'cancelled' => $missedAttendanceWindow,
    ];
}

function get_events_for_user(array $user): array
{
    try {
        $stmt = db()->prepare("
        SELECT e.*, u.full_name AS creator_name, u.nickname AS creator_nickname,
               u.avatar_color AS creator_avatar_color, u.avatar_photo AS creator_avatar_photo,
               ea.status AS attendee_status, ep.ai_status AS photo_status,
               EXISTS (
                   SELECT 1
                   FROM event_attendees marked_attendance
                   WHERE marked_attendance.event_id = e.id
                     AND marked_attendance.marked_at IS NOT NULL
               ) AS attendance_recorded,
               (SELECT COUNT(*) FROM event_attendees ea2 WHERE ea2.event_id = e.id) AS attendee_count
        FROM events e
        INNER JOIN users u ON u.id = e.creator_id
        LEFT JOIN event_attendees ea ON ea.event_id = e.id AND ea.user_id = :uid
        LEFT JOIN event_photos ep ON ep.event_id = e.id AND ep.user_id = :photo_uid
        WHERE e.scope = 'public' OR e.neighborhood_id = :nid
        ORDER BY e.event_date ASC, e.start_time ASC
        ");
        $stmt->execute([
            ':uid' => (int) $user['id'],
            ':photo_uid' => (int) $user['id'],
            ':nid' => (int) $user['neighborhood_id'],
        ]);
        $events = $stmt->fetchAll();
        foreach ($events as &$event) {
            $event = array_merge($event, get_event_time_state($event));
        }
        unset($event);
        return $events;
    } catch (PDOException $e) {
        $message = stripos($e->getMessage(), 'table') !== false
            ? 'Etkinlik tabloları bulunamadı. Migration durumu applied görünüyorsa uygulamanın bağlı olduğu veritabanını kontrol edin.'
            : 'Etkinlikler yüklenirken veritabanı hatası oluştu.';
        throw new RuntimeException($message, 0, $e);
    }
}

function count_started_attending_events(int $userId): int
{
    $stmt = db()->prepare("
        SELECT COUNT(*)
        FROM event_attendees ea
        INNER JOIN events e ON e.id = ea.event_id
        WHERE ea.user_id = :uid
          AND e.creator_id <> :creator_uid
          AND e.ended_at IS NULL
          AND e.cancelled_at IS NULL
          AND TIMESTAMP(e.event_date, e.start_time) <= NOW()
          AND (
              TIMESTAMP(e.event_date, e.start_time) > DATE_SUB(NOW(), INTERVAL 1 HOUR)
              OR EXISTS (
                  SELECT 1
                  FROM event_attendees marked_attendance
                  WHERE marked_attendance.event_id = e.id
                    AND marked_attendance.marked_at IS NOT NULL
              )
          )
    ");
    $stmt->execute([':uid' => $userId, ':creator_uid' => $userId]);
    return (int) $stmt->fetchColumn();
}

function cancel_events_without_attendance(): int
{
    $stmt = db()->prepare("
        UPDATE events
        SET cancelled_at = NOW()
        WHERE ended_at IS NULL
          AND cancelled_at IS NULL
          AND TIMESTAMP(event_date, start_time) <= DATE_SUB(NOW(), INTERVAL 1 HOUR)
          AND NOT EXISTS (
              SELECT 1
              FROM event_attendees ea
              WHERE ea.event_id = events.id
                AND ea.marked_at IS NOT NULL
          )
    ");
    $stmt->execute();
    return $stmt->rowCount();
}

function get_started_created_event(int $userId): ?array
{
    $stmt = db()->prepare("
        SELECT e.*, u.full_name AS creator_name, u.nickname AS creator_nickname
        FROM events e
        INNER JOIN users u ON u.id = e.creator_id
        WHERE e.creator_id = :uid
          AND e.ended_at IS NULL
          AND e.cancelled_at IS NULL
          AND TIMESTAMP(e.event_date, e.start_time) > DATE_SUB(NOW(), INTERVAL 1 HOUR)
          AND TIMESTAMP(e.event_date, e.start_time) <= NOW()
        ORDER BY TIMESTAMP(e.event_date, e.start_time) ASC
        LIMIT 1
    ");
    $stmt->execute([':uid' => $userId]);
    return $stmt->fetch() ?: null;
}

function categorize_events_for_user(array $events): array
{
    $sections = [
        'active' => [],
        'attending' => [],
        'available' => [],
        'past' => [],
    ];

    foreach ($events as $event) {
        $status = $event['attendee_status'] ?? null;
        $needsPhoto = $event['ended'] && !$event['cancelled']
            && $status === 'attended'
            && ($event['photo_status'] ?? null) !== 'approved';

        if ($event['started'] && !$event['ended'] && $status) {
            $sections['active'][] = $event;
        } elseif ($needsPhoto) {
            $sections['active'][] = $event;
        } elseif (!$event['started'] && $status) {
            $sections['attending'][] = $event;
        } elseif (!$event['started']) {
            $sections['available'][] = $event;
        } else {
            $sections['past'][] = $event;
        }
    }

    return $sections;
}

function join_event(array $user, int $eventId): array
{
    $stmt = db()->prepare("SELECT * FROM events WHERE id = :id AND (scope = 'public' OR neighborhood_id = :nid) LIMIT 1");
    $stmt->execute([':id' => $eventId, ':nid' => (int) $user['neighborhood_id']]);
    $event = $stmt->fetch();
    if (!$event) return ['ok' => false, 'error' => 'Etkinlik bulunamadı.'];
    $state = get_event_time_state($event);
    if ($state['ended']) return ['ok' => false, 'error' => 'Bitirilmiş etkinliğe artık katılım yapılamaz.'];
    if ($state['started']) return ['ok' => false, 'error' => 'Etkinlik başladı; artık katılım kaydı alınamıyor.'];
    $stmt = db()->prepare("INSERT IGNORE INTO event_attendees (event_id, user_id) VALUES (:eid, :uid)");
    $stmt->execute([':eid' => $eventId, ':uid' => (int) $user['id']]);
    return ['ok' => true, 'message' => 'Etkinliğe katılımınız kaydedildi.'];
}

function leave_event(array $user, int $eventId): array
{
    $stmt = db()->prepare("
        SELECT e.*, ea.status AS attendee_status
        FROM events e
        INNER JOIN event_attendees ea ON ea.event_id = e.id AND ea.user_id = :uid
        WHERE e.id = :eid AND (e.scope = 'public' OR e.neighborhood_id = :nid)
        LIMIT 1
    ");
    $stmt->execute([
        ':uid' => (int) $user['id'],
        ':eid' => $eventId,
        ':nid' => (int) $user['neighborhood_id'],
    ]);
    $event = $stmt->fetch();
    if (!$event) {
        return ['ok' => false, 'error' => 'Bu etkinlikte kayıtlı katılımınız bulunmuyor.'];
    }

    $state = get_event_time_state($event);
    if ($state['started'] || $state['ended']) {
        return ['ok' => false, 'error' => 'Etkinlik başladıktan sonra katılım geri çekilemez.'];
    }
    if ((int) $event['creator_id'] === (int) $user['id']) {
        return ['ok' => false, 'error' => 'Etkinlik oluşturan kişi kendi katılımını geri çekemez.'];
    }

    db()->prepare('DELETE FROM event_attendees WHERE event_id = :eid AND user_id = :uid')
        ->execute([':eid' => $eventId, ':uid' => (int) $user['id']]);
    return ['ok' => true, 'message' => 'Etkinlik katılımınız geri çekildi.'];
}

function finish_event(array $user, int $eventId): array
{
    $stmt = db()->prepare("SELECT * FROM events WHERE id = :eid AND creator_id = :uid LIMIT 1");
    $stmt->execute([':eid' => $eventId, ':uid' => (int) $user['id']]);
    $event = $stmt->fetch();
    if (!$event) return ['ok' => false, 'error' => 'Bu etkinliği bitirme yetkiniz yok.'];
    $state = get_event_time_state($event);
    if (!$state['started']) return ['ok' => false, 'error' => 'Etkinlik başlamadan bitirilemez.'];
    if ($state['ended']) return ['ok' => false, 'error' => 'Etkinlik zaten bitirilmiş.'];
    db()->prepare("UPDATE events SET ended_at = NOW() WHERE id = :eid AND ended_at IS NULL")
        ->execute([':eid' => $eventId]);
    return ['ok' => true, 'message' => 'Etkinlik bitirildi. Katılımcılar artık fotoğraflarını yükleyebilir.'];
}

function mark_event_attendance(array $user, int $eventId, array $attendeeIds): array
{
    $stmt = db()->prepare("SELECT * FROM events WHERE id = :eid AND creator_id = :uid LIMIT 1");
    $stmt->execute([':eid' => $eventId, ':uid' => (int) $user['id']]);
    $event = $stmt->fetch();
    if (!$event) return ['ok' => false, 'error' => 'Bu etkinlik için yoklama yetkiniz yok.'];
    $state = get_event_time_state($event);
    if (!$state['started']) {
        return ['ok' => false, 'error' => 'Yoklama etkinliğin başlama saatinde açılır.'];
    }
    if ($state['ended']) {
        return ['ok' => false, 'error' => 'Etkinlik bitirildiği için yoklama artık değiştirilemez.'];
    }
    db()->prepare("UPDATE event_attendees SET status = 'absent', marked_at = NOW() WHERE event_id = :eid AND user_id <> :creator_id")
        ->execute([':eid' => $eventId, ':creator_id' => (int) $user['id']]);
    if ($attendeeIds) {
        $placeholders = [];
        $params = [':event_id' => $eventId];
        foreach (array_values($attendeeIds) as $index => $attendeeId) {
            $placeholder = ':attendee_' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = (int) $attendeeId;
        }
        $sql = "UPDATE event_attendees
                SET status = 'attended', marked_at = NOW()
                WHERE event_id = :event_id AND user_id IN (" . implode(',', $placeholders) . ")";
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
    }
    db()->prepare("UPDATE event_attendees SET status = 'attended', marked_at = NOW() WHERE event_id = :eid AND user_id = :creator_id")
        ->execute([':eid' => $eventId, ':creator_id' => (int) $user['id']]);
    return ['ok' => true, 'message' => 'Yoklama kaydedildi. Fotoğrafı onaylanan katılımcılara puan verilecek.'];
}

function get_event_attendees(array $user, int $eventId): array
{
    $stmt = db()->prepare("SELECT ea.*, u.full_name, u.avatar_color, u.avatar_photo, u.nickname FROM event_attendees ea INNER JOIN users u ON u.id = ea.user_id INNER JOIN events e ON e.id = ea.event_id WHERE ea.event_id = :eid AND e.creator_id = :uid ORDER BY u.full_name");
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
    if (empty($event['ended_at'])) return ['ok' => false, 'error' => 'Etkinlik sahibi etkinliği bitirdikten sonra fotoğraf yükleyebilirsiniz.'];
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
    if (!$ai['approved']) return ['ok' => false, 'approved' => false, 'error' => 'Fotoğraf etkinlikle ilişkili bulunmadı: ' . $ai['reason']];
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
