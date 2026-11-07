<?php
require_once __DIR__ . '/../water/bill_ai_service.php';

function get_event_criteria_context(string $title, string $description): array
{
    $path = __DIR__ . '/../../criteria.json';
    $criteria = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
    if (!is_array($criteria)) {
        return ['prompt' => '', 'sources' => 'criteria.json okunamadı'];
    }

    $text = mb_strtolower($title . ' ' . $description, 'UTF-8');
    $references = [];
    foreach (($criteria['referans_kaynaklar'] ?? []) as $reference) {
        if (!empty($reference['kaynak_kodu'])) {
            $references[$reference['kaynak_kodu']] = $reference;
        }
    }
    $matchedActivities = [];
    $allActivities = [];
    foreach (($criteria['etkinlik_kategorileri'] ?? []) as $category) {
        foreach (($category['aktiviteler'] ?? []) as $activity) {
            $allActivities[] = $activity;
            $keywords = array_map(static fn ($keyword) => mb_strtolower((string) $keyword, 'UTF-8'), $activity['ne_yapilacak_anahtar_kelimeler'] ?? []);
            $matched = false;
            foreach ($keywords as $keyword) {
                if ($keyword !== '' && mb_strpos($text, $keyword, 0, 'UTF-8') !== false) {
                    $matched = true;
                    break;
                }
            }
            if ($matched) {
                $matchedActivities[] = $activity;
            }
        }
    }
    $activities = $matchedActivities ?: $allActivities;

    $sourceCodes = [];
    $activityLines = [];
    foreach ($activities as $activity) {
        foreach (($activity['kaynak_kodlari'] ?? []) as $code) {
            $sourceCodes[$code] = true;
        }
        $activityLines[] = json_encode([
            'aktivite' => $activity['etkinlik_basligi_ornek'] ?? '',
            'birim' => $activity['fiziksel_birim'] ?? '',
            'puan_katsayisi' => $activity['ai_birim_puan_katsayisi'] ?? null,
            'metrikler' => $activity['su_kazanimi_metrikleri'] ?? [],
            'standartlar' => $activity['fiziksel_uygulama_standartlari'] ?? [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $sourceLines = [];
    foreach (array_keys($sourceCodes) as $code) {
        $reference = $references[$code] ?? [];
        $sourceLines[] = $code . ': ' . ($reference['kurum_ve_yayin'] ?? '') . ' — ' . ($reference['metrik_kapsami'] ?? '');
    }

    return [
        'prompt' => implode("\n", $activityLines),
        'sources' => implode("\n", $sourceLines),
    ];
}

function score_event_with_ai(string $title, string $description, string $date, string $startTime): array
{
    $criteria = get_event_criteria_context($title, $description);
    $fallbackPoints = (int) min(100, max(5, round(14 + (mb_strlen($description, 'UTF-8') >= 120 ? 8 : 0))));
    $fallbackReason = 'Etkinlik süresi, fiziksel katılım gerektirmesi ve açıklamadaki topluluk katkısı dikkate alınarak geçici puan belirlendi.';
    $apiKey = defined('GEMINI_API_KEY') ? trim((string) GEMINI_API_KEY) : '';
    if ($apiKey === '') {
        return ['ok' => true, 'points' => $fallbackPoints, 'reason' => $fallbackReason, 'criteria_sources' => $criteria['sources'], 'ai_used' => false];
    }

    $prompt = <<<PROMPT
Sen Debi su tasarrufu ve mahalle dayanışması platformunda etkinlik puanı belirleyen asistansın.
Etkinlik fiziksel bir iş ve katılımcılar yalnızca yoklama ve etkinlik fotoğrafı doğrulamasından sonra puan alır.
Başlık: {$title}
Açıklama: {$description}
Tarih: {$date}
Başlangıç: {$startTime}
Etkinlik, oluşturucu tarafından fiziksel çalışma tamamlandığında bitirilecek.
criteria.json içindeki aşağıdaki aktivite, metrik ve katsayıları puanlama için temel al:
{$criteria['prompt']}
Kriterleri birebir uygulamak zorunda değilsin; etkinliğin kapsamına göre makul çıkarımlar yapabilirsin. 5 ile 100 arasında adil bir tam sayı puan belirle ve gerekçende hangi fiziksel katkıyı esas aldığını kısaca belirt.
Yanıtı yalnızca JSON ver: {"points": number, "reason": "Türkçe, en fazla iki cümlelik gerekçe", "criteria_sources": "Kısa kaynak kodu ve kurum/yayın özeti"}.
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
                    'criteria_sources' => ['type' => 'string'],
                ],
                'required' => ['points', 'reason', 'criteria_sources'],
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
        return ['ok' => true, 'points' => $fallbackPoints, 'reason' => $fallbackReason, 'criteria_sources' => $criteria['sources'], 'ai_used' => false];
    }
    return ['ok' => true, 'points' => $points, 'reason' => $reason, 'criteria_sources' => trim((string) ($result['criteria_sources'] ?? $criteria['sources'])), 'ai_used' => true];
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
