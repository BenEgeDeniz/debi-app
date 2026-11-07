<?php
require_once __DIR__ . '/../water/bill_ai_service.php';

function score_event_with_ai(string $title, string $description, string $date, string $startTime): array
{
    $fallbackPoints = (int) min(100, max(5, round(14 + (mb_strlen($description, 'UTF-8') >= 120 ? 8 : 0))));
    $fallbackReason = 'Etkinlik süresi, fiziksel katılım gerektirmesi ve açıklamadaki topluluk katkısı dikkate alınarak geçici puan belirlendi.';
    $apiKey = defined('GEMINI_API_KEY') ? trim((string) GEMINI_API_KEY) : '';
    if ($apiKey === '') {
        return ['ok' => true, 'points' => $fallbackPoints, 'reason' => $fallbackReason, 'ai_used' => false];
    }

    $prompt = <<<PROMPT
Sen Debi su tasarrufu ve mahalle dayanışması platformunda etkinlik puanı belirleyen asistansın.
Etkinlik fiziksel bir iş ve katılımcılar yalnızca yoklama ve etkinlik fotoğrafı doğrulamasından sonra puan alır.
Başlık: {$title}
Açıklama: {$description}
Tarih: {$date}
Başlangıç: {$startTime}
Etkinlik, oluşturucu tarafından fiziksel çalışma tamamlandığında bitirilecek.
5 ile 100 arasında adil bir tam sayı puan belirle. Süre, fiziksel emek, topluluk etkisi ve su/çevre katkısını sezgisel olarak değerlendir.
Yanıtı yalnızca JSON ver: {"points": number, "reason": "Türkçe, en fazla iki cümlelik gerekçe"}.
PROMPT;

    $payload = [
        'model' => 'gemini-3.8-flash',
        'input' => [['type' => 'text', 'text' => $prompt]],
        'response_format' => [
            'type' => 'text',
            'mime_type' => 'application/json',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'points' => ['type' => 'integer'],
                    'reason' => ['type' => 'string'],
                ],
                'required' => ['points', 'reason'],
            ],
        ],
    ];
    $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/interactions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['x-goog-api-key: ' . $apiKey, 'Content-Type: application/json', 'Api-Revision: 2026-05-20'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 30,
    ]);
    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $decoded = json_decode((string) $raw, true);
    $text = extract_interaction_output_text(is_array($decoded) ? $decoded : []);
    $result = json_decode($text, true);
    $points = (int) ($result['points'] ?? 0);
    $reason = trim((string) ($result['reason'] ?? ''));
    if ($httpCode >= 400 || $points < 5 || $points > 100 || $reason === '') {
        return ['ok' => true, 'points' => $fallbackPoints, 'reason' => $fallbackReason, 'ai_used' => false];
    }
    return ['ok' => true, 'points' => $points, 'reason' => $reason, 'ai_used' => true];
}

function verify_event_photo_with_ai(string $fullPath, string $title, string $description): array
{
    $apiKey = defined('GEMINI_API_KEY') ? trim((string) GEMINI_API_KEY) : '';
    if ($apiKey === '') {
        return ['ok' => true, 'approved' => true, 'reason' => 'AI anahtarı tanımlı olmadığı için fotoğraf geçici olarak onaylandı.', 'ai_used' => false];
    }
    $mime = mime_content_type($fullPath) ?: 'image/jpeg';
    $prompt = "Bu fotoğrafı etkinlik kanıtı olarak değerlendir. Etkinlik başlığı: {$title}. Açıklama: {$description}. Fotoğraf etkinlikle makul biçimde ilgiliyse approved true, değilse false ver. Yalnızca JSON: {\"approved\": true, \"reason\": \"Türkçe kısa gerekçe\"}.";
    $payload = [
        'model' => 'gemini-3.8-flash',
        'input' => [
            ['type' => 'text', 'text' => $prompt],
            ['type' => 'image', 'data' => base64_encode((string) file_get_contents($fullPath)), 'mime_type' => $mime],
        ],
        'response_format' => ['type' => 'text', 'mime_type' => 'application/json'],
    ];
    $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/interactions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['x-goog-api-key: ' . $apiKey, 'Content-Type: application/json', 'Api-Revision: 2026-05-20'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE), CURLOPT_TIMEOUT => 30,
    ]);
    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $decoded = json_decode((string) $raw, true);
    $result = json_decode(extract_interaction_output_text(is_array($decoded) ? $decoded : []), true);
    if ($httpCode >= 400 || !is_array($result) || !isset($result['approved'])) {
        return ['ok' => false, 'error' => 'Fotoğraf AI tarafından doğrulanamadı. Lütfen daha sonra tekrar deneyin.'];
    }
    return ['ok' => true, 'approved' => (bool) $result['approved'], 'reason' => trim((string) ($result['reason'] ?? '')), 'ai_used' => true];
}
