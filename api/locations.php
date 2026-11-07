<?php
/**
 * DEBI - İl, İlçe ve Mahalle Seçimi API Servisi
 * Kaynak: TurkiyeAPI v2 + Yerel JSON Önbellekleme
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

$action = $_GET['action'] ?? 'provinces';

// 81 İl Listesi (Alfabetik Sıralı, Plaka Kodu -> İl Adı)
function get_static_provinces(): array
{
    return [
        ['id' => 1,  'name' => 'Adana'],
        ['id' => 2,  'name' => 'Adıyaman'],
        ['id' => 3,  'name' => 'Afyonkarahisar'],
        ['id' => 4,  'name' => 'Ağrı'],
        ['id' => 68, 'name' => 'Aksaray'],
        ['id' => 5,  'name' => 'Amasya'],
        ['id' => 6,  'name' => 'Ankara'],
        ['id' => 7,  'name' => 'Antalya'],
        ['id' => 75, 'name' => 'Ardahan'],
        ['id' => 8,  'name' => 'Artvin'],
        ['id' => 9,  'name' => 'Aydın'],
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
 * Türkçe alfabetik sıralama yardımcısı
 */
function sort_by_turkish_name(array &$items): void
{
    $trMap = [
        'ç' => 'c~', 'Ç' => 'c~',
        'ğ' => 'g~', 'Ğ' => 'g~',
        'ı' => 'i',  'I' => 'i',
        'İ' => 'i~', 'i' => 'i~',
        'ö' => 'o~', 'Ö' => 'o~',
        'ş' => 's~', 'Ş' => 's~',
        'ü' => 'u~', 'Ü' => 'u~',
    ];
    usort($items, function ($a, $b) use ($trMap) {
        $na = strtr(mb_strtolower($a['name'] ?? '', 'UTF-8'), $trMap);
        $nb = strtr(mb_strtolower($b['name'] ?? '', 'UTF-8'), $trMap);
        return strcmp($na, $nb);
    });
}

/**
 * Verilen URL'den JSON verisi çeker
 */
function http_get_json(string $url): ?array
{
    $raw = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Debi-App/1.0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $raw  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 400) {
            $raw = null;
        }
    }

    if (!$raw) {
        $context = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'header'  => "User-Agent: Debi-App/1.0\r\nAccept: application/json\r\n",
                'timeout' => 8,
            ],
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
        ]);
        $raw = @file_get_contents($url, false, $context);
    }

    if ($raw) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return null;
}

/**
 * Önbellekten oku veya uzak API'den çekip kaydet
 */
function get_cached_or_remote(string $cacheFileName, callable $fetcher): ?array
{
    $cacheDir  = __DIR__ . '/../cache/locations';
    $cacheFile = $cacheDir . '/' . $cacheFileName;

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400 * 30)) {
        $content = @file_get_contents($cacheFile);
        if ($content) {
            $data = json_decode($content, true);
            if (is_array($data) && count($data) > 0) {
                return $data;
            }
        }
    }

    $list = $fetcher();
    if (is_array($list) && count($list) > 0) {
        sort_by_turkish_name($list);
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        @file_put_contents($cacheFile, json_encode($list, JSON_UNESCAPED_UNICODE));
        return $list;
    }

    return null;
}

try {
    if ($action === 'provinces') {
        echo json_encode(['ok' => true, 'data' => get_static_provinces()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'districts') {
        $provinceId = (int) ($_GET['province_id'] ?? 0);
        if ($provinceId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Geçerli bir il seçilmedi.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $data = get_cached_or_remote("districts_v2_{$provinceId}.json", function () use ($provinceId) {
            $res = http_get_json("https://api.turkiyeapi.dev/v2/provinces/{$provinceId}/districts");
            if (!empty($res['data']) && is_array($res['data'])) {
                return array_map(fn($d) => ['id' => (int) $d['id'], 'name' => (string) $d['name']], $res['data']);
            }
            return null;
        });

        if (!$data) {
            echo json_encode(['ok' => false, 'error' => 'İlçeler yüklenemedi.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'neighborhoods') {
        $districtId = (int) ($_GET['district_id'] ?? 0);
        if ($districtId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Geçerli bir ilçe seçilmedi.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $data = get_cached_or_remote("neighborhoods_v2_{$districtId}.json", function () use ($districtId) {
            $res = http_get_json("https://api.turkiyeapi.dev/v2/neighborhoods?districtId={$districtId}&limit=100");
            if (!empty($res['data']) && is_array($res['data'])) {
                return array_map(fn($n) => ['id' => (int) $n['id'], 'name' => (string) $n['name']], $res['data']);
            }
            return null;
        });

        if (!$data) {
            echo json_encode(['ok' => false, 'error' => 'Mahalleler yüklenemedi.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Geçersiz istek.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
