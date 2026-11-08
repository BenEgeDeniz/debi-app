<?php
/**
 * DEBI - Gemini 3.8 Flash (Interactions API) Su Faturası Okuma Servisi
 * Kullanıcının yüklediği su faturası fotoğrafını Gemini Interactions API ile analiz eder,
 * metreküp (m³), fatura tutarı (TL), dönem (ay/yıl), gün sayısı ve su idaresi bilgisini çıkarır.
 */

/**
 * Yüklenen fatura görselini kaydeder ve Gemini Interactions API (gemini-3.8-flash) ile analiz eder.
 */
function analyze_water_bill_image(array $file): array
{
    require_once __DIR__ . '/invoice_storage.php';
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

    try {
        $encryptedReference = encrypt_invoice_file($file['tmp_name'], $allowedMimes[$mime]);
        $secureName = basename((string) parse_url($encryptedReference, PHP_URL_PATH));
        $relPath = 'invoice.php?file=' . rawurlencode($secureName) . '&type=' . rawurlencode($allowedMimes[$mime]);
        $_SESSION['pending_invoice_reference'] = $relPath;
        $fullPath = tempnam(sys_get_temp_dir(), 'debi_bill_');
        if ($fullPath === false) {
            throw new RuntimeException('Geçici fatura dosyası oluşturulamadı.');
        }
        file_put_contents($fullPath, (string) file_get_contents($file['tmp_name']));
        register_shutdown_function(static function () use ($fullPath): void {
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
        });
    } catch (Throwable $exception) {
        return ['ok' => false, 'error' => 'Fatura görseli güvenli biçimde saklanamadı.'];
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
- is_water_bill: Görsel resmi bir su faturası mı? El yazısı not, boş form, ekran görüntüsü, makbuz veya sayaç fotoğrafıysa false.
- is_handwritten: Faturadaki ana bilgiler el yazısıyla mı yazılmış? El yazısıysa true.
- is_original_document: Görsel resmi faturanın kendisi mi; düzenlenmiş, üzerine yazılmış veya manipüle edilmiş görünüyor mu? Değilse false.
- provider_name: Su idaresi veya kurum adı (Örn: "İSKİ", "ASKİ", "İZSU". Okunamıyorsa boş bırak)
- period_month: Fatura dönemi ayı (1 ile 12 arasında tam sayı. Son okuma tarihindeki ayı baz al)
- period_year: Fatura dönemi yılı (Örn: 2025 veya 2026)
- consumption_m3: Tüketilen su miktarı metreküp (m3) cinsinden sayısal değer (Örn: 11.5 veya 9)
- bill_amount: Ödenecek toplam fatura tutarı TL cinsinden sayısal değer (Örn: 345.50)
- reading_days: İlk okuma ile son okuma arasındaki gün sayısı / fatura gün sayısı (Yazmıyorsa 30)
- summary_text: Kullanıcıya gösterilecek tek cümlelik Türkçe özet (Örn: "İSKİ faturanızdan 29 günlük dönemde 11 m³ su tüketimi ve 348,00 TL fatura tutarı okundu.")
PROMPT;

    $schema = [
        'type'       => 'object',
        'properties' => [
            'is_water_bill'  => ['type' => 'boolean'],
            'is_handwritten' => ['type' => 'boolean'],
            'is_original_document' => ['type' => 'boolean'],
            'provider_name'  => ['type' => 'string'],
            'period_month'   => ['type' => 'integer'],
            'period_year'    => ['type' => 'integer'],
            'consumption_m3' => ['type' => 'number'],
            'bill_amount'    => ['type' => 'number'],
            'reading_days'   => ['type' => 'integer'],
            'summary_text'   => ['type' => 'string'],
        ],
        'required' => [
            'is_water_bill',
            'is_handwritten',
            'is_original_document',
            'provider_name',
            'period_month',
            'period_year',
            'consumption_m3',
            'bill_amount',
            'reading_days',
            'summary_text',
        ],
    ];

    // Güncel Gemini Interactions API (models/gemini-3.8-flash)
    $payload = [
        'model' => 'gemini-3.8-flash',
        'input' => [
            [
                'type' => 'text',
                'text' => $prompt,
            ],
            [
                'type'      => 'image',
                'data'      => $imageBase64,
                'mime_type' => $mime,
            ],
        ],
        'response_format' => [
            'type'      => 'text',
            'mime_type' => 'application/json',
            'schema'    => $schema,
        ],
    ];

    $url = 'https://generativelanguage.googleapis.com/v1beta/interactions';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'x-goog-api-key: ' . $apiKey,
            'Content-Type: application/json',
            'Api-Revision: 2026-05-20',
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => 30,
    ]);

    $rawResponse = curl_exec($ch);
    $httpCode    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr     = curl_error($ch);
    curl_close($ch);

    if ($rawResponse === false || $httpCode >= 400) {
        $errDetail  = $curlErr ?: 'HTTP ' . $httpCode;
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

    $respJson = json_decode((string) $rawResponse, true);
    $textPart = extract_interaction_output_text(is_array($respJson) ? $respJson : []);

    $extracted = json_decode($textPart, true);

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

    $isWaterBill = !empty($extracted['is_water_bill']);
    $isHandwritten = !empty($extracted['is_handwritten']);
    $isOriginalDocument = array_key_exists('is_original_document', $extracted)
        ? !empty($extracted['is_original_document'])
        : true;
    if (!$isWaterBill || $isHandwritten || !$isOriginalDocument) {
        @unlink($fullPath);
        $reason = $isHandwritten
            ? 'El yazısı faturalar kabul edilmiyor.'
            : (!$isOriginalDocument ? 'Düzenlenmiş veya üzerine yazılmış görseller kabul edilmiyor.' : 'Yüklenen görsel resmi bir su faturası olarak doğrulanamadı.');
        return [
            'ok' => false,
            'error' => 'Fatura reddedildi: ' . $reason . ' Orijinal faturayı olduğu gibi ve net şekilde yükleyin.',
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
        'is_water_bill'   => $isWaterBill,
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

/**
 * Gemini Interactions API yanıtından (steps veya outputs) metin çıktısını ayıklar.
 */
function extract_interaction_output_text(array $respJson): string
{
    if (!empty($respJson['output_text']) && is_string($respJson['output_text'])) {
        return $respJson['output_text'];
    }

    if (!empty($respJson['steps']) && is_array($respJson['steps'])) {
        $texts = [];
        foreach ($respJson['steps'] as $step) {
            if (($step['type'] ?? '') === 'model_output' && !empty($step['content']) && is_array($step['content'])) {
                foreach ($step['content'] as $part) {
                    if (($part['type'] ?? '') === 'text' && isset($part['text'])) {
                        $texts[] = (string) $part['text'];
                    }
                }
            }
        }
        if (!empty($texts)) {
            return implode('', $texts);
        }
    }

    if (!empty($respJson['outputs']) && is_array($respJson['outputs'])) {
        $last = end($respJson['outputs']);
        if (is_array($last) && isset($last['text'])) {
            return (string) $last['text'];
        }
    }

    if (!empty($respJson['candidates'][0]['content']['parts'][0]['text'])) {
        return (string) $respJson['candidates'][0]['content']['parts'][0]['text'];
    }

    return '';
}
