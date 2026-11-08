<?php

function debbie_bot_user(): ?array
{
    static $bot = null;
    if ($bot !== null) {
        return $bot ?: null;
    }

    $stmt = db()->prepare('
        SELECT u.*, h.housing_type, h.unit_label
        FROM users u
        INNER JOIN households h ON h.id = u.household_id
        WHERE u.nickname = :nickname AND u.is_ai_bot = 1
        LIMIT 1
    ');
    $stmt->execute([':nickname' => 'debbie']);
    $bot = $stmt->fetch() ?: false;
    return $bot ?: null;
}

function debbie_mention_in_text(string $content): bool
{
    return preg_match('/@debbie(?:;|\b)/i', $content) === 1;
}

function debbie_public_context(int $excludePostId): string
{
    $stmt = db()->prepare('
        SELECT p.content, p.created_at, u.full_name
        FROM posts p
        INNER JOIN users u ON u.id = p.user_id
        WHERE p.visibility = "public" AND p.id <> :post_id
        ORDER BY p.created_at DESC
        LIMIT 30
    ');
    $stmt->execute([':post_id' => $excludePostId]);
    $rows = $stmt->fetchAll();
    $lines = [];
    foreach ($rows as $row) {
        $lines[] = '- ' . $row['full_name'] . ': ' . mb_substr((string) $row['content'], 0, 500, 'UTF-8');
    }
    return implode("\n", $lines);
}

function debbie_post_context(int $postId): array
{
    $stmt = db()->prepare('
        SELECT p.content AS post_content, p.visibility, u.full_name AS post_author
        FROM posts p
        INNER JOIN users u ON u.id = p.user_id
        WHERE p.id = :post_id
        LIMIT 1
    ');
    $stmt->execute([':post_id' => $postId]);
    $post = $stmt->fetch();
    if (!$post) {
        return [];
    }

    $commentStmt = db()->prepare('
        SELECT c.id, c.parent_comment_id, c.content, c.created_at, u.full_name, u.nickname
        FROM comments c
        INNER JOIN users u ON u.id = c.user_id
        WHERE c.post_id = :post_id
        ORDER BY c.created_at ASC
        LIMIT 100
    ');
    $commentStmt->execute([':post_id' => $postId]);
    $comments = [];
    foreach ($commentStmt->fetchAll() as $comment) {
        $comments[] = [
            'id' => (int) $comment['id'],
            'parent_comment_id' => (int) ($comment['parent_comment_id'] ?? 0),
            'author' => $comment['full_name'] . ' (@' . $comment['nickname'] . ')',
            'content' => mb_substr((string) $comment['content'], 0, 800, 'UTF-8'),
        ];
    }
    return ['post' => $post, 'comments' => $comments];
}

function debbie_generate_reply(array $actor, int $postId, ?int $parentCommentId, string $triggerContent): ?string
{
    $bot = debbie_bot_user();
    $apiKey = defined('GEMINI_API_KEY') ? trim((string) GEMINI_API_KEY) : '';
    if (!$bot || $apiKey === '') {
        return null;
    }

    $context = debbie_post_context($postId);
    if (!$context) {
        return null;
    }

    $post = $context['post'];
    $conversation = json_encode($context['comments'], JSON_UNESCAPED_UNICODE);
    $publicContext = debbie_public_context($postId);
    $actorNickname = (string) ($actor['nickname'] ?? '');
    $systemPrompt = <<<PROMPT
Sen Debi sosyal su tasarrufu platformunun @debbie adlı yapay zeka asistanısın.
Sadece ekoloji, su tasarrufu, iklim, sürdürülebilirlik, atık azaltma, enerji verimliliği ve mahalle dayanışmasıyla doğrudan ilgili sorulara yanıt ver.
Konuyla ilgisiz, kişisel, siyasi, tıbbi, hukuki veya hassas sorulara kısa ve nazikçe "Bu konu Debi'nin ekoloji ve su tasarrufu alanı dışında kalıyor." de.
Yanıtların Türkçe, sıcak, kısa ve somut olsun; kesin olmayan bilgileri kesin gerçek gibi sunma.
Senin görevin etiketlendiğin mesaja odaklanmak. Gerekirse gönderinin ve altındaki konuşmanın tamamını, ayrıca güncel genel public gönderileri bağlam olarak kullan.
Kullanıcıyı utandırma, özel bilgileri tekrar etme, kullanıcılar hakkında çıkarım yapma.
Yanıtın yalnızca JSON olsun: {"reply":"kısa Türkçe yanıt"}.
PROMPT;
    $userPrompt = $systemPrompt . "\n\nGÖNDERİ:\n" . json_encode($post, JSON_UNESCAPED_UNICODE)
        . "\n\nYORUM ZİNCİRİ:\n" . $conversation
        . "\n\nGÜNCEL PUBLIC BAĞLAM:\n" . $publicContext
        . "\n\nETİKETLEYEN KULLANICI: @" . $actorNickname
        . "\nETİKETLENEN MESAJ:\n" . $triggerContent
        . "\nPARENT COMMENT ID: " . (int) ($parentCommentId ?? 0);

    $payload = [
        'model' => defined('DEBBIE_GEMINI_MODEL') ? DEBBIE_GEMINI_MODEL : 'gemini-3.5-flash-lite',
        'input' => [['type' => 'text', 'text' => $userPrompt]],
        'response_format' => ['type' => 'text', 'mime_type' => 'application/json'],
    ];
    $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/interactions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['x-goog-api-key: ' . $apiKey, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 20,
    ]);
    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode >= 400) {
        error_log('Debbie AI request failed with HTTP ' . $httpCode);
        return null;
    }

    $decoded = json_decode((string) $raw, true);
    $text = debbie_extract_interaction_output_text(is_array($decoded) ? $decoded : []);
    $result = json_decode($text, true);
    $reply = trim((string) ($result['reply'] ?? ''));
    if ($reply === '') {
        return null;
    }
    return '@' . $actorNickname . '; ' . $reply;
}

function debbie_extract_interaction_output_text(array $response): string
{
    if (!empty($response['output_text']) && is_string($response['output_text'])) {
        return $response['output_text'];
    }
    foreach (($response['steps'] ?? []) as $step) {
        foreach (($step['content'] ?? []) as $part) {
            if (($step['type'] ?? '') === 'model_output' && ($part['type'] ?? '') === 'text') {
                return (string) ($part['text'] ?? '');
            }
        }
    }
    return '';
}

function debbie_enqueue_reply(array $actor, int $postId, ?int $parentCommentId, string $content): void
{
    if (!debbie_mention_in_text($content)) {
        return;
    }

    $stmt = db()->prepare('
        INSERT INTO debbie_reply_queue
            (actor_user_id, post_id, parent_comment_id, trigger_content)
        VALUES (:actor, :post, :parent, :content)
    ');
    $stmt->execute([
        ':actor' => (int) $actor['id'],
        ':post' => $postId,
        ':parent' => $parentCommentId ?: null,
        ':content' => $content,
    ]);
}

function debbie_process_queue_item(int $queueId): bool
{
    $stmt = db()->prepare('
        SELECT q.*, u.id AS actor_id, u.nickname AS actor_nickname
        FROM debbie_reply_queue q
        INNER JOIN users u ON u.id = q.actor_user_id
        WHERE q.id = :id AND q.status = "processing"
        LIMIT 1
    ');
    $stmt->execute([':id' => $queueId]);
    $job = $stmt->fetch();
    if (!$job) {
        return false;
    }

    try {
        $reply = debbie_generate_reply(
            ['id' => (int) $job['actor_id'], 'nickname' => (string) $job['actor_nickname']],
            (int) $job['post_id'],
            $job['parent_comment_id'] !== null ? (int) $job['parent_comment_id'] : null,
            (string) $job['trigger_content']
        );
        if ($reply !== null) {
            $bot = debbie_bot_user();
            if ($bot) {
                add_post_comment($bot, (int) $job['post_id'], $reply, [], (int) ($job['parent_comment_id'] ?? 0), false);
            }
        }

        $done = db()->prepare('
            UPDATE debbie_reply_queue
            SET status = "completed", completed_at = NOW(), last_error = NULL
            WHERE id = :id
        ');
        $done->execute([':id' => $queueId]);
        return true;
    } catch (Throwable $exception) {
        $failed = db()->prepare('
            UPDATE debbie_reply_queue
            SET status = CASE WHEN attempts >= 3 THEN "failed" ELSE "pending" END,
                available_at = DATE_ADD(NOW(), INTERVAL 1 MINUTE),
                last_error = :error
            WHERE id = :id
        ');
        $failed->execute([
            ':id' => $queueId,
            ':error' => mb_substr($exception->getMessage(), 0, 500, 'UTF-8'),
        ]);
        error_log('Debbie queue job ' . $queueId . ' failed: ' . $exception->getMessage());
        return false;
    }
}

function debbie_process_queue_batch(int $limit = 2): int
{
    $limit = max(1, min(2, $limit));
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $reset = $pdo->prepare('
            UPDATE debbie_reply_queue
            SET status = "pending", locked_at = NULL
            WHERE status = "processing" AND locked_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        ');
        $reset->execute();

        $stmt = $pdo->query("
            SELECT id
            FROM debbie_reply_queue
            WHERE status = 'pending' AND available_at <= NOW()
            ORDER BY id ASC
            LIMIT {$limit}
            FOR UPDATE
        ");
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $claim = $pdo->prepare("
                UPDATE debbie_reply_queue
                SET status = 'processing', attempts = attempts + 1, locked_at = NOW()
                WHERE id IN ($placeholders)
            ");
            $claim->execute($ids);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }

    $processed = 0;
    foreach ($ids ?? [] as $id) {
        if (debbie_process_queue_item($id)) {
            $processed++;
        }
    }
    return $processed;
}

function maybe_answer_as_debbie(array $actor, int $postId, ?int $parentCommentId, string $content): void
{
    try {
        debbie_enqueue_reply($actor, $postId, $parentCommentId, $content);
    } catch (Throwable $exception) {
        error_log('Debbie AI queue failed: ' . $exception->getMessage());
    }
}
