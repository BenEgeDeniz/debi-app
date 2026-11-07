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

    // 1. Veri Girişi: "Geçen Ayki Tüketim (m3)" alınır (Formdan girildiyse formdan, yoksa önceki ay kaydından)
    $prevInputRaw = trim((string) ($input['previous_consumption_m3'] ?? ''));
    $gecenAyM3    = $prevInputRaw !== '' ? (float) str_replace(',', '.', $prevInputRaw) : 0.0;

    if ($gecenAyM3 <= 0) {
        $prevM = $month === 1 ? 12 : $month - 1;
        $prevY = $month === 1 ? $year - 1 : $year;
        $prevStmt = $pdo->prepare("
            SELECT consumption_m3
            FROM water_logs
            WHERE household_id = :hid
              AND (period_year < :y1 OR (period_year = :y2 AND period_month < :m))
            ORDER BY period_year DESC, period_month DESC
            LIMIT 1
        ");
        $prevStmt->execute([
            ':hid' => (int) $user['household_id'],
            ':y1'  => $year,
            ':y2'  => $year,
            ':m'   => $month,
        ]);
        $foundPrev = $prevStmt->fetchColumn();
        if ($foundPrev !== false && (float) $foundPrev > 0) {
            $gecenAyM3 = (float) $foundPrev;
        }
    }

    // 2. Fark Hesabı: tasarruf_m3 = gecen_ay_m3 - bu_ay_m3
    $tasarrufM3      = 0.0;
    $kurtarilanLitre = 0;
    $tasarrufYuzdesi = 0.0;
    $earnedPoints    = 0;

    if ($gecenAyM3 > 0) {
        $farkM3 = round($gecenAyM3 - $consumptionM3, 2);
        if ($farkM3 > 0) {
            $tasarrufM3      = $farkM3;
            $kurtarilanLitre = (int) round($tasarrufM3 * 1000);
            $tasarrufYuzdesi = round(($tasarrufM3 / $gecenAyM3) * 100, 2);

            // 3. Puan (Damla) Dönüşümü: %10 ve üzeri tasarruf barajı geçildiyse sabit +10 Debi Puanı
            if ($tasarrufYuzdesi >= 10.0) {
                $earnedPoints = 10;
            }
        }
    }

    // Aynı dönem daha önce kaydedildiyse eski tasarruf ve puanı kontrol et (mükerrer gölet eklemesini önlemek için)
    $oldLogStmt = $pdo->prepare("
        SELECT saved_m3, saved_liters, earned_points
        FROM water_logs
        WHERE household_id = :hid AND period_year = :y AND period_month = :m
        LIMIT 1
    ");
    $oldLogStmt->execute([
        ':hid' => (int) $user['household_id'],
        ':y'   => $year,
        ':m'   => $month,
    ]);
    $oldLog = $oldLogStmt->fetch() ?: null;

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
            :prevm3, :savedm3, :savedl, :savepct, :pts,
            :rcount, :daily,
            :bill, :bimg, :prov, :days, :notes
        )
        ON DUPLICATE KEY UPDATE
            user_id                 = VALUES(user_id),
            consumption_m3          = VALUES(consumption_m3),
            previous_consumption_m3 = VALUES(previous_consumption_m3),
            saved_m3                = VALUES(saved_m3),
            saved_liters            = VALUES(saved_liters),
            saving_pct              = VALUES(saving_pct),
            earned_points           = VALUES(earned_points),
            resident_count_snapshot = VALUES(resident_count_snapshot),
            daily_liters_per_person = VALUES(daily_liters_per_person),
            bill_amount             = VALUES(bill_amount),
            bill_image_path         = COALESCE(VALUES(bill_image_path), bill_image_path),
            provider_name           = VALUES(provider_name),
            reading_days            = VALUES(reading_days),
            notes                   = VALUES(notes)
    ");

    $stmt->execute([
        ':hid'     => (int) $user['household_id'],
        ':uid'     => (int) $user['id'],
        ':nid'     => (int) $user['neighborhood_id'],
        ':aid'     => !empty($user['apartment_id']) ? (int) $user['apartment_id'] : null,
        ':y'       => $year,
        ':m'       => $month,
        ':m3'      => $consumptionM3,
        ':prevm3'  => $gecenAyM3 > 0 ? $gecenAyM3 : null,
        ':savedm3' => $tasarrufM3,
        ':savedl'  => $kurtarilanLitre,
        ':savepct' => $tasarrufYuzdesi,
        ':pts'     => $earnedPoints,
        ':rcount'  => $residentCount,
        ':daily'   => $dailyLitersPerPerson,
        ':bill'    => $billVal,
        ':bimg'    => $billImagePath !== '' ? $billImagePath : null,
        ':prov'    => $providerName !== '' ? $providerName : null,
        ':days'    => $readingDays,
        ':notes'   => $notes !== '' ? $notes : null,
    ]);

    $monthName = turkish_month_name($month) . ' ' . $year;

    // 4. Gölette Birikme (Kolektif Havuz) & Kullanıcı Cüzdanına Puan Aktarımı
    $deltaM3     = $tasarrufM3 - (float) ($oldLog['saved_m3'] ?? 0);
    $deltaLiters = $kurtarilanLitre - (int) ($oldLog['saved_liters'] ?? 0);
    $deltaPoints = $earnedPoints - (int) ($oldLog['earned_points'] ?? 0);

    $pdo->exec("
        INSERT IGNORE INTO collective_pond (id, golet_mevcut_su_m3, golet_mevcut_litre, golet_toplam_puan, golet_hedef_m3)
        VALUES (1, 0.00, 0, 0, 500.00)
    ");

    $updPond = $pdo->prepare("
        UPDATE collective_pond
        SET golet_mevcut_su_m3 = GREATEST(0, golet_mevcut_su_m3 + :dm3),
            golet_mevcut_litre = GREATEST(0, CAST(golet_mevcut_litre AS SIGNED) + :dlit),
            golet_toplam_puan  = GREATEST(0, CAST(golet_toplam_puan AS SIGNED) + :dpts)
        WHERE id = 1
    ");
    $updPond->execute([
        ':dm3'  => $deltaM3,
        ':dlit' => $deltaLiters,
        ':dpts' => $deltaPoints,
    ]);

    $pointsAwarded = 0;
    if ($earnedPoints > 0 && function_exists('award_points')) {
        $pointsAwarded = award_points(
            (int) $user['id'],
            (int) $user['household_id'],
            'bill_saving_10pct',
            $earnedPoints,
            "💧 {$monthName} faturasında %{$tasarrufYuzdesi} ({$kurtarilanLitre} L) su tasarrufu ile +{$earnedPoints} Debi Puanı!",
            "bill_saving_{$year}_{$month}"
        );
    }

    // Seçilen akışta (Arkadaşlar / Apartman / Genel Debi) fatura paylaşımı oluştur
    if ($feedScope !== 'none') {
        $provTag  = $providerName !== '' ? " ({$providerName})" : '';
        $postText = "{$monthName} dönemi{$provTag} su faturamızı kaydettik: {$consumptionM3} m³.";
        if ($kurtarilanLitre > 0) {
            $postText .= " Önceki aya göre {$kurtarilanLitre} L (%{$tasarrufYuzdesi}) su kurtararak Debi Puan Göleti'ne katkıda bulunduk!";
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
 */
function get_household_water_analytics(array $user): array
{
    $rawHistory = get_household_water_history((int) ($user['household_id'] ?? 0));
    $chronological = array_reverse($rawHistory);
    $hasRealLogs = !empty($chronological);
    $isSample = false;

    $series = [];
    $count = count($chronological);

    if ($count >= 2) {
        $slice = array_slice($chronological, -6);
        foreach ($slice as $row) {
            $mName = turkish_month_name((int) $row['period_month']);
            $series[] = [
                'xlab'            => $mName,
                'period_label'    => $mName . ' ' . $row['period_year'],
                'consumption_m3'  => round((float) $row['consumption_m3'], 2),
                'daily_liters'    => round((float) $row['daily_liters_per_person'], 1),
            ];
        }
    } elseif ($count === 1) {
        $only = $chronological[0];
        $curM = (int) $only['period_month'];
        $curY = (int) $only['period_year'];
        $prevM = $curM === 1 ? 12 : $curM - 1;
        $prevY = $curM === 1 ? $curY - 1 : $curY;
        $curM3 = round((float) $only['consumption_m3'], 2);
        $prevM3 = !empty($only['previous_consumption_m3']) && (float) $only['previous_consumption_m3'] > 0
            ? round((float) $only['previous_consumption_m3'], 2)
            : round($curM3 + 2.2, 2);
        $isSample = empty($only['previous_consumption_m3']);

        $series[] = [
            'xlab'           => turkish_month_name($prevM),
            'period_label'   => turkish_month_name($prevM) . ' ' . $prevY,
            'consumption_m3' => $prevM3,
            'daily_liters'   => round((float) $only['daily_liters_per_person'] * ($prevM3 / max(0.5, $curM3)), 1),
        ];
        $series[] = [
            'xlab'           => turkish_month_name($curM),
            'period_label'   => turkish_month_name($curM) . ' ' . $curY,
            'consumption_m3' => $curM3,
            'daily_liters'   => round((float) $only['daily_liters_per_person'], 1),
        ];
    } else {
        $isSample = true;
        $nowM = (int) date('n');
        $nowY = (int) date('Y');
        $sampleM3 = [14.8, 13.9, 13.2, 10.4];
        for ($i = 3; $i >= 0; $i--) {
            $ts = mktime(0, 0, 0, $nowM - $i, 1, $nowY);
            $m = (int) date('n', $ts);
            $y = (int) date('Y', $ts);
            $mName = turkish_month_name($m);
            $m3Val = $sampleM3[3 - $i];
            $series[] = [
                'xlab'           => $mName,
                'period_label'   => $mName . ' ' . $y,
                'consumption_m3' => $m3Val,
                'daily_liters'   => round(($m3Val * 1000) / (30 * max(1, (int) ($user['resident_count'] ?? 3))), 1),
            ];
        }
    }

    // 1. Puanlama Yöntemi Hesabı (Fatura Farkı -> %10 Barajlı Puan ve Gölet Birikimi)
    $n = count($series);
    $prevMonthM3     = (float) $series[$n - 2]['consumption_m3'];
    $currMonthM3     = (float) $series[$n - 1]['consumption_m3'];
    $diffM3          = round($prevMonthM3 - $currMonthM3, 2);
    $savedM3         = $diffM3 > 0 ? $diffM3 : 0.0;
    $savedLiters     = $savedM3 > 0 ? (int) round($savedM3 * 1000) : 0;
    $savingPct       = ($prevMonthM3 > 0 && $savedM3 > 0) ? round(($savedM3 / $prevMonthM3) * 100, 1) : 0.0;
    $earnedPoints    = ($savingPct >= 10.0) ? 10 : 0;

    $pondState = get_collective_pond_state();
    // Eğer veritabanında henüz kayıt yoksa örnek tasarrufu gölet başlangıç değerinde göster
    if (!$hasRealLogs && $pondState['golet_mevcut_su_m3'] <= 0) {
        $pondState['golet_mevcut_su_m3'] = $savedM3;
        $pondState['golet_mevcut_litre'] = $savedLiters;
        $pondState['golet_toplam_puan']  = $earnedPoints;
        $pondState['doluluk_yuzdesi']    = round(($savedM3 / $pondState['golet_hedef_m3']) * 100, 1);
    }

    if ($pondState['doluluk_yuzdesi'] >= 66.0 || $savedLiters >= 3000) {
        $pondStage = 'full';
    } elseif ($pondState['doluluk_yuzdesi'] >= 20.0 || $savedLiters >= 1000) {
        $pondStage = 'half';
    } else {
        $pondStage = 'dry';
    }

    // Yıllık (Yearly) Seri Hesaplaması
    $yearlyStmt = db()->prepare("
        SELECT period_year, SUM(consumption_m3) AS total_m3, AVG(daily_liters_per_person) AS avg_daily
        FROM water_logs
        WHERE household_id = :hid
        GROUP BY period_year
        ORDER BY period_year ASC
        LIMIT 6
    ");
    $yearlyStmt->execute([':hid' => (int) ($user['household_id'] ?? 0)]);
    $yearlyRows = $yearlyStmt->fetchAll();

    $yearlySeries = [];
    if (count($yearlyRows) >= 2) {
        foreach ($yearlyRows as $yRow) {
            $yr = (string) $yRow['period_year'];
            $yearlySeries[] = [
                'xlab'           => $yr,
                'period_label'   => $yr . ' Yılı',
                'consumption_m3' => round((float) $yRow['total_m3'], 1),
                'daily_liters'   => round((float) $yRow['avg_daily'], 1),
            ];
        }
    } else {
        $curYear = (int) date('Y');
        $baseYearly = max(90.0, round($currMonthM3 * 12, 1));
        $sampleYears = [
            $curYear - 3 => round($baseYearly + 18.5, 1),
            $curYear - 2 => round($baseYearly + 11.2, 1),
            $curYear - 1 => round($baseYearly + 4.8, 1),
            $curYear     => $baseYearly,
        ];
        foreach ($sampleYears as $yr => $ym3) {
            $yearlySeries[] = [
                'xlab'           => (string) $yr,
                'period_label'   => $yr . ' Yılı',
                'consumption_m3' => $ym3,
                'daily_liters'   => round(($ym3 * 1000) / (365 * max(1, (int) ($user['resident_count'] ?? 3))), 1),
            ];
        }
    }

    return [
        'has_real_logs'   => $hasRealLogs,
        'is_sample'       => $isSample,
        'series'          => $series,
        'yearly_series'   => $yearlySeries,
        'prev_m3'         => $prevMonthM3,
        'curr_m3'         => $currMonthM3,
        'diff_m3'         => $diffM3,
        'saved_m3'        => $savedM3,
        'saved_liters'    => $savedLiters,
        'saving_pct'      => $savingPct,
        'earned_points'   => $earnedPoints,
        'collective_pond' => $pondState,
        'pond_stage'      => $pondStage,
    ];
}


