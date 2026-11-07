<?php
/**
 * DEBI - Akış Etkinlikleri & Yapay Zeka (Gemini 3.8 Flash) Puanlama Modülü
 */

require_once __DIR__ . '/../gamification/gamification_service.php';
require_once __DIR__ . '/../friends/friendship_service.php';

/**
 * Yapay Zeka (Gemini 3.8 Flash) ile Etkinlik Ödül Puanı ve Gerekçesini Belirler.
 * Veritabanındaki topluluk tasarruf verilerini ve etkinliğin tasarruf potansiyelini inceler.
 * ai_reason KESİNLİKLE maksimum 100 karakter olarak sınırlandırılır.
 */
function calculate_event_ai_reward(array $eventData, array $contextData = []): array
{
    $title       = trim((string) ($eventData['title'] ?? ''));
    $description = trim((string) ($eventData['description'] ?? ''));
    $location    = trim((string) ($eventData['location'] ?? ''));
    $visibility  = trim((string) ($eventData['visibility'] ?? 'public'));

    $apiKey = defined('GEMINI_API_KEY') ? trim((string) GEMINI_API_KEY) : '';

    if ($apiKey !== '') {
        $prompt = <<<PROMPT
Sen Debi su tasarrufu sosyal ağının yapay zeka jürisisin.
Kullanıcılar akışta su tasarrufu, yağmur suyu hasadı, perlatör/musluk başlığı değişimi, su kaçağı tespiti, bahçe sulama optimizasyonu veya çevre bilinci etkinlikleri düzenliyor.
Etkinlik detaylarına göre bu etkinliğe katılan hanelere verilecek ödül puanını (Damla 💧) ve gerekçesini belirle:

Etkinlik Başlığı: {$title}
Açıklama: {$description}
Yer: {$location}
Akış Kapsamı: {$visibility}

Puanlama Kuralları:
- Puan: 15 ile 50 arasında bir tam sayı (integer).
- Yüksek pratik tasarruf (örn. yağmur hasadı deposu, perlatör montajı, kaçak onarımı, ortak sayaç takibi): 35 - 50 puan
- Orta düzey (toplu atölye, temizlik, eğitim, komşuluk bilinçlendirmesi): 25 - 35 puan
- Temel düzey (sohbet, fikir alışverişi, yürüyüş): 15 - 25 puan
- "ai_reason": Puanın neden verildiğini açıklayan Türkçe gerekçe. KESİNLİKLE MAKSİMUM 100 KARAKTER OLMALIDIR! 100 karakteri asla aşamaz.
PROMPT;

        $schema = [
            'type'       => 'object',
            'properties' => [
                'reward_points' => ['type' => 'integer'],
                'ai_reason'     => ['type' => 'string'],
            ],
            'required' => ['reward_points', 'ai_reason'],
        ];

        $payload = [
            'model' => 'gemini-3.8-flash',
            'input' => [
                ['type' => 'text', 'text' => $prompt],
            ],
            'response_format' => [
                'type'      => 'text',
                'mime_type' => 'application/json',
                'schema'    => $schema,
            ],
        ];

        try {
            $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/interactions');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'x-goog-api-key: ' . $apiKey,
                    'Content-Type: application/json',
                    'Api-Revision: 2026-05-20',
                ],
                CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_TIMEOUT        => 8,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response !== false && $httpCode >= 200 && $httpCode < 300) {
                $decoded = json_decode($response, true);
                $textOut = '';

                if (isset($decoded['output']) && is_array($decoded['output'])) {
                    foreach ($decoded['output'] as $block) {
                        if (($block['type'] ?? '') === 'text' && !empty($block['text'])) {
                            $textOut = $block['text'];
                            break;
                        }
                    }
                }

                if ($textOut !== '') {
                    $aiData = json_decode($textOut, true);
                    if (!empty($aiData['reward_points']) && !empty($aiData['ai_reason'])) {
                        $pts = max(15, min(50, (int) $aiData['reward_points']));
                        $rsn = trim((string) $aiData['ai_reason']);
                        if (mb_strlen($rsn, 'UTF-8') > 100) {
                            $rsn = mb_substr($rsn, 0, 97, 'UTF-8') . '...';
                        }
                        return [
                            'reward_points' => $pts,
                            'ai_reason'     => $rsn,
                            'ai_used'       => true,
                        ];
                    }
                }
            }
        } catch (Throwable $e) {
            // Hata durumunda yerel akıllı değerlendirmeye geç
        }
    }

    // Yerel Akıllı AI Sezgisel Değerlendirmesi (Güvenilir ve Hızlı)
    $allText = mb_strtolower($title . ' ' . $description, 'UTF-8');

    $highKeywords = ['hasat', 'yağmur', 'depo', 'perlatör', 'montaj', 'tamir', 'kaçak', 'sayaç', 'debi', 'arıza', 'boru'];
    $medKeywords  = ['atölye', 'eğitim', 'temizlik', 'fatura', 'tasarruf', 'bilinç', 'çevre', 'bahçe', 'komşu'];

    $highMatch = 0;
    foreach ($highKeywords as $w) {
        if (mb_strpos($allText, $w) !== false) {
            $highMatch++;
        }
    }

    $medMatch = 0;
    foreach ($medKeywords as $w) {
        if (mb_strpos($allText, $w) !== false) {
            $medMatch++;
        }
    }

    if ($highMatch > 0) {
        $points = min(50, 35 + ($highMatch * 5));
        $reason = "Kolektif su hasadı ve yüksek tasarruf etkisi nedeniyle {$points} Puan belirlendi.";
    } elseif ($medMatch > 0) {
        $points = min(35, 25 + ($medMatch * 4));
        $reason = "Topluluk su duyarlılığı ve hane tasarrufu kazanımıyla {$points} Puan verildi.";
    } else {
        $points = 20;
        $reason = "Su bilinci ve komşuluk dayanışması katkısıyla 20 Puan belirlendi.";
    }

    if (mb_strlen($reason, 'UTF-8') > 100) {
        $reason = mb_substr($reason, 0, 97, 'UTF-8') . '...';
    }

    return [
        'reward_points' => $points,
        'ai_reason'     => $reason,
        'ai_used'       => false,
    ];
}

/**
 * Akışta Yeni Etkinlik Oluşturur
 */
function create_event(array $user, array $input): array
{
    if (function_exists('ensure_gamification_schema')) {
        ensure_gamification_schema();
    }

    $title       = trim((string) ($input['title'] ?? ''));
    $description = trim((string) ($input['description'] ?? ''));
    $location    = trim((string) ($input['location'] ?? ''));
    $eventDate   = trim((string) ($input['event_date'] ?? ''));
    $visibility  = trim((string) ($input['visibility'] ?? 'public'));

    if (!in_array($visibility, ['friends', 'apartment', 'public'], true)) {
        $visibility = 'friends';
    }
    if ($visibility === 'apartment' && empty($user['apartment_id'])) {
        $visibility = 'friends';
    }

    if (mb_strlen($title) < 3) {
        return ['ok' => false, 'error' => 'Lütfen en az 3 karakterli bir etkinlik başlığı girin.'];
    }
    if (mb_strlen($description) < 6) {
        return ['ok' => false, 'error' => 'Lütfen etkinliğin amacını anlatan kısa bir açıklama girin.'];
    }
    if (mb_strlen($location) < 2) {
        return ['ok' => false, 'error' => 'Lütfen etkinlik yerini belirtin (Örn: Apartman Bahçesi).'];
    }

    $timestamp = strtotime($eventDate);
    if ($timestamp === false) {
        return ['ok' => false, 'error' => 'Lütfen geçerli bir tarih ve saat seçin.'];
    }
    $formattedDate = date('Y-m-d H:i:s', $timestamp);

    // Yapay zeka ile etkinliğe ödül puanı ve max 100 karakterlik gerekçe ata
    $aiEval       = calculate_event_ai_reward([
        'title'       => $title,
        'description' => $description,
        'location'    => $location,
        'visibility'  => $visibility,
    ]);
    $rewardPoints = (int) $aiEval['reward_points'];
    $aiReason     = (string) $aiEval['ai_reason'];

    $pdo = db();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO events (
                user_id, apartment_id, neighborhood_id, visibility,
                title, description, location, event_date, status,
                reward_points, ai_reason
            ) VALUES (
                :uid, :aid, :nid, :vis,
                :title, :desc, :loc, :edate, 'upcoming',
                :pts, :reason
            )
        ");

        $stmt->execute([
            ':uid'    => (int) $user['id'],
            ':aid'    => !empty($user['apartment_id']) ? (int) $user['apartment_id'] : null,
            ':nid'    => (int) $user['neighborhood_id'],
            ':vis'    => $visibility,
            ':title'  => $title,
            ':desc'   => $description,
            ':loc'    => $location,
            ':edate'  => $formattedDate,
            ':pts'    => $rewardPoints,
            ':reason' => $aiReason,
        ]);

        $eventId = (int) $pdo->lastInsertId();

        // Etkinliği oluşturan kullanıcı otomatik olarak katılımcı kaydedilir
        $pStmt = $pdo->prepare("
            INSERT INTO event_participants (event_id, user_id, household_id, rsvp_status)
            VALUES (:eid, :uid, :hid, 'attending')
            ON DUPLICATE KEY UPDATE rsvp_status = 'attending'
        ");
        $pStmt->execute([
            ':eid' => $eventId,
            ':uid' => (int) $user['id'],
            ':hid' => (int) $user['household_id'],
        ]);

        return [
            'ok'            => true,
            'event_id'      => $eventId,
            'reward_points' => $rewardPoints,
            'ai_reason'     => $aiReason,
        ];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Etkinlik oluşturulurken bir hata oluştu: ' . $e->getMessage()];
    }
}

/**
 * Akış İçin Etkinlikleri Listeler
 */
function get_feed_events(array $user, string $scope = 'friends', int $limit = 10): array
{
    $pdo       = db();
    $userId    = (int) $user['id'];
    $aptId     = (int) ($user['apartment_id'] ?? 0);
    $friendIds = function_exists('get_user_friend_ids') ? get_user_friend_ids($userId) : [];

    $whereClauses = [];
    $params       = [];

    if ($scope === 'friends') {
        $allowedUsers = array_values(array_unique(array_merge([$userId], $friendIds)));
        $inUsers      = implode(',', array_fill(0, count($allowedUsers), '?'));
        $whereClauses[] = "(e.visibility = 'friends' AND e.user_id IN ({$inUsers}))";
        $params = array_merge($params, $allowedUsers);
    } elseif ($scope === 'apartment' && $aptId > 0) {
        $whereClauses[] = "(e.visibility = 'apartment' AND e.apartment_id = ?)";
        $params[] = $aptId;
    } else {
        $whereClauses[] = "(e.visibility = 'public')";
    }

    $whereSql = implode(' OR ', $whereClauses);

    try {
        $sql = "
            SELECT
                e.*,
                u.full_name,
                u.avatar_color,
                u.avatar_photo,
                h.unit_label,
                h.housing_type,
                a.name AS apartment_name,
                (SELECT COUNT(*) FROM event_participants ep WHERE ep.event_id = e.id AND ep.rsvp_status = 'attending') AS participant_count,
                (SELECT ep2.rsvp_status FROM event_participants ep2 WHERE ep2.event_id = e.id AND ep2.user_id = {$userId} LIMIT 1) AS my_rsvp,
                (SELECT ep3.attended FROM event_participants ep3 WHERE ep3.event_id = e.id AND ep3.user_id = {$userId} LIMIT 1) AS my_attended,
                (SELECT ep4.points_awarded FROM event_participants ep4 WHERE ep4.event_id = e.id AND ep4.user_id = {$userId} LIMIT 1) AS my_points_awarded,
                (SELECT ep5.summary_note FROM event_participants ep5 WHERE ep5.event_id = e.id AND ep5.user_id = {$userId} LIMIT 1) AS my_summary_note
            FROM events e
            INNER JOIN users u ON u.id = e.user_id
            INNER JOIN households h ON h.id = u.household_id
            LEFT JOIN apartments a ON a.id = e.apartment_id
            WHERE ({$whereSql})
            ORDER BY
                CASE WHEN e.status = 'live' THEN 1
                     WHEN e.status = 'upcoming' THEN 2
                     ELSE 3 END ASC,
                e.event_date DESC
            LIMIT " . (int) $limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $events = $stmt->fetchAll();

        // Her etkinliğin katılımcı avatarlarını ve tamamlanan özetlerini çek
        foreach ($events as &$evt) {
            $eid = (int) $evt['id'];

            // Katılımcı avatarları (ilk 5)
            $pStmt = $pdo->prepare("
                SELECT u.id, u.full_name, u.avatar_color, u.avatar_photo, ep.attended, ep.summary_note
                FROM event_participants ep
                INNER JOIN users u ON u.id = ep.user_id
                WHERE ep.event_id = :eid AND ep.rsvp_status = 'attending'
                ORDER BY ep.id ASC
                LIMIT 6
            ");
            $pStmt->execute([':eid' => $eid]);
            $evt['attendees'] = $pStmt->fetchAll();

            // Tamamlanmış özetler
            if ($evt['status'] === 'completed') {
                $sStmt = $pdo->prepare("
                    SELECT u.full_name, u.avatar_color, u.avatar_photo, ep.summary_note
                    FROM event_participants ep
                    INNER JOIN users u ON u.id = ep.user_id
                    WHERE ep.event_id = :eid AND ep.summary_note IS NOT NULL AND ep.summary_note != ''
                    ORDER BY ep.updated_at DESC
                    LIMIT 4
                ");
                $sStmt->execute([':eid' => $eid]);
                $evt['takeaways'] = $sStmt->fetchAll();
            } else {
                $evt['takeaways'] = [];
            }
        }
        unset($evt);

        return $events;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Kullanıcının Dahil Olduğu Aktif / Canlı Etkinliği Döndürür
 * (Etkinlik canlıysa katılacağım diyenlerin feed tepesinde canlı sohbet & yoklama alanı açılır)
 */
function get_active_live_event_for_user(int $userId): ?array
{
    $pdo = db();
    try {
        $stmt = $pdo->prepare("
            SELECT
                e.*,
                u.full_name AS creator_name,
                u.avatar_color AS creator_color,
                u.avatar_photo AS creator_photo,
                a.name AS apartment_name,
                ep.rsvp_status,
                ep.attended,
                ep.points_awarded,
                ep.summary_note
            FROM events e
            INNER JOIN users u ON u.id = e.user_id
            LEFT JOIN apartments a ON a.id = e.apartment_id
            LEFT JOIN event_participants ep ON ep.event_id = e.id AND ep.user_id = :uid
            WHERE (e.status = 'live' 
                   OR (e.status = 'upcoming' AND e.event_date <= NOW() AND e.event_date >= DATE_SUB(NOW(), INTERVAL 4 HOUR))
                   OR (e.status = 'completed' AND ep.attended = 1 AND (ep.summary_note IS NULL OR ep.summary_note = '') AND e.updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)))
              AND (e.user_id = :uid2 OR ep.rsvp_status = 'attending')
            ORDER BY 
                CASE WHEN e.status = 'live' THEN 1
                     WHEN e.status = 'upcoming' THEN 2
                     ELSE 3 END ASC,
                e.event_date ASC
            LIMIT 1
        ");
        $stmt->execute([
            ':uid'  => $userId,
            ':uid2' => $userId,
        ]);
        $evt = $stmt->fetch();

        if (!$evt) {
            return null;
        }

        $eventId = (int) $evt['id'];

        // Tüm katılımcı listesi (Yoklama için)
        $pStmt = $pdo->prepare("
            SELECT
                ep.id AS participant_id,
                ep.user_id,
                ep.attended,
                ep.points_awarded,
                ep.summary_note,
                u.full_name,
                u.avatar_color,
                u.avatar_photo,
                h.unit_label
            FROM event_participants ep
            INNER JOIN users u ON u.id = ep.user_id
            INNER JOIN households h ON h.id = u.household_id
            WHERE ep.event_id = :eid AND ep.rsvp_status = 'attending'
            ORDER BY ep.attended DESC, u.full_name ASC
        ");
        $pStmt->execute([':eid' => $eventId]);
        $evt['participants'] = $pStmt->fetchAll();

        // Canlı Sohbet Mesajları
        $evt['messages'] = get_event_messages($eventId, 40);

        return $evt;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Katılacağım / Vazgeç (RSVP) İşlemi
 */
function toggle_event_rsvp(int $eventId, int $userId, int $householdId): array
{
    $pdo = db();
    try {
        $chk = $pdo->prepare("
            SELECT id, rsvp_status FROM event_participants
            WHERE event_id = :eid AND user_id = :uid
            LIMIT 1
        ");
        $chk->execute([':eid' => $eventId, ':uid' => $userId]);
        $row = $chk->fetch();

        if ($row && $row['rsvp_status'] === 'attending') {
            $upd = $pdo->prepare("UPDATE event_participants SET rsvp_status = 'declined' WHERE id = :id");
            $upd->execute([':id' => (int) $row['id']]);
            $attending = false;
        } else {
            $ins = $pdo->prepare("
                INSERT INTO event_participants (event_id, user_id, household_id, rsvp_status)
                VALUES (:eid, :uid, :hid, 'attending')
                ON DUPLICATE KEY UPDATE rsvp_status = 'attending'
            ");
            $ins->execute([
                ':eid' => $eventId,
                ':uid' => $userId,
                ':hid' => $householdId,
            ]);
            $attending = true;
        }

        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM event_participants WHERE event_id = :eid AND rsvp_status = 'attending'");
        $cntStmt->execute([':eid' => $eventId]);
        $newCount = (int) $cntStmt->fetchColumn();

        return ['ok' => true, 'attending' => $attending, 'count' => $newCount];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Etkinliği Canlıya Alır (Yalnızca oluşturan kişi)
 */
function start_event(int $eventId, int $userId): array
{
    $pdo = db();
    try {
        $stmt = $pdo->prepare("UPDATE events SET status = 'live' WHERE id = :eid AND user_id = :uid");
        $stmt->execute([':eid' => $eventId, ':uid' => $userId]);
        return ['ok' => ($stmt->rowCount() > 0)];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Etkinliği Oluşturan Kişinin Yoklama Alması (Gelenleri İşaretleme)
 */
function toggle_participant_attendance(int $eventId, int $targetUserId, int $creatorId): array
{
    $pdo = db();
    try {
        // Doğrulama: Etkinliği oluşturan kişi mi?
        $evStmt = $pdo->prepare("SELECT id FROM events WHERE id = :eid AND user_id = :uid LIMIT 1");
        $evStmt->execute([':eid' => $eventId, ':uid' => $creatorId]);
        if (!$evStmt->fetchColumn()) {
            return ['ok' => false, 'error' => 'Yalnızca etkinliği oluşturan kişi yoklama alabilir.'];
        }

        $pStmt = $pdo->prepare("
            SELECT id, attended FROM event_participants
            WHERE event_id = :eid AND user_id = :uid
            LIMIT 1
        ");
        $pStmt->execute([':eid' => $eventId, ':uid' => $targetUserId]);
        $row = $pStmt->fetch();

        if (!$row) {
            return ['ok' => false, 'error' => 'Katılımcı bulunamadı.'];
        }

        $newAttended = ((int) $row['attended'] === 1) ? 0 : 1;
        $upd = $pdo->prepare("UPDATE event_participants SET attended = :att WHERE id = :id");
        $upd->execute([':att' => $newAttended, ':id' => (int) $row['id']]);

        // Etkinlik yoklamasının alındığını işaretle
        $pdo->prepare("UPDATE events SET attendance_taken = 1 WHERE id = :eid")->execute([':eid' => $eventId]);

        return ['ok' => true, 'attended' => ($newAttended === 1)];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Etkinliği Tamamlar & Özet Alımını Başlatır
 */
function complete_event(int $eventId, int $userId): array
{
    $pdo = db();
    try {
        $stmt = $pdo->prepare("UPDATE events SET status = 'completed' WHERE id = :eid AND user_id = :uid");
        $stmt->execute([':eid' => $eventId, ':uid' => $userId]);
        return ['ok' => true];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Canlı Sohbet Mesajı Gönder
 */
function send_event_message(int $eventId, int $userId, string $message): array
{
    $msg = trim($message);
    if ($msg === '') {
        return ['ok' => false, 'error' => 'Mesaj boş olamaz.'];
    }

    $pdo = db();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO event_messages (event_id, user_id, message)
            VALUES (:eid, :uid, :msg)
        ");
        $stmt->execute([
            ':eid' => $eventId,
            ':uid' => $userId,
            ':msg' => $msg,
        ]);
        return ['ok' => true, 'message_id' => (int) $pdo->lastInsertId()];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Canlı Sohbet Mesajlarını Getir
 */
function get_event_messages(int $eventId, int $limit = 40): array
{
    $pdo = db();
    try {
        $stmt = $pdo->prepare("
            SELECT
                m.id,
                m.user_id,
                m.message,
                m.created_at,
                u.full_name,
                u.avatar_color,
                u.avatar_photo
            FROM event_messages m
            INNER JOIN users u ON u.id = m.user_id
            WHERE m.event_id = :eid
            ORDER BY m.created_at ASC
            LIMIT " . (int) $limit);
        $stmt->execute([':eid' => $eventId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Katılımcının "Ne Yaptım & Ne Öğrendim" Özetini Göndermesi ve Ödül Puanını Kazanması
 */
function submit_event_takeaway(int $eventId, int $userId, string $summaryNote): array
{
    $note = trim($summaryNote);
    if (mb_strlen($note) < 5) {
        return ['ok' => false, 'error' => 'Lütfen etkinlikte ne yaptığınızı ve ne öğrendiğinizi kısaca özetleyin.'];
    }

    $pdo = db();
    try {
        // Etkinlik bilgilerini ve ödül puanını çek
        $eStmt = $pdo->prepare("SELECT id, title, reward_points, status FROM events WHERE id = :eid LIMIT 1");
        $eStmt->execute([':eid' => $eventId]);
        $event = $eStmt->fetch();

        if (!$event) {
            return ['ok' => false, 'error' => 'Etkinlik bulunamadı.'];
        }

        // Katılımcı yoklamada var mı?
        $pStmt = $pdo->prepare("
            SELECT id, household_id, attended, points_awarded
            FROM event_participants
            WHERE event_id = :eid AND user_id = :uid
            LIMIT 1
        ");
        $pStmt->execute([':eid' => $eventId, ':uid' => $userId]);
        $part = $pStmt->fetch();

        if (!$part) {
            return ['ok' => false, 'error' => 'Bu etkinliğe katılım kaydınız bulunamadı.'];
        }

        if ((int) $part['attended'] !== 1) {
            return ['ok' => false, 'error' => 'Yoklamada bulunmadığınız için ödül puanı tanımlanamaz.'];
        }

        $hid          = (int) $part['household_id'];
        $rewardPoints = (int) $event['reward_points'];
        $already      = ((int) $part['points_awarded'] === 1);

        // Özeti kaydet
        $upd = $pdo->prepare("
            UPDATE event_participants
            SET summary_note   = :note,
                points_awarded = 1
            WHERE id = :id
        ");
        $upd->execute([':note' => $note, ':id' => (int) $part['id']]);

        // Puanı haneye ve daire göletine ekle (daha önce eklenmediyse)
        if (!$already && $rewardPoints > 0) {
            award_points(
                $userId,
                $hid,
                'event_attendance',
                $rewardPoints,
                '🎉 Etkinlik Katılımı: ' . $event['title'],
                'event_' . $eventId
            );
            if (function_exists('recalculate_household_water_savings')) {
                recalculate_household_water_savings($hid);
            }
        }

        return [
            'ok'            => true,
            'reward_points' => $rewardPoints,
            'already_had'   => $already,
        ];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}
