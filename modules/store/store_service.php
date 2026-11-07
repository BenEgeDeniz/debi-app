<?php
/**
 * DEBI - Puan Kullanım, Yeşil Markalar Mağazası & Anlaşmalı Kampanya Destek Modülü
 */

if (!function_exists('get_household_pond_state')) {
    require_once __DIR__ . '/../water/water_service.php';
}

/**
 * Mağaza ve Kampanya tablolarının kurulu olduğundan emin olur.
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
        $chk = $pdo->query("SHOW TABLES LIKE 'store_items'");
        if ($chk && $chk->rowCount() > 0) {
            return;
        }

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
                        // Tekil ifade hatasını yut
                    }
                }
            }
        }
    } catch (Throwable $e) {
        // Sessizce geç
    }
}

/**
 * Kullanıcının / Hanesinin güncel kullanılabilir puan bakiyesini döndürür.
 */
function get_store_wallet_balance(array $user): int
{
    $householdId = (int) ($user['household_id'] ?? 0);
    if ($householdId <= 0) {
        return 0;
    }

    try {
        ensure_store_schema();
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
            return $rows;
        }
    } catch (Throwable $e) {
        // Fallback listesine düş
    }

    return [
        [
            'id'           => 1,
            'slug'         => 'cam-fincan-ekokafe',
            'category'     => 'cafe',
            'brand_name'   => 'Damla & Fincan Yeşil Kafe',
            'title'        => 'Cam Bardak ve Porselen Fincan Kullanan Anlaşmalı Kafe İndirimi',
            'description'  => 'Tek kullanımlık kağıt ve plastik bardak harcamasını ortadan kaldıran; yalnızca cam bardak ve porselen fincan ile servis yaparak gizli su tüketimini azaltan anlaşmalı kafede tüm içeceklerde %50 indirim.',
            'eco_badge'    => '☕ Cam Bardak & Fincan · Sıfır Kağıt/Plastik',
            'discount_pct' => 50,
            'cost_points'  => 50,
            'icon_emoji'   => '☕',
        ],
        [
            'id'           => 2,
            'slug'         => 'yesil-damla-roastery',
            'category'     => 'cafe',
            'brand_name'   => 'Yeşil Damla Roastery & Kafe',
            'title'        => 'Kağıt ve Plastik Harcamasını Azaltan Kafe %50 İndirim Kuponu',
            'description'  => 'Kendi termosunu getirenlere veya kafe içi cam bardak/fincan tercih edenlere özel; kağıt-plastik atığını ve üretim suyu ayak izini azaltan anlaşmalı kahve mağazasında %50 indirim.',
            'eco_badge'    => '🥛 Cam Bardak Kullanımı · Su Tasarrufu Dostu',
            'discount_pct' => 50,
            'cost_points'  => 50,
            'icon_emoji'   => '🍵',
        ],
        [
            'id'           => 3,
            'slug'         => 'biodamla-eko-deterjan',
            'category'     => 'detergent',
            'brand_name'   => 'BioDamla Ekolojik Deterjan',
            'title'        => 'Su Ayak İzini Azaltmayı Amaçlamış Bitkisel Deterjan Ürününde %50 İndirim',
            'description'  => 'Düşük köpüren formülüyle durulama suyundan %45 tasarruf sağlayan ve gri su kirliliğini önleyerek su ayak izini azaltmayı amaçlamış anlaşmalı deterjan markasının ürününde %50 indirim.',
            'eco_badge'    => '🫧 Düşük Su Ayak İzi · Ekolojik Deterjan',
            'discount_pct' => 50,
            'cost_points'  => 50,
            'icon_emoji'   => '🧴',
        ],
        [
            'id'           => 4,
            'slug'         => 'aquapure-konsantre-deterjan',
            'category'     => 'detergent',
            'brand_name'   => 'AquaPure Su Dostu Deterjan',
            'title'        => 'Su Tasarrufuna Katkı Sağlayan Konsantre Çamaşır & Bulaşık Deterjanı',
            'description'  => 'Susuz konsantre tablet teknolojisiyle hem üretimde hem de evdeki yıkamalarda su tasarrufuna doğrudan katkı sağlayan anlaşmalı deterjan ürününde %50 indirim.',
            'eco_badge'    => '💧 Su Tasarruflu Durulama · Doğada Çözünür',
            'discount_pct' => 50,
            'cost_points'  => 50,
            'icon_emoji'   => '🧼',
        ],
        [
            'id'           => 5,
            'slug'         => 'ekoakis-perlator-magaza',
            'category'     => 'eco_product',
            'brand_name'   => 'EkoAkış Su Tasarruf Ekipmanları',
            'title'        => 'Su Tasarrufuna Katkı Sağlayan Havalandırmalı Musluk Perlatörü',
            'description'  => 'Standart musluklara saniyeler içinde takılan ve su akış hızını düşürmeden %50 su tasarrufu sağlayan anlaşmalı yeşil mağaza ürününde %50 indirim.',
            'eco_badge'    => '🚰 %50 Musluk Su Tasarrufu · Yeşil Marka',
            'discount_pct' => 50,
            'cost_points'  => 50,
            'icon_emoji'   => '🚰',
        ],
    ];
}

/**
 * Anlaşmalı Kampanyaya Destek Olma — Kampanya Listesini Döndürür
 */
function get_active_support_campaigns(): array
{
    ensure_store_schema();

    $rows = [];
    try {
        $stmt = db()->query("
            SELECT *
            FROM support_campaigns
            WHERE is_active = 1
            ORDER BY sort_order ASC, id ASC
        ");
        $rows = $stmt ? $stmt->fetchAll() : [];
    } catch (Throwable $e) {
        $rows = [];
    }

    if (empty($rows)) {
        $rows = [
            [
                'id'                     => 1,
                'slug'                   => 'kuruyan-gollere-can-suyu',
                'partner_name'           => 'Debi & Doğa Su Vakfı Anlaşmalı Kampanyası',
                'title'                  => 'Anlaşmalı Kuruyan Göllere ve Havzalara Can Suyu Kampanyası',
                'description'            => '50 puan harcayarak 50 L/m³\'lük bir su tasarrufu karşılığını kuruma tehdidi altındaki yerel göl ve sulak alanların restorasyon kampanyasına gönderin.',
                'impact_unit_label'      => '50 L/m³',
                'cost_points'            => 50,
                'water_contrib_amount'   => 50,
                'target_contrib_amount'  => 5000,
                'current_contrib_amount' => 1450,
                'supporter_count'        => 29,
                'icon_emoji'             => '🌊',
            ],
            [
                'id'                     => 2,
                'slug'                   => 'okul-cesmeleri-tasarruf-donusumu',
                'partner_name'           => 'Yeşil Okullar & Belediye Su İşbirliği',
                'title'                  => 'Mahalle Okullarında Tasarruflu Musluk & Gri Su Dönüşüm Kampanyası',
                'description'            => '50 puan harcayarak 50 L/m³\'lük tasarruf desteğini devlet okullarındaki muslukların fotoselli ve perlatörlü başlıklarla değiştirilmesi kampanyasına gönderin.',
                'impact_unit_label'      => '50 L/m³',
                'cost_points'            => 50,
                'water_contrib_amount'   => 50,
                'target_contrib_amount'  => 3500,
                'current_contrib_amount' => 950,
                'supporter_count'        => 19,
                'icon_emoji'             => '🏫',
            ],
            [
                'id'                     => 3,
                'slug'                   => 'yerel-tarim-damla-sulama',
                'partner_name'           => 'Anadolu Su Hasadı & Damla Sulama Girişimi',
                'title'                  => 'Yerel Çiftçilere Damla Sulama ve Yağmur Suyu Hasadı Destek Kampanyası',
                'description'            => '50 puan harcayarak 50 L/m³\'lük su tasarrufu katkısını tarımda vahşi sulama yerine damla sulama borularına geçiş yapan anlaşmalı kampanyaya aktarın.',
                'impact_unit_label'      => '50 L/m³',
                'cost_points'            => 50,
                'water_contrib_amount'   => 50,
                'target_contrib_amount'  => 4000,
                'current_contrib_amount' => 1100,
                'supporter_count'        => 22,
                'icon_emoji'             => '🌱',
            ],
        ];
    }

    foreach ($rows as &$row) {
        $target  = max(1, (int) ($row['target_contrib_amount'] ?? 5000));
        $current = max(0, (int) ($row['current_contrib_amount'] ?? 0));
        $row['progress_pct'] = min(100, round(($current / $target) * 100, 1));
    }
    unset($row);

    return $rows;
}

/**
 * Yeşil Mağaza Ürününde %50 İndirim İçin 50 Puan Harcama İşlemi
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
        return ['ok' => false, 'error' => 'Seçilen anlaşmalı mağaza ürünü bulunamadı.'];
    }

    $costPoints  = max(1, (int) ($item['cost_points'] ?? 50));
    $discountPct = max(1, (int) ($item['discount_pct'] ?? 50));
    $balance     = get_store_wallet_balance($user);

    if ($balance < $costPoints) {
        return [
            'ok'    => false,
            'error' => "Yetersiz puan bakiyesi! Bu indirim için {$costPoints} Puan gerekiyor (Mevcut bakiyeniz: {$balance} Puan).",
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
            INSERT INTO store_redemptions (user_id, household_id, store_item_id, spent_points, discount_pct, coupon_code)
            VALUES (:uid, :hid, :iid, :pts, :disc, :code)
        ");
        $insRedeem->execute([
            ':uid'  => $userId,
            ':hid'  => $householdId,
            ':iid'  => (int) $item['id'],
            ':pts'  => $costPoints,
            ':disc' => $discountPct,
            ':code' => $couponCode,
        ]);

        $desc = "🌿 {$item['brand_name']}: %{$discountPct} indirim kuponu alındı ({$couponCode})";
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
            'message'     => "Tebrikler! {$costPoints} puan harcayarak \"{$item['brand_name']}\" mağazasında %{$discountPct} indirim kazandınız. Kupon Kodunuz: {$couponCode}",
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'error' => 'Mağaza puan harcama işlemi sırasında bir hata oluştu.'];
    }
}

/**
 * Anlaşmalı Kampanyaya 50 Puan Harcayarak 50 L/m³ Tasarruf Gönderimi
 */
function support_campaign_with_points(array $user, int $campaignId): array
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

    $costPoints    = max(1, (int) ($campaign['cost_points'] ?? 50));
    $contribAmount = max(1, (int) ($campaign['water_contrib_amount'] ?? 50));
    $impactLabel   = (string) ($campaign['impact_unit_label'] ?? '50 L/m³');
    $balance       = get_store_wallet_balance($user);

    if ($balance < $costPoints) {
        return [
            'ok'    => false,
            'error' => "Yetersiz puan bakiyesi! Kampanyaya {$impactLabel} tasarruf göndermek için {$costPoints} Puan gerekiyor (Mevcut bakiyeniz: {$balance} Puan).",
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
            ':pts' => $costPoints,
            ':hid' => $householdId,
        ]);

        if ($deductStmt->rowCount() !== 1) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Puan bakiyeniz bu kampanya desteği için yeterli değil.'];
        }

        $updCamp = $pdo->prepare("
            UPDATE support_campaigns
            SET current_contrib_amount = current_contrib_amount + :contrib,
                supporter_count        = supporter_count + 1
            WHERE id = :cid
        ");
        $updCamp->execute([
            ':contrib' => $contribAmount,
            ':cid'     => (int) $campaign['id'],
        ]);

        $insSup = $pdo->prepare("
            INSERT INTO campaign_supports (user_id, household_id, campaign_id, spent_points, contrib_amount, impact_label)
            VALUES (:uid, :hid, :cid, :pts, :contrib, :label)
        ");
        $insSup->execute([
            ':uid'     => $userId,
            ':hid'     => $householdId,
            ':cid'     => (int) $campaign['id'],
            ':pts'     => $costPoints,
            ':contrib' => $contribAmount,
            ':label'   => $impactLabel,
        ]);
        $supportId = (int) $pdo->lastInsertId();

        $desc = "💧 {$campaign['title']} kampanyasına {$impactLabel} tasarruf gönderildi";
        $insLog = $pdo->prepare("
            INSERT INTO point_logs (user_id, household_id, action_code, points, description, ref_key)
            VALUES (:uid, :hid, 'campaign_support', :pts, :desc, :rkey)
        ");
        $insLog->execute([
            ':uid'  => $userId,
            ':hid'  => $householdId,
            ':pts'  => -$costPoints,
            ':desc' => $desc,
            ':rkey' => 'campaign_support_' . $supportId,
        ]);

        $pdo->commit();

        return [
            'ok'      => true,
            'message' => "Teşekkürler! {$costPoints} puan harcayarak \"{$campaign['title']}\" kampanyasına {$impactLabel} su tasarrufu gönderimi sağladınız.",
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'error' => 'Kampanya desteği gönderilirken bir hata oluştu.'];
    }
}

/**
 * Hanenin Aldığı Yeşil Mağaza İndirim Kuponları Geçmişi
 */
function get_household_store_redemptions(int $householdId, int $limit = 8): array
{
    ensure_store_schema();
    if ($householdId <= 0) {
        return [];
    }

    try {
        $stmt = db()->prepare("
            SELECT sr.*, si.brand_name, si.title, si.category, si.icon_emoji, u.full_name
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
 * Hanenin Gönderdiği Anlaşmalı Kampanya Destekleri Geçmişi
 */
function get_household_campaign_supports(int $householdId, int $limit = 8): array
{
    ensure_store_schema();
    if ($householdId <= 0) {
        return [];
    }

    try {
        $stmt = db()->prepare("
            SELECT cs.*, sc.title AS campaign_title, sc.partner_name, sc.icon_emoji, u.full_name
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

