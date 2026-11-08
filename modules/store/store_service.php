<?php
/**
 * DEBI - Mağaza (Yeşil Markalarla Harcama Yapma & Anlaşmalı Kampanyaya Destek Olma) Modülü
 */

if (!function_exists('get_household_pond_state')) {
    require_once __DIR__ . '/../water/water_service.php';
}

/**
 * Mağaza ve Kampanya tablolarının güncel şema ve içerikle kurulu olduğundan emin olur.
 */
function ensure_store_schema(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $pdo = db();

        $sqlPath = __DIR__ . '/../../sql-dum/101_store_and_campaigns.sql';
        $statements = [];
        if (is_file($sqlPath)) {
            $sql = (string) file_get_contents($sqlPath);
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($statements as $stmtSql) {
                $clean = trim((string) preg_replace('/^\s*--.*$/m', '', $stmtSql));
                if ($clean !== '' && stripos($clean, 'CREATE TABLE') === 0) {
                    try {
                        $pdo->exec($clean);
                    } catch (Throwable $inner) {
                        // Tekil ifade hatasını geç
                    }
                }
            }
        }

        // Eski şemadan kalma zorunlu sütunları temizle / varsayılan değer ata ve yeni sütunları ekle
        $alterCommands = [
            "ALTER TABLE store_items MODIFY COLUMN category VARCHAR(50) NOT NULL DEFAULT 'cafe'",
            "ALTER TABLE store_items MODIFY COLUMN title VARCHAR(180) NOT NULL DEFAULT ''",
            "ALTER TABLE store_items DROP COLUMN badge_label",
            "ALTER TABLE store_items ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER description",
            "ALTER TABLE store_redemptions ADD COLUMN is_used TINYINT(1) NOT NULL DEFAULT 0 AFTER coupon_code",
            "ALTER TABLE store_redemptions ADD COLUMN used_at DATETIME DEFAULT NULL AFTER is_used",
            "ALTER TABLE support_campaigns DROP COLUMN contrib_unit",
            "ALTER TABLE support_campaigns DROP COLUMN contrib_amount",
            "ALTER TABLE support_campaigns DROP COLUMN cost_points",
            "ALTER TABLE support_campaigns DROP COLUMN target_contrib_amount",
            "ALTER TABLE support_campaigns DROP COLUMN current_contrib_amount",
            "ALTER TABLE support_campaigns ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER description",
            "ALTER TABLE support_campaigns ADD COLUMN min_points INT UNSIGNED NOT NULL DEFAULT 50",
            "ALTER TABLE support_campaigns ADD COLUMN target_dp INT UNSIGNED NOT NULL DEFAULT 5000",
            "ALTER TABLE support_campaigns ADD COLUMN current_dp INT UNSIGNED NOT NULL DEFAULT 1250",
            "ALTER TABLE campaign_supports DROP COLUMN contrib_unit",
            "ALTER TABLE campaign_supports DROP COLUMN contrib_amount",
        ];
        foreach ($alterCommands as $altSql) {
            try {
                $pdo->exec($altSql);
            } catch (Throwable $e) {
            }
        }

        // Şema güncellendikten sonra INSERT / ON DUPLICATE KEY ifadelerini çalıştır
        foreach ($statements as $stmtSql) {
            $clean = trim((string) preg_replace('/^\s*--.*$/m', '', $stmtSql));
            if ($clean !== '' && stripos($clean, 'CREATE TABLE') !== 0) {
                try {
                    $pdo->exec($clean);
                } catch (Throwable $inner) {
                    // Tekil ifade hatasını geç
                }
            }
        }

        // İndirim oranı = Harcanacak DP kuralını ve görsel yollarını senkronize et
        try {
            $pdo->exec("DELETE FROM store_items WHERE slug IN ('biodamla-eko-deterjan', 'cam-fincan-ekokafe', 'yesil-damla-roastery', 'aquapure-konsantre-deterjan', 'ekoakis-perlator-magaza')");
            $pdo->exec("UPDATE store_items SET cost_points = discount_pct");
            $pdo->exec("UPDATE store_items SET image_path = 'assets/store/damla-kafe.jpg' WHERE slug = 'damla-kafe'");
            $pdo->exec("UPDATE store_items SET image_path = 'assets/store/yesil-sofra-restoran.jpg' WHERE slug = 'yesil-sofra-restoran'");
            $pdo->exec("UPDATE store_items SET image_path = 'assets/store/aquapure-deterjan.jpg' WHERE slug = 'aquapure-deterjan'");
            $pdo->exec("UPDATE store_items SET image_path = 'assets/store/ekoakis-perlator.jpg' WHERE slug = 'ekoakis-perlator'");
            $pdo->exec("DELETE FROM support_campaigns WHERE slug NOT IN ('okul-cesmeleri-tasarruf-donusumu', 'kuruyan-gollere-can-suyu')");
            $pdo->exec("UPDATE support_campaigns SET image_path = 'assets/store/campaign-school.jpg', title = 'Mahallemizdeki Okullarda Tasarruflu Musluk & Gri Su Dönüşüm Kampanyası', partner_name = 'Yeşil Okullar & İlçe Milli Eğitim Su İşbirliği' WHERE slug = 'okul-cesmeleri-tasarruf-donusumu'");
            $pdo->exec("UPDATE support_campaigns SET image_path = 'assets/store/campaign-lake.jpg', title = 'Burdur Gölü ve Havzasına Can Suyu Kampanyası', partner_name = 'Debi & Burdur Gölü Havza Koruma Vakfı', description = 'Kuruma tehlikesi yaşayan Burdur Gölü havzasındaki tarım arazilerinde damla sulamaya geçiş sağlanarak göl suyunun yerinde korunması ve göle doğrudan can suyu aktarılması saha projesidir.' WHERE slug = 'kuruyan-gollere-can-suyu'");
        } catch (Throwable $e) {
        }
    } catch (Throwable $e) {
        // Sessizce geç
    }
}

/**
 * Kullanıcının / Hanesinin güncel kullanılabilir puan (DP) bakiyesini (Navbar'daki DP ile birebir aynı kaynak) döndürür.
 */
function get_store_wallet_balance(array $user): int
{
    $householdId = (int) ($user['household_id'] ?? 0);
    if ($householdId <= 0) {
        return 0;
    }

    try {
        ensure_store_schema();
        if (function_exists('get_household_pond_state')) {
            $pond = get_household_pond_state($householdId);
            return max(0, (int) ($pond['total_drops'] ?? 0));
        }
        recalculate_household_water_savings($householdId, (int) ($user['id'] ?? 0));
        $stmt = db()->prepare("SELECT total_points FROM households WHERE id = :hid LIMIT 1");
        $stmt->execute([':hid' => $householdId]);
        return max(0, (int) $stmt->fetchColumn());
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Yeşil Markalarla Harcama Yapma — Alt Alta Mağaza Kartlarını Listeler
 */
function get_active_store_items(): array
{
    ensure_store_schema();

    try {
        $stmt = db()->query("
            SELECT *
            FROM store_items
            WHERE is_active = 1
            ORDER BY sort_order ASC, id ASC
        ");
        $rows = $stmt ? $stmt->fetchAll() : [];
        if (!empty($rows)) {
            foreach ($rows as &$r) {
                $r['discount_pct'] = max(1, (int) ($r['discount_pct'] ?? 30));
                $r['cost_points']  = $r['discount_pct'];
            }
            unset($r);
            return $rows;
        }
    } catch (Throwable $e) {
        // Fallback listesine düş
    }

    return [
        [
            'id'           => 1,
            'slug'         => 'damla-kafe',
            'category'     => 'cafe',
            'brand_name'   => 'Damla Kafe',
            'title'        => '',
            'description'  => 'Porselen fincan ile servis yaparak gizli su tüketimini azaltmaktadır.',
            'image_path'   => 'assets/store/damla-kafe.jpg',
            'discount_pct' => 30,
            'cost_points'  => 30,
        ],
        [
            'id'           => 2,
            'slug'         => 'yesil-sofra-restoran',
            'category'     => 'restaurant',
            'brand_name'   => 'Yeşil Sofra Restoran',
            'title'        => '',
            'description'  => 'Menüdeki yemek tercihlerini düşük su ayak izli ve mevsimsel içeriklere göre ayarlayarak mutfakta su tüketimini azaltmaktadır.',
            'image_path'   => 'assets/store/yesil-sofra-restoran.jpg',
            'discount_pct' => 50,
            'cost_points'  => 50,
        ],
        [
            'id'           => 3,
            'slug'         => 'aquapure-deterjan',
            'category'     => 'detergent',
            'brand_name'   => 'AquaPure Su Dostu Deterjan',
            'title'        => '',
            'description'  => 'Su tasarrufuna katkı sağlayan ve su ayak izini azaltmayı amaçlamış konsantre çamaşır ve bulaşık deterjanı.',
            'image_path'   => 'assets/store/aquapure-deterjan.jpg',
            'discount_pct' => 10,
            'cost_points'  => 10,
        ],
        [
            'id'           => 4,
            'slug'         => 'ekoakis-perlator',
            'category'     => 'eco_product',
            'brand_name'   => 'EkoAkış',
            'title'        => '',
            'description'  => 'Havalandırmalı Musluk Perlatörü',
            'image_path'   => 'assets/store/ekoakis-perlator.jpg',
            'discount_pct' => 15,
            'cost_points'  => 15,
        ],
    ];
}

/**
 * Anlaşmalı Kampanyaya Destek Olma — 2 Spesifik Kampanyayı Listeler
 */
function get_active_support_campaigns(?array $user = null): array
{
    ensure_store_schema();

    $rows = [];
    try {
        $stmt = db()->query("
            SELECT *
            FROM support_campaigns
            WHERE is_active = 1
            ORDER BY sort_order ASC, id ASC
            LIMIT 2
        ");
        $rows = $stmt ? $stmt->fetchAll() : [];
    } catch (Throwable $e) {
        $rows = [];
    }

    if (empty($rows)) {
        $rows = [
            [
                'id'              => 1,
                'slug'            => 'okul-cesmeleri-tasarruf-donusumu',
                'partner_name'    => 'Yeşil Okullar & İlçe Milli Eğitim Su İşbirliği',
                'title'           => 'Mahallemizdeki Okullarda Tasarruflu Musluk & Gri Su Dönüşüm Kampanyası',
                'description'     => 'Mahallemizde bulunan ilkokul, ortaokul ve liselerdeki okul lavabolarının perlatörlü musluklarla dönüştürülmesi ve yağmur/gri su geri kazanım ünitesi kurulması saha uygulamasıdır.',
                'image_path'      => 'assets/store/campaign-school.jpg',
                'min_points'      => 50,
                'target_dp'       => 3500,
                'current_dp'      => 950,
                'supporter_count' => 19,
            ],
            [
                'id'              => 2,
                'slug'            => 'kuruyan-gollere-can-suyu',
                'partner_name'    => 'Debi & Burdur Gölü Havza Koruma Vakfı',
                'title'           => 'Burdur Gölü ve Havzasına Can Suyu Kampanyası',
                'description'     => 'Kuruma tehlikesi yaşayan Burdur Gölü havzasındaki tarım arazilerinde damla sulamaya geçiş sağlanarak göl suyunun yerinde korunması ve göle doğrudan can suyu aktarılması saha projesidir.',
                'image_path'      => 'assets/store/campaign-lake.jpg',
                'min_points'      => 50,
                'target_dp'       => 5000,
                'current_dp'      => 1450,
                'supporter_count' => 29,
            ],
        ];
    }

    $districtName     = trim((string) ($user['neighborhood_district'] ?? 'Kadıköy'));
    $neighborhoodName = trim((string) ($user['neighborhood_name'] ?? 'Moda Mah.'));
    $localAreaLabel   = $districtName !== '' ? "{$districtName} · {$neighborhoodName}" : $neighborhoodName;

    foreach ($rows as &$row) {
        $slug    = (string) ($row['slug'] ?? '');
        $target  = max(1, (int) ($row['target_dp'] ?? $row['target_contrib_amount'] ?? 5000));
        $current = max(0, (int) ($row['current_dp'] ?? $row['current_contrib_amount'] ?? 0));
        $row['target_dp']    = $target;
        $row['current_dp']   = $current;
        $row['min_points']   = max(50, (int) ($row['min_points'] ?? $row['cost_points'] ?? 50));
        $row['progress_pct'] = min(100, round(($current / $target) * 100, 1));

        if ($slug === 'okul-cesmeleri-tasarruf-donusumu') {
            $row['image_path']  = $row['image_path'] ?: 'assets/store/campaign-school.jpg';
            $row['title']       = "{$neighborhoodName} Okullarında Tasarruflu Musluk & Gri Su Dönüşüm Kampanyası";
            $row['scope_title'] = "Saha Uygulama Kapsamı: {$localAreaLabel} Devlet Okulları";
            $row['scope_items'] = [
                "{$neighborhoodName} İlkokulu & Ortaokulu: 42 lavabo musluğuna %50 tasarruflu perlatör montajı",
                "{$districtName} Anadolu Lisesi: Bahçe sulaması için 5 tonluk yağmur ve gri su hasadı deposu kurulumu",
                "Saha Ziyareti & Uygulama Takvimi: Hedef puana ulaşıldığında mahalle gönüllüleri ve teknik ekip ile yerinde montaj uygulaması gerçekleştirilir.",
            ];
        } else {
            $row['image_path']  = $row['image_path'] ?: 'assets/store/campaign-lake.jpg';
            $row['title']       = 'Burdur Gölü ve Havzasına Can Suyu Kampanyası';
            $row['scope_title'] = 'Saha Uygulama Kapsamı: Burdur Gölü Kapalı Havzası';
            $row['scope_items'] = [
                'Burdur Gölü Kıyı Köyleri (Karakent & İlyas): Yem bitkisi tarlalarında vahşi sulamadan kapalı damla sulama hattına dönüşüm',
                'Göl Besleme Dereleri: Buharlaşma kaybını azaltan su iletim hatları ile göle doğrudan can suyu salımı',
                'Saha Ziyareti & Uygulama Takvimi: Dönemsel saha gezileriyle kurulan damla sulama parselleri ve göl su seviyesi yerinde incelenir.',
            ];
        }
    }
    unset($row);

    return $rows;
}

/**
 * Yeşil Mağaza Ürününde %X İndirim İçin X DP Harcama İşlemi
 */
function redeem_store_item(array $user, int $itemId, string $itemSlug = ''): array
{
    ensure_store_schema();

    $userId      = (int) ($user['id'] ?? 0);
    $householdId = (int) ($user['household_id'] ?? 0);
    if ($userId <= 0 || $householdId <= 0 || ($itemId <= 0 && $itemSlug === '')) {
        return ['ok' => false, 'error' => 'Geçersiz mağaza seçimi.'];
    }

    $pdo  = db();
    $item = null;

    if ($itemSlug !== '') {
        $stmt = $pdo->prepare("SELECT * FROM store_items WHERE slug = :slug AND is_active = 1 LIMIT 1");
        $stmt->execute([':slug' => $itemSlug]);
        $item = $stmt->fetch() ?: null;
    }
    if (!$item && $itemId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM store_items WHERE id = :id AND is_active = 1 LIMIT 1");
        $stmt->execute([':id' => $itemId]);
        $item = $stmt->fetch() ?: null;
    }
    if (!$item) {
        foreach (get_active_store_items() as $fallbackItem) {
            if (($itemSlug !== '' && ($fallbackItem['slug'] ?? '') === $itemSlug) || ((int) ($fallbackItem['id'] ?? 0) === $itemId)) {
                try {
                    $insItem = $pdo->prepare("
                        INSERT INTO store_items (slug, category, brand_name, title, description, image_path, discount_pct, cost_points, is_active, sort_order)
                        VALUES (:slug, :cat, :brand, '', :desc, :img, :disc, :cost, 1, 1)
                        ON DUPLICATE KEY UPDATE brand_name = VALUES(brand_name), discount_pct = VALUES(discount_pct), cost_points = VALUES(cost_points)
                    ");
                    $insItem->execute([
                        ':slug'  => $fallbackItem['slug'],
                        ':cat'   => $fallbackItem['category'] ?? 'cafe',
                        ':brand' => $fallbackItem['brand_name'],
                        ':desc'  => $fallbackItem['description'] ?? '',
                        ':img'   => $fallbackItem['image_path'] ?? null,
                        ':disc'  => (int) $fallbackItem['discount_pct'],
                        ':cost'  => (int) $fallbackItem['discount_pct'],
                    ]);
                    $stmt = $pdo->prepare("SELECT * FROM store_items WHERE slug = :slug LIMIT 1");
                    $stmt->execute([':slug' => $fallbackItem['slug']]);
                    $item = $stmt->fetch() ?: $fallbackItem;
                } catch (Throwable $e) {
                    $item = $fallbackItem;
                }
                break;
            }
        }
    }
    if (!$item) {
        return ['ok' => false, 'error' => 'Seçilen anlaşmalı mağaza bulunamadı.'];
    }

    $discountPct = max(1, (int) ($item['discount_pct'] ?? 30));
    $costPoints  = $discountPct; // %30 indirim için 30 DP, %50 indirim için 50 DP
    $balance     = get_store_wallet_balance($user);

    if ($balance < $costPoints) {
        return [
            'ok'    => false,
            'error' => "Yetersiz puan bakiyesi! %{$discountPct} indirim için {$costPoints} DP gerekiyor (Mevcut bakiyeniz: {$balance} DP).",
        ];
    }

    $couponCode = 'DEBI' . $discountPct . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

    try {
        $pdo->beginTransaction();

        // PDO ATTR_EMULATE_PREPARES = false için her parametre ismi tekil olmalıdır (:pts_sub ve :pts_min)
        $deductStmt = $pdo->prepare("
            UPDATE households
            SET total_points = total_points - :pts_sub
            WHERE id = :hid AND total_points >= :pts_min
        ");
        $deductStmt->execute([
            ':pts_sub' => $costPoints,
            ':hid'     => $householdId,
            ':pts_min' => $costPoints,
        ]);

        if ($deductStmt->rowCount() !== 1) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Puan bakiyeniz bu işlem için yeterli değil.'];
        }

        $insRedeem = $pdo->prepare("
            INSERT INTO store_redemptions (user_id, household_id, store_item_id, spent_points, discount_pct, coupon_code, is_used)
            VALUES (:uid, :hid, :iid, :pts, :disc, :code, 0)
        ");
        $insRedeem->execute([
            ':uid'  => $userId,
            ':hid'  => $householdId,
            ':iid'  => (int) ($item['id'] ?? 1),
            ':pts'  => $costPoints,
            ':disc' => $discountPct,
            ':code' => $couponCode,
        ]);

        $pdo->commit();

        try {
            $desc = "🌿 {$item['brand_name']}: %{$discountPct} indirim kuponu oluşturuldu (-{$costPoints} DP · {$couponCode})";
            $insLog = $pdo->prepare("
                INSERT INTO point_logs (user_id, household_id, action_code, points, description, ref_key)
                VALUES (:uid, :hid, 'store_redeem', :pts, :desc, :rkey)
            ");
            $insLog->execute([
                ':uid'  => $userId,
                ':hid'  => $householdId,
                ':pts'  => -$costPoints,
                ':desc' => $desc,
                ':rkey' => 'store_redeem_' . $couponCode,
            ]);
        } catch (Throwable $logErr) {
            // Audit log hatası ana işlemi etkilemesin
        }

        return [
            'ok'           => true,
            'coupon_code'  => $couponCode,
            'brand_name'   => (string) $item['brand_name'],
            'discount_pct' => $discountPct,
            'spent_points' => $costPoints,
            'message'      => "Satın alımınız onaylandı! \"{$item['brand_name']}\" mağazasında %{$discountPct} indirim kuponu aldınız. Kupon Kodunuz: {$couponCode}",
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'error' => 'Mağaza puan harcama işlemi sırasında bir hata oluştu.'];
    }
}

/**
 * Aktif Kuponu "Kullanıldı" Olarak İşaretleme
 */
function mark_store_coupon_used(array $user, int $redemptionId): array
{
    ensure_store_schema();
    $householdId = (int) ($user['household_id'] ?? 0);
    if ($householdId <= 0 || $redemptionId <= 0) {
        return ['ok' => false, 'error' => 'Geçersiz kupon seçimi.'];
    }

    try {
        $stmt = db()->prepare("
            UPDATE store_redemptions
            SET is_used = 1, used_at = NOW()
            WHERE id = :id AND household_id = :hid AND is_used = 0
        ");
        $stmt->execute([':id' => $redemptionId, ':hid' => $householdId]);
        if ($stmt->rowCount() < 1) {
            return ['ok' => false, 'error' => 'Kupon bulunamadı veya daha önce kullanılmış.'];
        }
        return ['ok' => true, 'message' => 'Kupon kullanıldı olarak işaretlendi ve geçmişe taşındı.'];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Kupon durumu güncellenemedi.'];
    }
}

/**
 * Anlaşmalı Kampanyaya DP Gönderimi (Min 50 DP, Maksimum Sınır Yok)
 */
function support_campaign_with_points(array $user, int $campaignId, int $requestedPoints = 50, string $campaignSlug = ''): array
{
    ensure_store_schema();

    $userId      = (int) ($user['id'] ?? 0);
    $householdId = (int) ($user['household_id'] ?? 0);
    if ($userId <= 0 || $householdId <= 0 || ($campaignId <= 0 && $campaignSlug === '')) {
        return ['ok' => false, 'error' => 'Geçersiz kampanya seçimi.'];
    }

    $pdo      = db();
    $campaign = null;

    if ($campaignSlug !== '') {
        $stmt = $pdo->prepare("SELECT * FROM support_campaigns WHERE slug = :slug AND is_active = 1 LIMIT 1");
        $stmt->execute([':slug' => $campaignSlug]);
        $campaign = $stmt->fetch() ?: null;
    }
    if (!$campaign && $campaignId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM support_campaigns WHERE id = :id AND is_active = 1 LIMIT 1");
        $stmt->execute([':id' => $campaignId]);
        $campaign = $stmt->fetch() ?: null;
    }
    if (!$campaign) {
        foreach (get_active_support_campaigns($user) as $fallbackCamp) {
            if (($campaignSlug !== '' && ($fallbackCamp['slug'] ?? '') === $campaignSlug) || ((int) ($fallbackCamp['id'] ?? 0) === $campaignId)) {
                try {
                    $insCamp = $pdo->prepare("
                        INSERT INTO support_campaigns (slug, partner_name, title, description, image_path, min_points, target_dp, current_dp, supporter_count, is_active, sort_order)
                        VALUES (:slug, :partner, :title, :desc, :img, :minp, :tardp, :curdp, :supcnt, 1, 1)
                        ON DUPLICATE KEY UPDATE partner_name = VALUES(partner_name), title = VALUES(title)
                    ");
                    $insCamp->execute([
                        ':slug'    => $fallbackCamp['slug'],
                        ':partner' => $fallbackCamp['partner_name'],
                        ':title'   => $fallbackCamp['title'],
                        ':desc'    => $fallbackCamp['description'] ?? '',
                        ':img'     => $fallbackCamp['image_path'] ?? null,
                        ':minp'    => (int) ($fallbackCamp['min_points'] ?? 50),
                        ':tardp'   => (int) ($fallbackCamp['target_dp'] ?? 5000),
                        ':curdp'   => (int) ($fallbackCamp['current_dp'] ?? 1000),
                        ':supcnt'  => (int) ($fallbackCamp['supporter_count'] ?? 20),
                    ]);
                    $stmt = $pdo->prepare("SELECT * FROM support_campaigns WHERE slug = :slug LIMIT 1");
                    $stmt->execute([':slug' => $fallbackCamp['slug']]);
                    $campaign = $stmt->fetch() ?: $fallbackCamp;
                } catch (Throwable $e) {
                    $campaign = $fallbackCamp;
                }
                break;
            }
        }
    }
    if (!$campaign) {
        return ['ok' => false, 'error' => 'Seçilen anlaşmalı kampanya bulunamadı.'];
    }

    $minPoints = max(50, (int) ($campaign['min_points'] ?? $campaign['cost_points'] ?? 50));
    if ($requestedPoints < $minPoints) {
        return [
            'ok'    => false,
            'error' => "Kampanyaya gönderilecek destek miktarı en az {$minPoints} DP olmalıdır.",
        ];
    }

    $balance = get_store_wallet_balance($user);
    if ($balance < $requestedPoints) {
        return [
            'ok'    => false,
            'error' => "Yetersiz puan bakiyesi! {$requestedPoints} DP göndermek için yeterli puanınız yok (Mevcut bakiyeniz: {$balance} DP).",
        ];
    }

    try {
        $pdo->beginTransaction();

        // PDO ATTR_EMULATE_PREPARES = false için her parametre ismi tekil olmalıdır (:pts_sub ve :pts_min)
        $deductStmt = $pdo->prepare("
            UPDATE households
            SET total_points = total_points - :pts_sub
            WHERE id = :hid AND total_points >= :pts_min
        ");
        $deductStmt->execute([
            ':pts_sub' => $requestedPoints,
            ':hid'     => $householdId,
            ':pts_min' => $requestedPoints,
        ]);

        if ($deductStmt->rowCount() !== 1) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Puan bakiyeniz bu kampanya desteği için yeterli değil.'];
        }

        $updCamp = $pdo->prepare("
            UPDATE support_campaigns
            SET current_dp      = current_dp + :pts,
                supporter_count = supporter_count + 1
            WHERE id = :cid
        ");
        $updCamp->execute([
            ':pts' => $requestedPoints,
            ':cid' => (int) ($campaign['id'] ?? 1),
        ]);

        $insSup = $pdo->prepare("
            INSERT INTO campaign_supports (user_id, household_id, campaign_id, spent_points)
            VALUES (:uid, :hid, :cid, :pts)
        ");
        $insSup->execute([
            ':uid' => $userId,
            ':hid' => $householdId,
            ':cid' => (int) ($campaign['id'] ?? 1),
            ':pts' => $requestedPoints,
        ]);
        $supportId = (int) $pdo->lastInsertId();

        $pdo->commit();

        try {
            $desc = "💧 {$campaign['title']} kampanyasına {$requestedPoints} DP destek gönderildi";
            $insLog = $pdo->prepare("
                INSERT INTO point_logs (user_id, household_id, action_code, points, description, ref_key)
                VALUES (:uid, :hid, 'campaign_support', :pts, :desc, :rkey)
            ");
            $insLog->execute([
                ':uid'  => $userId,
                ':hid'  => $householdId,
                ':pts'  => -$requestedPoints,
                ':desc' => $desc,
                ':rkey' => 'campaign_support_' . $supportId,
            ]);
        } catch (Throwable $logErr) {
            // Audit log hatası ana işlemi etkilemesin
        }

        $neighborhoodName = trim((string) ($user['neighborhood_name'] ?? 'Moda Mah.'));
        $slug             = (string) ($campaign['slug'] ?? '');
        $displayTitle     = $slug === 'okul-cesmeleri-tasarruf-donusumu'
            ? "{$neighborhoodName} Okullarında Tasarruflu Musluk & Gri Su Dönüşüm Kampanyası"
            : (string) ($campaign['title'] ?? 'Kampanya');
        $newCurrentDp     = (int) ($campaign['current_dp'] ?? 0) + $requestedPoints;
        $targetDp         = max(1, (int) ($campaign['target_dp'] ?? 5000));
        $progressPct      = min(100, round(($newCurrentDp / $targetDp) * 100, 1));
        $newSupporters    = (int) ($campaign['supporter_count'] ?? 0) + 1;
        $dateLabel        = function_exists('format_date_tr')
            ? format_date_tr(date('Y-m-d H:i:s'), false)
            : date('d.m.Y');

        return [
            'ok'          => true,
            'message'     => "Teşekkürler! \"{$displayTitle}\" kampanyasına {$requestedPoints} DP destek gönderimi sağladınız.",
            'certificate' => [
                'support_id'      => $supportId,
                'recipient_name'  => (string) ($user['full_name'] ?? 'Değerli Katılımcı'),
                'campaign_title'  => $displayTitle,
                'partner_name'    => (string) ($campaign['partner_name'] ?? 'Debi Su İşbirliği'),
                'spent_points'    => $requestedPoints,
                'current_dp'      => $newCurrentDp,
                'target_dp'       => $targetDp,
                'progress_pct'    => $progressPct,
                'supporter_count' => $newSupporters,
                'date_label'      => $dateLabel,
                'president_name'  => 'Debi Su Koruma Kurulu',
            ],
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'error' => 'Kampanya desteği gönderilirken bir hata oluştu.'];
    }
}

/**
 * Hanenin Yeşil Mağaza Kuponları ve Harcama Geçmişi
 */
function get_household_store_redemptions(int $householdId, int $limit = 20): array
{
    ensure_store_schema();
    if ($householdId <= 0) {
        return [];
    }

    $safeLimit = max(1, min(100, (int) $limit));
    try {
        $stmt = db()->prepare("
            SELECT sr.*,
                   COALESCE(si.brand_name, 'Yeşil Mağaza') AS brand_name,
                   COALESCE(si.title, '') AS title,
                   COALESCE(si.category, 'cafe') AS category,
                   COALESCE(u.full_name, '') AS full_name
            FROM store_redemptions sr
            LEFT JOIN store_items si ON si.id = sr.store_item_id
            LEFT JOIN users u ON u.id = sr.user_id
            WHERE sr.household_id = :hid
            ORDER BY sr.created_at DESC, sr.id DESC
            LIMIT {$safeLimit}
        ");
        $stmt->execute([':hid' => $householdId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Hanenin Katkıda Bulunduğu Anlaşmalı Kampanyalar Listesi
 */
function get_household_campaign_supports(int $householdId, int $limit = 20): array
{
    ensure_store_schema();
    if ($householdId <= 0) {
        return [];
    }

    $safeLimit = max(1, min(100, (int) $limit));
    try {
        $stmt = db()->prepare("
            SELECT cs.*,
                   COALESCE(sc.title, 'Su Tasarrufu Kampanyası') AS campaign_title,
                   COALESCE(sc.partner_name, 'Debi Su İşbirliği') AS partner_name,
                   COALESCE(sc.slug, '') AS campaign_slug,
                   COALESCE(sc.current_dp, 1000) AS current_dp,
                   COALESCE(sc.target_dp, 5000) AS target_dp,
                   COALESCE(sc.supporter_count, 20) AS supporter_count,
                   COALESCE(u.full_name, '') AS full_name
            FROM campaign_supports cs
            LEFT JOIN support_campaigns sc ON sc.id = cs.campaign_id
            LEFT JOIN users u ON u.id = cs.user_id
            WHERE cs.household_id = :hid
            ORDER BY cs.created_at DESC, cs.id DESC
            LIMIT {$safeLimit}
        ");
        $stmt->execute([':hid' => $householdId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}
