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
        if (is_file($sqlPath)) {
            $sql = (string) file_get_contents($sqlPath);
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($statements as $stmtSql) {
                $clean = trim((string) preg_replace('/^\s*--.*$/m', '', $stmtSql));
                if ($clean !== '') {
                    try {
                        $pdo->exec($clean);
                    } catch (Throwable $inner) {
                        // Tekil ifade hatasını geç
                    }
                }
            }
        }

        // Eski şemadan kalma sütun/kayıt varsa temizle ve yeni yapıya uyarla
        try {
            $pdo->exec("ALTER TABLE store_items MODIFY COLUMN category ENUM('cafe','restaurant','detergent','eco_product') NOT NULL DEFAULT 'cafe'");
        } catch (Throwable $e) {
        }
        try {
            $pdo->exec("ALTER TABLE store_items ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER description");
        } catch (Throwable $e) {
        }
        try {
            $pdo->exec("ALTER TABLE store_redemptions ADD COLUMN is_used TINYINT(1) NOT NULL DEFAULT 0 AFTER coupon_code");
        } catch (Throwable $e) {
        }
        try {
            $pdo->exec("ALTER TABLE store_redemptions ADD COLUMN used_at DATETIME DEFAULT NULL AFTER is_used");
        } catch (Throwable $e) {
        }
        try {
            $pdo->exec("ALTER TABLE support_campaigns ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER description");
        } catch (Throwable $e) {
        }
        try {
            $pdo->exec("ALTER TABLE support_campaigns ADD COLUMN min_points INT UNSIGNED NOT NULL DEFAULT 50");
        } catch (Throwable $e) {
        }
        try {
            $pdo->exec("ALTER TABLE support_campaigns ADD COLUMN target_dp INT UNSIGNED NOT NULL DEFAULT 5000");
        } catch (Throwable $e) {
        }
        try {
            $pdo->exec("ALTER TABLE support_campaigns ADD COLUMN current_dp INT UNSIGNED NOT NULL DEFAULT 1250");
        } catch (Throwable $e) {
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
function redeem_store_item(array $user, int $itemId): array
{
    ensure_store_schema();

    $userId      = (int) ($user['id'] ?? 0);
    $householdId = (int) ($user['household_id'] ?? 0);
    if ($userId <= 0 || $householdId <= 0 || $itemId <= 0) {
        return ['ok' => false, 'error' => 'Geçersiz mağaza seçimi.'];
    }

    $pdo = db();
    $itemStmt = $pdo->prepare("SELECT * FROM store_items WHERE id = :id AND is_active = 1 LIMIT 1");
    $itemStmt->execute([':id' => $itemId]);
    $item = $itemStmt->fetch();
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

        $deductStmt = $pdo->prepare("
            UPDATE households
            SET total_points = total_points - :pts
            WHERE id = :hid AND total_points >= :pts
        ");
        $deductStmt->execute([
            ':pts' => $costPoints,
            ':hid' => $householdId,
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
            ':iid'  => (int) $item['id'],
            ':pts'  => $costPoints,
            ':disc' => $discountPct,
            ':code' => $couponCode,
        ]);

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

        $pdo->commit();

        return [
            'ok'          => true,
            'coupon_code' => $couponCode,
            'message'     => "Tebrikler! {$costPoints} DP harcayarak \"{$item['brand_name']}\" mağazasında %{$discountPct} aktif indirim kuponu aldınız. Kupon Kodunuz: {$couponCode}",
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
function support_campaign_with_points(array $user, int $campaignId, int $requestedPoints = 50): array
{
    ensure_store_schema();

    $userId      = (int) ($user['id'] ?? 0);
    $householdId = (int) ($user['household_id'] ?? 0);
    if ($userId <= 0 || $householdId <= 0 || $campaignId <= 0) {
        return ['ok' => false, 'error' => 'Geçersiz kampanya seçimi.'];
    }

    $pdo = db();
    $campStmt = $pdo->prepare("SELECT * FROM support_campaigns WHERE id = :id AND is_active = 1 LIMIT 1");
    $campStmt->execute([':id' => $campaignId]);
    $campaign = $campStmt->fetch();
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

        $deductStmt = $pdo->prepare("
            UPDATE households
            SET total_points = total_points - :pts
            WHERE id = :hid AND total_points >= :pts
        ");
        $deductStmt->execute([
            ':pts' => $requestedPoints,
            ':hid' => $householdId,
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
            ':cid' => (int) $campaign['id'],
        ]);

        $insSup = $pdo->prepare("
            INSERT INTO campaign_supports (user_id, household_id, campaign_id, spent_points)
            VALUES (:uid, :hid, :cid, :pts)
        ");
        $insSup->execute([
            ':uid' => $userId,
            ':hid' => $householdId,
            ':cid' => (int) $campaign['id'],
            ':pts' => $requestedPoints,
        ]);
        $supportId = (int) $pdo->lastInsertId();

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

        $pdo->commit();

        return [
            'ok'      => true,
            'message' => "Teşekkürler! \"{$campaign['title']}\" kampanyasına {$requestedPoints} DP destek gönderimi sağladınız.",
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

    try {
        $stmt = db()->prepare("
            SELECT sr.*, si.brand_name, si.title, si.category, u.full_name
            FROM store_redemptions sr
            INNER JOIN store_items si ON si.id = sr.store_item_id
            INNER JOIN users u ON u.id = sr.user_id
            WHERE sr.household_id = :hid
            ORDER BY sr.created_at DESC, sr.id DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':hid', $householdId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
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

    try {
        $stmt = db()->prepare("
            SELECT cs.*, sc.title AS campaign_title, sc.partner_name, sc.slug AS campaign_slug, u.full_name
            FROM campaign_supports cs
            INNER JOIN support_campaigns sc ON sc.id = cs.campaign_id
            INNER JOIN users u ON u.id = cs.user_id
            WHERE cs.household_id = :hid
            ORDER BY cs.created_at DESC, cs.id DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':hid', $householdId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}
