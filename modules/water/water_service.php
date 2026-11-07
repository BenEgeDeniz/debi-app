<?php
/**
 * DEBI - Su Tüketimi ve Tasarruf Alışkanlıkları Modülü
 */

/**
 * Aylık Su Tüketim Kaydı Ekleme / Güncelleme
 */
function save_water_log(array $user, array $input): array
{
    $year          = (int) ($input['period_year'] ?? date('Y'));
    $month         = (int) ($input['period_month'] ?? date('n'));
    $consumptionM3 = (float) str_replace(',', '.', trim((string) ($input['consumption_m3'] ?? '0')));
    $billAmount    = trim((string) ($input['bill_amount'] ?? ''));
    $billVal       = $billAmount !== '' ? (float) str_replace(',', '.', $billAmount) : null;
    $readingDays   = max(1, min(90, (int) ($input['reading_days'] ?? 30)));
    $providerName  = trim((string) ($input['provider_name'] ?? ''));
    $billImagePath = trim((string) ($input['bill_image_path'] ?? ''));
    $notes         = trim($input['notes'] ?? '');
    $feedScope     = trim((string) ($input['feed_scope'] ?? ''));
    if ($feedScope === '') {
        $feedScope = !empty($input['share_on_feed']) ? 'friends' : 'none';
    }
    if (!in_array($feedScope, ['none', 'friends', 'apartment', 'public'], true)) {
        $feedScope = 'friends';
    }
    if ($feedScope === 'apartment' && empty($user['apartment_id'])) {
        $feedScope = 'friends';
    }

    if ($year < 2020 || $year > 2035 || $month < 1 || $month > 12) {
        return ['ok' => false, 'error' => 'Geçersiz dönem seçimi.'];
    }

    if ($consumptionM3 <= 0 || $consumptionM3 > 500) {
        return ['ok' => false, 'error' => 'Lütfen geçerli bir su tüketim miktarı (m³) girin (Örn: 9.5).'];
    }

    // Yalnızca uploads/bills/ altındaki geçerli görsel yoluna izin ver
    if ($billImagePath !== '' && strpos($billImagePath, 'uploads/bills/') !== 0) {
        $billImagePath = '';
    }

    $residentCount = max(1, (int) ($user['resident_count'] ?? 2));
    // 1 m3 = 1000 Litre. Faturadaki okuma gün sayısı ve kişi sayısına göre günlük litre:
    $dailyLitersPerPerson = round(($consumptionM3 * 1000) / ($readingDays * $residentCount), 1);

    if (function_exists('ensure_gamification_schema')) {
        ensure_gamification_schema();
    }

    $pdo = db();

    $stmt = $pdo->prepare("
        INSERT INTO water_logs (
            household_id, user_id, neighborhood_id, apartment_id,
            period_year, period_month, consumption_m3,
            previous_consumption_m3, saved_m3, saved_liters, saving_pct, earned_points,
            resident_count_snapshot, daily_liters_per_person,
            bill_amount, bill_image_path, provider_name, reading_days, notes
        ) VALUES (
            :hid, :uid, :nid, :aid,
            :y, :m, :m3,
            NULL, 0.00, 0, 0.00, 0,
            :rcount, :daily,
            :bill, :bimg, :prov, :days, :notes
        )
        ON DUPLICATE KEY UPDATE
            user_id                 = VALUES(user_id),
            consumption_m3          = VALUES(consumption_m3),
            resident_count_snapshot = VALUES(resident_count_snapshot),
            daily_liters_per_person = VALUES(daily_liters_per_person),
            bill_amount             = VALUES(bill_amount),
            bill_image_path         = COALESCE(VALUES(bill_image_path), bill_image_path),
            provider_name           = VALUES(provider_name),
            reading_days            = VALUES(reading_days),
            notes                   = VALUES(notes)
    ");

    $stmt->execute([
        ':hid'    => (int) $user['household_id'],
        ':uid'    => (int) $user['id'],
        ':nid'    => (int) $user['neighborhood_id'],
        ':aid'    => !empty($user['apartment_id']) ? (int) $user['apartment_id'] : null,
        ':y'      => $year,
        ':m'      => $month,
        ':m3'     => $consumptionM3,
        ':rcount' => $residentCount,
        ':daily'  => $dailyLitersPerPerson,
        ':bill'   => $billVal,
        ':bimg'   => $billImagePath !== '' ? $billImagePath : null,
        ':prov'   => $providerName !== '' ? $providerName : null,
        ':days'   => $readingDays,
        ':notes'  => $notes !== '' ? $notes : null,
    ]);

    $monthName = turkish_month_name($month) . ' ' . $year;

    // Kronolojik olarak gerçek faturalar arasındaki tasarruf farkını ve ortak göleti güncelle
    $calc = recalculate_household_water_savings((int) $user['household_id'], (int) $user['id'], $year, $month);
    $gecenAyM3       = $calc['previous_m3'];
    $tasarrufM3      = $calc['saved_m3'];
    $kurtarilanLitre = $calc['saved_liters'];
    $tasarrufYuzdesi = $calc['saving_pct'];
    $earnedPoints    = $calc['points_awarded'];

    // Seçilen akışta (Arkadaşlar / Apartman / Genel Debi) fatura paylaşımı oluştur
    if ($feedScope !== 'none') {
        $provTag  = $providerName !== '' ? " ({$providerName})" : '';
        $postText = "{$monthName} dönemi{$provTag} su faturamızı kaydettik: {$consumptionM3} m³.";
        if ($kurtarilanLitre > 0) {
            $postText .= " Önceki faturamıza göre {$kurtarilanLitre} L (%{$tasarrufYuzdesi}) su kurtararak Debi Puan Göleti'ne katkıda bulunduk!";
        }
        if ($notes !== '') {
            $postText .= " Notumuz: " . $notes;
        }

        $pStmt = $pdo->prepare("
            INSERT INTO posts (user_id, neighborhood_id, apartment_id, visibility, category, content)
            VALUES (:uid, :nid, :aid, :vis, 'achievement', :content)
        ");
        $pStmt->execute([
            ':uid'     => (int) $user['id'],
            ':nid'     => (int) $user['neighborhood_id'],
            ':aid'     => !empty($user['apartment_id']) ? (int) $user['apartment_id'] : null,
            ':vis'     => $feedScope,
            ':content' => $postText,
        ]);
    }

    return [
        'ok'               => true,
        'daily_liters'     => $dailyLitersPerPerson,
        'previous_m3'      => $gecenAyM3,
        'saved_m3'         => $tasarrufM3,
        'saved_liters'     => $kurtarilanLitre,
        'saving_pct'       => $tasarrufYuzdesi,
        'points_awarded'   => $earnedPoints,
    ];
}

/**
 * Hanenin yüklediği gerçek fatura kayıtlarını kronolojik sırayla karşılaştırarak
 * tasarruf miktarlarını (saved_m3, saved_liters, saving_pct, earned_points) ve
 * ortak Debi Puan Göleti (collective_pond) toplamlarını gerçek verilerle senkronize eder.
 */
function recalculate_household_water_savings(int $householdId, int $userId = 0, ?int $targetYear = null, ?int $targetMonth = null): array
{
    $emptyResult = [
        'previous_m3'    => null,
        'saved_m3'       => 0.0,
        'saved_liters'   => 0,
        'saving_pct'     => 0.0,
        'points_awarded' => 0,
    ];

    if ($householdId <= 0) {
        return $emptyResult;
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT id, user_id, period_year, period_month, consumption_m3,
                   previous_consumption_m3, saved_m3, saved_liters, saving_pct, earned_points
            FROM water_logs
            WHERE household_id = :hid
            ORDER BY period_year ASC, period_month ASC
        ");
        $stmt->execute([':hid' => $householdId]);
        $rows = $stmt->fetchAll();

        $updStmt = $pdo->prepare("
            UPDATE water_logs
            SET previous_consumption_m3 = :prevm3,
                saved_m3                = :savedm3,
                saved_liters            = :savedl,
                saving_pct              = :savepct,
                earned_points           = :pts
            WHERE id = :id
        ");

        $matchedResult = null;
        $latestResult  = $emptyResult;
        $totalRows     = count($rows);

        for ($i = 0; $i < $totalRows; $i++) {
            $cur   = $rows[$i];
            $curM3 = round((float) $cur['consumption_m3'], 2);

            $prevM3       = null;
            $savedM3      = 0.0;
            $savedLiters  = 0;
            $savingPct    = 0.0;
            $earnedPoints = 0;

            if ($i > 0) {
                $prevRow = $rows[$i - 1];
                $prevM3  = round((float) $prevRow['consumption_m3'], 2);
                if ($prevM3 > 0) {
                    $diff = round($prevM3 - $curM3, 2);
                    if ($diff > 0) {
                        $savedM3      = $diff;
                        $savedLiters  = (int) round($savedM3 * 1000);
                        $savingPct    = round(($savedM3 / $prevM3) * 100, 2);
                        $earnedPoints = $savingPct >= 10.0 ? 10 : 0;
                    }
                }
            }

            $oldPrev = $cur['previous_consumption_m3'] !== null ? round((float) $cur['previous_consumption_m3'], 2) : null;
            if (
                $oldPrev !== $prevM3 ||
                round((float) ($cur['saved_m3'] ?? 0), 2) !== $savedM3 ||
                (int) ($cur['saved_liters'] ?? 0) !== $savedLiters ||
                round((float) ($cur['saving_pct'] ?? 0), 2) !== $savingPct ||
                (int) ($cur['earned_points'] ?? 0) !== $earnedPoints
            ) {
                $updStmt->execute([
                    ':prevm3'  => $prevM3,
                    ':savedm3' => $savedM3,
                    ':savedl'  => $savedLiters,
                    ':savepct' => $savingPct,
                    ':pts'     => $earnedPoints,
                    ':id'      => (int) $cur['id'],
                ]);
            }

            if ($earnedPoints > 0 && function_exists('award_points')) {
                $targetUid = $userId > 0 ? $userId : (int) ($cur['user_id'] ?? 0);
                if ($targetUid > 0) {
                    $mLabel = turkish_month_name((int) $cur['period_month']) . ' ' . (int) $cur['period_year'];
                    award_points(
                        $targetUid,
                        $householdId,
                        'bill_saving_10pct',
                        $earnedPoints,
                        "💧 {$mLabel} faturasında %{$savingPct} ({$savedLiters} L) su tasarrufu ile +{$earnedPoints} Debi Puanı!",
                        'bill_saving_' . (int) $cur['period_year'] . '_' . (int) $cur['period_month']
                    );
                }
            }

            $rowResult = [
                'previous_m3'    => $prevM3,
                'saved_m3'       => $savedM3,
                'saved_liters'   => $savedLiters,
                'saving_pct'     => $savingPct,
                'points_awarded' => $earnedPoints,
            ];
            $latestResult = $rowResult;
            if ($targetYear !== null && $targetMonth !== null && (int) $cur['period_year'] === $targetYear && (int) $cur['period_month'] === $targetMonth) {
                $matchedResult = $rowResult;
            }
        }

        $pdo->exec("
            INSERT IGNORE INTO collective_pond (id, golet_mevcut_su_m3, golet_mevcut_litre, golet_toplam_puan, golet_hedef_m3)
            VALUES (1, 0.00, 0, 0, 500.00)
        ");
        $pdo->exec("
            UPDATE collective_pond cp
            JOIN (
                SELECT
                    COALESCE(SUM(saved_m3), 0) AS total_m3,
                    COALESCE(SUM(saved_liters), 0) AS total_liters,
                    COALESCE(SUM(earned_points), 0) AS total_pts
                FROM water_logs
            ) agg
            SET cp.golet_mevcut_su_m3 = agg.total_m3,
                cp.golet_mevcut_litre = agg.total_liters,
                cp.golet_toplam_puan  = agg.total_pts
            WHERE cp.id = 1
        ");

        return $matchedResult ?? $latestResult;
    } catch (Throwable $e) {
        return $emptyResult;
    }
}

/**
 * Hanenin Geçmiş Su Tüketim Kayıtları
 */
function get_household_water_history(int $householdId): array
{
    $stmt = db()->prepare("
        SELECT *
        FROM water_logs
        WHERE household_id = :hid
        ORDER BY period_year DESC, period_month DESC
        LIMIT 12
    ");
    $stmt->execute([':hid' => $householdId]);
    return $stmt->fetchAll();
}

/**
 * Sağ Yan Menü ve Özet Kartları İçin Hızlı Tüketim Kıyaslaması
 */
function get_quick_water_summary(array $user): array
{
    $pdo   = db();
    $year  = (int) date('Y');
    $month = (int) date('n');

    // Hanenin bu ayki kaydı
    $myStmt = $pdo->prepare("
        SELECT consumption_m3, daily_liters_per_person
        FROM water_logs
        WHERE household_id = :hid AND period_year = :y AND period_month = :m
        LIMIT 1
    ");
    $myStmt->execute([
        ':hid' => (int) $user['household_id'],
        ':y'   => $year,
        ':m'   => $month,
    ]);
    $myLog = $myStmt->fetch() ?: null;

    // Mahalle ortalaması
    $nStmt = $pdo->prepare("
        SELECT AVG(daily_liters_per_person) AS avg_daily, COUNT(*) AS count_logs
        FROM water_logs
        WHERE neighborhood_id = :nid AND period_year = :y AND period_month = :m
    ");
    $nStmt->execute([
        ':nid' => (int) $user['neighborhood_id'],
        ':y'   => $year,
        ':m'   => $month,
    ]);
    $neighborhoodAvg = $nStmt->fetch() ?: ['avg_daily' => null, 'count_logs' => 0];

    // Apartman ortalaması (eğer apartmana bağlıysa)
    $apartmentAvg = ['avg_daily' => null, 'count_logs' => 0];
    if (!empty($user['apartment_id'])) {
        $aStmt = $pdo->prepare("
            SELECT AVG(daily_liters_per_person) AS avg_daily, COUNT(*) AS count_logs
            FROM water_logs
            WHERE apartment_id = :aid AND period_year = :y AND period_month = :m
        ");
        $aStmt->execute([
            ':aid' => (int) $user['apartment_id'],
            ':y'   => $year,
            ':m'   => $month,
        ]);
        $apartmentAvg = $aStmt->fetch() ?: $apartmentAvg;
    }

    return [
        'period_label'     => turkish_month_name($month) . ' ' . $year,
        'my_log'           => $myLog,
        'neighborhood_avg' => $neighborhoodAvg['avg_daily'] !== null ? round((float) $neighborhoodAvg['avg_daily'], 1) : null,
        'neighborhood_cnt' => (int) ($neighborhoodAvg['count_logs'] ?? 0),
        'apartment_avg'    => $apartmentAvg['avg_daily'] !== null ? round((float) $apartmentAvg['avg_daily'], 1) : null,
        'apartment_cnt'    => (int) ($apartmentAvg['count_logs'] ?? 0),
    ];
}

/**
 * Tasarruf Alışkanlıkları Listesi ve Hanenin Seçimleri
 */
function get_saving_habits_with_status(int $householdId): array
{
    $stmt = db()->prepare("
        SELECT
            sh.*,
            CASE WHEN hh.household_id IS NOT NULL THEN 1 ELSE 0 END AS is_active
        FROM saving_habits sh
        LEFT JOIN household_habits hh
            ON hh.habit_id = sh.id AND hh.household_id = :hid
        ORDER BY sh.id ASC
    ");
    $stmt->execute([':hid' => $householdId]);
    return $stmt->fetchAll();
}

/**
 * Hanenin Tasarruf Alışkanlığını Açıp Kapatma
 */
function toggle_household_habit(int $householdId, int $habitId, int $userId = 0): int
{
    $pdo = db();
    $chk = $pdo->prepare("
        SELECT 1 FROM household_habits
        WHERE household_id = :hid AND habit_id = :hab
        LIMIT 1
    ");
    $chk->execute([':hid' => $householdId, ':hab' => $habitId]);

    if ($chk->fetchColumn()) {
        $del = $pdo->prepare("DELETE FROM household_habits WHERE household_id = :hid AND habit_id = :hab");
        $del->execute([':hid' => $householdId, ':hab' => $habitId]);
        return 0;
    } else {
        $ins = $pdo->prepare("INSERT INTO household_habits (household_id, habit_id) VALUES (:hid, :hab)");
        $ins->execute([':hid' => $householdId, ':hab' => $habitId]);

        if ($userId > 0 && function_exists('award_points')) {
            $hSt = $pdo->prepare("SELECT title FROM saving_habits WHERE id = :id LIMIT 1");
            $hSt->execute([':id' => $habitId]);
            $hTitle = $hSt->fetchColumn() ?: 'Tasarruf Alışkanlığı';
            return award_points(
                $userId,
                $householdId,
                'habit_adopt',
                40,
                '🛠️ Yeni alışkanlık: ' . $hTitle,
                'habit_' . $habitId
            );
        }
        return 0;
    }
}

/**
 * Ortak Kolektif Havuz / Debi Puan Göleti Durumunu Döndürür
 * Hedef kapasite: 500 m³. Doluluk yüzdesi: (golet_mevcut_su_m3 / golet_hedef_m3) * 100
 */
function get_collective_pond_state(): array
{
    if (function_exists('ensure_gamification_schema')) {
        ensure_gamification_schema();
    }

    $pdo = db();
    try {
        $pdo->exec("
            INSERT IGNORE INTO collective_pond (id, golet_mevcut_su_m3, golet_mevcut_litre, golet_toplam_puan, golet_hedef_m3)
            VALUES (1, 0.00, 0, 0, 500.00)
        ");

        $row = $pdo->query("SELECT * FROM collective_pond WHERE id = 1 LIMIT 1")->fetch();
        if (!$row) {
            $row = [
                'golet_mevcut_su_m3' => 0.0,
                'golet_mevcut_litre' => 0,
                'golet_toplam_puan'  => 0,
                'golet_hedef_m3'     => 500.0,
            ];
        }

        // Tablodaki toplamlarla senkronize tut
        $sumRow = $pdo->query("
            SELECT
                COALESCE(SUM(saved_m3), 0) AS sum_m3,
                COALESCE(SUM(saved_liters), 0) AS sum_liters,
                COALESCE(SUM(earned_points), 0) AS sum_pts
            FROM water_logs
        ")->fetch();

        $mevcutM3    = max((float) $row['golet_mevcut_su_m3'], (float) ($sumRow['sum_m3'] ?? 0));
        $mevcutLitre = max((int) $row['golet_mevcut_litre'], (int) ($sumRow['sum_liters'] ?? 0));
        $toplamPuan  = max((int) $row['golet_toplam_puan'], (int) ($sumRow['sum_pts'] ?? 0));
        $hedefM3     = max(1.0, (float) ($row['golet_hedef_m3'] ?? 500.0));
        $dolulukPct  = min(100.0, round(($mevcutM3 / $hedefM3) * 100, 1));

        return [
            'golet_mevcut_su_m3' => round($mevcutM3, 2),
            'golet_mevcut_litre' => $mevcutLitre,
            'golet_toplam_puan'  => $toplamPuan,
            'golet_hedef_m3'     => round($hedefM3, 2),
            'doluluk_yuzdesi'    => $dolulukPct,
        ];
    } catch (Throwable $e) {
        return [
            'golet_mevcut_su_m3' => 0.0,
            'golet_mevcut_litre' => 0,
            'golet_toplam_puan'  => 0,
            'golet_hedef_m3'     => 500.0,
            'doluluk_yuzdesi'    => 0.0,
        ];
    }
}

/**
 * Su Analizi Sayfası İçin Aylık/Yıllık m³ Zaman Çizelgesi ve Debi Puan Göleti Verileri
 * Yalnızca kullanıcının yüklediği gerçek fatura kayıtlarını (water_logs) kullanır; sahte ay/yıl üretmez.
 */
function get_household_water_analytics(array $user): array
{
    $householdId = (int) ($user['household_id'] ?? 0);
    $userId      = (int) ($user['id'] ?? 0);

    // Tek fatura veya geçmiş kayıtların tasarruf ve gölet toplamlarını gerçek verilerle senkronize et
    recalculate_household_water_savings($householdId, $userId);

    $rawHistory    = get_household_water_history($householdId);
    $chronological = array_reverse($rawHistory);
    $hasRealLogs   = !empty($chronological);

    $series = [];
    $slice  = array_slice($chronological, -12);
    foreach ($slice as $row) {
        $mName = turkish_month_name((int) $row['period_month']);
        $yr    = (int) $row['period_year'];
        $series[] = [
            'xlab'           => $mName . ' ' . $yr,
            'short_xlab'     => $mName,
            'period_label'   => $mName . ' ' . $yr,
            'period_year'    => $yr,
            'period_month'   => (int) $row['period_month'],
            'consumption_m3' => round((float) $row['consumption_m3'], 2),
            'daily_liters'   => round((float) $row['daily_liters_per_person'], 1),
        ];
    }

    // 1. Puanlama Yöntemi Hesabı (Yalnızca en az 2 gerçek fatura dönemi varsa karşılaştırma yapılır)
    $n               = count($series);
    $hasComparison   = ($n >= 2);
    $prevMonthLabel  = 'Önceki Dönem Faturası';
    $currMonthLabel  = 'Son Dönem Faturası';
    $prevMonthM3     = null;
    $currMonthM3     = null;
    $diffM3          = 0.0;
    $savedM3         = 0.0;
    $savedLiters     = 0;
    $savingPct       = 0.0;
    $earnedPoints    = 0;

    if ($n >= 2) {
        $prevItem       = $series[$n - 2];
        $currItem       = $series[$n - 1];
        $prevMonthLabel = $prevItem['period_label'] . ' Tüketimi';
        $currMonthLabel = $currItem['period_label'] . ' Tüketimi';
        $prevMonthM3    = (float) $prevItem['consumption_m3'];
        $currMonthM3    = (float) $currItem['consumption_m3'];
        $diffM3         = round($prevMonthM3 - $currMonthM3, 2);
        $savedM3        = $diffM3 > 0 ? $diffM3 : 0.0;
        $savedLiters    = $savedM3 > 0 ? (int) round($savedM3 * 1000) : 0;
        $savingPct      = ($prevMonthM3 > 0 && $savedM3 > 0) ? round(($savedM3 / $prevMonthM3) * 100, 1) : 0.0;
        $earnedPoints   = ($savingPct >= 10.0) ? 10 : 0;
    } elseif ($n === 1) {
        $currItem       = $series[0];
        $currMonthLabel = $currItem['period_label'] . ' Tüketimi';
        $currMonthM3    = (float) $currItem['consumption_m3'];
    }

    $pondState = get_collective_pond_state();

    if ($pondState['doluluk_yuzdesi'] >= 66.0 || $savedLiters >= 3000) {
        $pondStage = 'full';
    } elseif ($pondState['doluluk_yuzdesi'] >= 20.0 || $savedLiters >= 1000) {
        $pondStage = 'half';
    } else {
        $pondStage = 'dry';
    }

    // Yıllık (Yearly) Seri Hesaplaması — yalnızca gerçek kayıtlı yılları döndürür
    $yearlyStmt = db()->prepare("
        SELECT period_year, SUM(consumption_m3) AS total_m3, AVG(daily_liters_per_person) AS avg_daily, COUNT(*) AS bill_count
        FROM water_logs
        WHERE household_id = :hid
        GROUP BY period_year
        ORDER BY period_year ASC
        LIMIT 6
    ");
    $yearlyStmt->execute([':hid' => $householdId]);
    $yearlyRows = $yearlyStmt->fetchAll();

    $yearlySeries = [];
    foreach ($yearlyRows as $yRow) {
        $yr   = (string) $yRow['period_year'];
        $bCnt = (int) ($yRow['bill_count'] ?? 1);
        $yearlySeries[] = [
            'xlab'           => $yr . ' (' . $bCnt . ' fatura)',
            'period_label'   => $yr . ' Yılı (' . $bCnt . ' fatura)',
            'consumption_m3' => round((float) $yRow['total_m3'], 1),
            'daily_liters'   => round((float) $yRow['avg_daily'], 1),
        ];
    }

    // Kullanıcının güncel cüzdan puanını çek
    $walletPoints = (int) ($user['total_points'] ?? 0);
    if ($userId > 0) {
        try {
            $ptSt = db()->prepare("SELECT total_points FROM users WHERE id = :id LIMIT 1");
            $ptSt->execute([':id' => $userId]);
            $dbPts = $ptSt->fetchColumn();
            if ($dbPts !== false) {
                $walletPoints = (int) $dbPts;
            }
        } catch (Throwable $ignore) {
        }
    }

    return [
        'has_real_logs'      => $hasRealLogs,
        'has_comparison'     => $hasComparison,
        'bill_count'         => $n,
        'is_sample'          => false,
        'series'             => $series,
        'yearly_series'      => $yearlySeries,
        'prev_label'         => $prevMonthLabel,
        'curr_label'         => $currMonthLabel,
        'prev_m3'            => $prevMonthM3,
        'curr_m3'            => $currMonthM3,
        'diff_m3'            => $diffM3,
        'saved_m3'           => $savedM3,
        'saved_liters'       => $savedLiters,
        'saving_pct'         => $savingPct,
        'earned_points'      => $earnedPoints,
        'user_wallet_points' => $walletPoints,
        'collective_pond'    => $pondState,
        'pond_stage'         => $pondStage,
    ];
}


