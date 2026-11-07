<?php
/**
 * DEBI - Gemini AI Su Faturası Okuma Servisi
 * Kullanıcının yüklediği su faturası fotoğrafını Gemini API ile analiz eder,
 * metreküp (m³), fatura tutarı (TL), dönem (ay/yıl), gün sayısı ve su idaresi bilgisini çıkarır.
 */

/**
 * Yüklenen fatura görselini kaydeder ve Gemini API ile analiz eder.
 */
function analyze_water_bill_image(array $file): array
{
    if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Lütfen net okunabilen bir su faturası fotoğrafı seçin.'];
    }

    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowedMimes[$mime])) {
        return ['ok' => false, 'error' => 'Lütfen JPG, PNG veya WebP formatında bir fatura görseli yükleyin.'];
    }

    if (($file['size'] ?? 0) > 8 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'Fatura fotoğrafı en fazla 8 MB olabilir.'];
    }

    $uploadDir = __DIR__ . '/../../uploads/bills';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileName = 'bill_' . date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . $allowedMimes[$mime];
    $fullPath = $uploadDir . '/' . $fileName;
    $relPath  = 'uploads/bills/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        return ['ok' => false, 'error' => 'Fatura görseli sunucuya kaydedilemedi.'];
    }

    $apiKey = defined('GEMINI_API_KEY') ? trim((string) GEMINI_API_KEY) : '';

    // Eğer Gemini API anahtarı henüz girilmemişse görseli koruyarak onay ekranına düşür
    if ($apiKey === '') {
        return [
            'ok'              => true,
            'ai_used'         => false,
            'api_key_missing' => true,
            'bill_image_path' => $relPath,
            'extracted'       => [
                'provider_name'  => '',
                'period_month'   => (int) date('n'),
                'period_year'    => (int) date('Y'),
                'consumption_m3' => '',
                'bill_amount'    => '',
                'reading_days'   => 30,
                'summary_text'   => 'config/database.local.php dosyasına GEMINI_API_KEY eklendiğinde bu alanlar faturadan otomatik doldurulur. Şimdilik değerleri aşağıdan doğrulayabilirsiniz.',
            ],
        ];
    }

    $imageBase64 = base64_encode((string) file_get_contents($fullPath));

    $prompt = <<<PROMPT
Sen Türkiye'deki su faturalarını (İSKİ, ASKİ, İZSU, BUSKİ, MESKİ, ASAT, KASKİ, SASKİ vb.) okuyan uzman bir asistansın.
Bu görseldeki su faturasını incele ve aşağıdaki alanları JSON olarak çıkar:
- is_water_bill: Görsel bir su faturası veya sayaç/tüketim belgesi mi? (true/false)
- provider_name: Su idaresi veya kurum adı (Örn: "İSKİ", "ASKİ", "İZSU". Okunamıyorsa boş bırak)
- period_month: Fatura dönemi ayı (1 ile 12 arasında tam sayı. Son okuma tarihindeki ayı baz al)
- period_year: Fatura dönemi yılı (Örn: 2025 veya 2026)
- consumption_m3: Tüketilen su miktarı metreküp (m3) cinsinden sayısal değer (Örn: 11.5 veya 9)
- bill_amount: Ödenecek toplam fatura tutarı TL cinsinden sayısal değer (Örn: 345.50)
- reading_days: İlk okuma ile son okuma arasındaki gün sayısı / fatura gün sayısı (Yazmıyorsa 30)
- summary_text: Kullanıcıya gösterilecek tek cümlelik Türkçe özet (Örn: "İSKİ faturanızdan 29 günlük dönemde 11 m³ su tüketimi ve 348,00 TL fatura tutarı okundu.")
PROMPT;

    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt],
                    [
                        'inline_data' => [
                            'mime_type' => $mime,
                            'data'      => $imageBase64,
                        ],
                    ],
                ],
            ],
        ],
        'generationConfig' => [
            'temperature'      => 0.1,
            'responseMimeType' => 'application/json',
            'responseSchema'   => [
                'type'       => 'OBJECT',
                'properties' => [
                    'is_water_bill'  => ['type' => 'BOOLEAN'],
                    'provider_name'  => ['type' => 'STRING'],
                    'period_month'   => ['type' => 'INTEGER'],
                    'period_year'    => ['type' => 'INTEGER'],
                    'consumption_m3' => ['type' => 'NUMBER'],
                    'bill_amount'    => ['type' => 'NUMBER'],
                    'reading_days'   => ['type' => 'INTEGER'],
                    'summary_text'   => ['type' => 'STRING'],
                ],
                'required' => [
                    'is_water_bill',
                    'provider_name',
                    'period_month',
                    'period_year',
                    'consumption_m3',
                    'bill_amount',
                    'reading_days',
                    'summary_text',
                ],
            ],
        ],
    ];

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . urlencode($apiKey);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => 25,
    ]);

    $rawResponse = curl_exec($ch);
    $httpCode    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr     = curl_error($ch);
    curl_close($ch);

    if ($rawResponse === false || $httpCode >= 400) {
        $errDetail = $curlErr ?: 'HTTP ' . $httpCode;
        $decodedErr = json_decode((string) $rawResponse, true);
        if (!empty($decodedErr['error']['message'])) {
            $errDetail = $decodedErr['error']['message'];
        }
        return [
            'ok'              => true,
            'ai_used'         => false,
            'ai_error'        => 'Yapay zeka servisine bağlanırken bir sorun oluştu (' . $errDetail . '). Bilgileri aşağıdan kontrol ederek kaydedebilirsiniz.',
            'bill_image_path' => $relPath,
            'extracted'       => [
                'provider_name'  => '',
                'period_month'   => (int) date('n'),
                'period_year'    => (int) date('Y'),
                'consumption_m3' => '',
                'bill_amount'    => '',
                'reading_days'   => 30,
                'summary_text'   => 'Fatura görseli yüklendi. Lütfen değerleri kontrol edin.',
            ],
        ];
    }

    $respJson  = json_decode((string) $rawResponse, true);
    $textPart  = $respJson['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $extracted = json_decode((string) $textPart, true);

    if (!is_array($extracted)) {
        return [
            'ok'              => true,
            'ai_used'         => false,
            'ai_error'        => 'Fatura verisi tam ayrıştırılamadı. Lütfen aşağıdaki alanları kontrol edin.',
            'bill_image_path' => $relPath,
            'extracted'       => [
                'provider_name'  => '',
                'period_month'   => (int) date('n'),
                'period_year'    => (int) date('Y'),
                'consumption_m3' => '',
                'bill_amount'    => '',
                'reading_days'   => 30,
                'summary_text'   => 'Fatura görseli yüklendi.',
            ],
        ];
    }

    $month = (int) ($extracted['period_month'] ?? date('n'));
    if ($month < 1 || $month > 12) {
        $month = (int) date('n');
    }

    $year = (int) ($extracted['period_year'] ?? date('Y'));
    if ($year < 2020 || $year > 2035) {
        $year = (int) date('Y');
    }

    $days = (int) ($extracted['reading_days'] ?? 30);
    if ($days < 1 || $days > 90) {
        $days = 30;
    }

    $m3   = isset($extracted['consumption_m3']) && (float) $extracted['consumption_m3'] > 0
        ? round((float) $extracted['consumption_m3'], 2)
        : '';
    $bill = isset($extracted['bill_amount']) && (float) $extracted['bill_amount'] > 0
        ? round((float) $extracted['bill_amount'], 2)
        : '';

    return [
        'ok'              => true,
        'ai_used'         => true,
        'is_water_bill'   => (bool) ($extracted['is_water_bill'] ?? true),
        'bill_image_path' => $relPath,
        'extracted'       => [
            'provider_name'  => trim((string) ($extracted['provider_name'] ?? '')),
            'period_month'   => $month,
            'period_year'    => $year,
            'consumption_m3' => $m3,
            'bill_amount'    => $bill,
            'reading_days'   => $days,
            'summary_text'   => trim((string) ($extracted['summary_text'] ?? 'Faturanız yapay zeka tarafından okundu.')),
        ],
    ];
}
