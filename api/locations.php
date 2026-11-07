<?php
/**
 * DEBI - İl, İlçe ve Mahalle Seçimi API Servisi
 * Kaynak: TurkiyeAPI (api.turkiyeapi.dev) + Yerel JSON Önbellekleme
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=86400');

$action = $_GET['action'] ?? 'provinces';

// 81 İl Listesi (Plaka Kodu -> İl Adı)
function get_static_provinces(): array
{
    return [
        ['id' => 1, 'name' => 'Adana'],
        ['id' => 2, 'name' => 'Adıyaman'],
        ['id' => 3, 'name' => 'Afyonkarahisar'],
        ['id' => 4, 'name' => 'Ağrı'],
        ['id' => 68, 'name' => 'Aksaray'],
        ['id' => 5, 'name' => 'Amasya'],
        ['id' => 6, 'name' => 'Ankara'],
        ['id' => 7, 'name' => 'Antalya'],
        ['id' => 75, 'name' => 'Ardahan'],
        ['id' => 8, 'name' => 'Artvin'],
        ['id' => 9, 'name' => 'Aydın'],
        ['id' => 10, 'name' => 'Balıkesir'],
        ['id' => 74, 'name' => 'Bartın'],
        ['id' => 72, 'name' => 'Batman'],
        ['id' => 69, 'name' => 'Bayburt'],
        ['id' => 11, 'name' => 'Bilecik'],
        ['id' => 12, 'name' => 'Bingöl'],
        ['id' => 13, 'name' => 'Bitlis'],
        ['id' => 14, 'name' => 'Bolu'],
        ['id' => 15, 'name' => 'Burdur'],
        ['id' => 16, 'name' => 'Bursa'],
        ['id' => 17, 'name' => 'Çanakkale'],
        ['id' => 18, 'name' => 'Çankırı'],
        ['id' => 19, 'name' => 'Çorum'],
        ['id' => 20, 'name' => 'Denizli'],
        ['id' => 21, 'name' => 'Diyarbakır'],
        ['id' => 81, 'name' => 'Düzce'],
        ['id' => 22, 'name' => 'Edirne'],
        ['id' => 23, 'name' => 'Elazığ'],
        ['id' => 24, 'name' => 'Erzincan'],
        ['id' => 25, 'name' => 'Erzurum'],
        ['id' => 26, 'name' => 'Eskişehir'],
        ['id' => 27, 'name' => 'Gaziantep'],
        ['id' => 28, 'name' => 'Giresun'],
        ['id' => 29, 'name' => 'Gümüşhane'],
        ['id' => 30, 'name' => 'Hakkari'],
        ['id' => 31, 'name' => 'Hatay'],
        ['id' => 76, 'name' => 'Iğdır'],
        ['id' => 32, 'name' => 'Isparta'],
        ['id' => 34, 'name' => 'İstanbul'],
        ['id' => 35, 'name' => 'İzmir'],
        ['id' => 46, 'name' => 'Kahramanmaraş'],
        ['id' => 78, 'name' => 'Karabük'],
        ['id' => 70, 'name' => 'Karaman'],
        ['id' => 36, 'name' => 'Kars'],
        ['id' => 37, 'name' => 'Kastamonu'],
        ['id' => 38, 'name' => 'Kayseri'],
        ['id' => 71, 'name' => 'Kırıkkale'],
        ['id' => 39, 'name' => 'Kırklareli'],
        ['id' => 40, 'name' => 'Kırşehir'],
        ['id' => 79, 'name' => 'Kilis'],
        ['id' => 41, 'name' => 'Kocaeli'],
        ['id' => 42, 'name' => 'Konya'],
        ['id' => 43, 'name' => 'Kütahya'],
        ['id' => 44, 'name' => 'Malatya'],
        ['id' => 45, 'name' => 'Manisa'],
        ['id' => 47, 'name' => 'Mardin'],
        ['id' => 33, 'name' => 'Mersin'],
        ['id' => 48, 'name' => 'Muğla'],
        ['id' => 49, 'name' => 'Muş'],
        ['id' => 50, 'name' => 'Nevşehir'],
        ['id' => 51, 'name' => 'Niğde'],
        ['id' => 52, 'name' => 'Ordu'],
        ['id' => 80, 'name' => 'Osmaniye'],
        ['id' => 53, 'name' => 'Rize'],
        ['id' => 54, 'name' => 'Sakarya'],
        ['id' => 55, 'name' => 'Samsun'],
        ['id' => 56, 'name' => 'Siirt'],
        ['id' => 57, 'name' => 'Sinop'],
        ['id' => 58, 'name' => 'Sivas'],
        ['id' => 63, 'name' => 'Şanlıurfa'],
        ['id' => 73, 'name' => 'Şırnak'],
        ['id' => 59, 'name' => 'Tekirdağ'],
        ['id' => 60, 'name' => 'Tokat'],
        ['id' => 61, 'name' => 'Trabzon'],
        ['id' => 62, 'name' => 'Tunceli'],
        ['id' => 64, 'name' => 'Uşak'],
        ['id' => 65, 'name' => 'Van'],
        ['id' => 77, 'name' => 'Yalova'],
        ['id' => 66, 'name' => 'Yozgat'],
        ['id' => 67, 'name' => 'Zonguldak'],
    ];
}

/**
 * Uzak API'den veri çeker ve yerel önbelleğe kaydeder.
 */
function fetch_with_cache(string $cacheFileName, string $url): ?array
{
    $cacheDir = __DIR__ . '/../cache/locations';
    $cacheFile = $cacheDir . '/' . $cacheFileName;

    // Önbellek geçerli mi (30 gün)
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400 * 30)) {
        $content = @file_get_contents($cacheFile);
        if ($content) {
            $data = json_decode($content, true);
            if (is_array($data)) {
                return $data;
            }
        }
    }

    $raw = null;
    $context = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'header'  => "User-Agent: Debi-App/1.0\r\nAccept: application/json\r\n",
            'timeout' => 6,
        ],
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
        ]
    ]);

    $raw = @file_get_contents($url, false, $context);

    if (!$raw && function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Debi-App/1.0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $raw = curl_exec($ch);
        curl_close($ch);
    }

    if ($raw) {
        $decoded = json_decode($raw, true);
        if (!empty($decoded['data']) && is_array($decoded['data'])) {
            if (!is_dir($cacheDir)) {
                @mkdir($cacheDir, 0755, true);
            }
            @file_put_contents($cacheFile, json_encode($decoded['data'], JSON_UNESCAPED_UNICODE));
            return $decoded['data'];
        }
    }

    return null;
}

try {
    if ($action === 'provinces') {
        // İlleri Türkçe alfabetik sıralı döndür
        $provinces = get_static_provinces();
        usort($provinces, function ($a, $b) {
            if (class_exists('Collator')) {
                $coll = new Collator('tr_TR');
                return $coll->compare($a['name'], $b['name']);
            }
            $trMap = ['ç'=>'c1', 'Ç'=>'c1', 'ğ'=>'g1', 'Ğ'=>'g1', 'ı'=>'i0', 'I'=>'i0', 'İ'=>'i1', 'i'=>'i1', 'ö'=>'o1', 'Ö'=>'o1', 'ş'=>'s1', 'Ş'=>'s1', 'ü'=>'u1', 'Ü'=>'u1'];
            $nameA = strtr(mb_strtolower($a['name'], 'UTF-8'), $trMap);
            $nameB = strtr(mb_strtolower($b['name'], 'UTF-8'), $trMap);
            return strcmp($nameA, $nameB);
        });

        echo json_encode(['ok' => true, 'data' => $provinces], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'districts') {
        $provinceId = (int) ($_GET['province_id'] ?? 0);
        if ($provinceId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Geçerli bir il ID belirtilmedi.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $url = "https://api.turkiyeapi.dev/v2/provinces/{$provinceId}/districts?fields=id,name&sort=name";
        $data = fetch_with_cache("districts_{$provinceId}.json", $url);

        if ($data === null) {
            echo json_encode(['ok' => false, 'error' => 'İlçeler yüklenemedi. Lütfen internet bağlantınızı kontrol edin.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'neighborhoods') {
        $districtId = (int) ($_GET['district_id'] ?? 0);
        if ($districtId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Geçerli bir ilçe ID belirtilmedi.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $url = "https://api.turkiyeapi.dev/v2/districts/{$districtId}/neighborhoods?fields=id,name&sort=name";
        $data = fetch_with_cache("neighborhoods_{$districtId}.json", $url);

        if ($data === null) {
            echo json_encode(['ok' => false, 'error' => 'Mahalleler yüklenemedi. Lütfen internet bağlantınızı kontrol edin.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Bilinmeyen işlem.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
