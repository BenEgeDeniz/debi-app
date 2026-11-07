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
    $aiVerified           = !empty($input['ai_verified']) ? 1 : 0;
    $aiUsed               = !empty($input['ai_used']);
    $reviewStatus         = (!$aiUsed || $aiVerified === 1) ? 'approved' : 'pending';

    $pdo = db();

    $stmt = $pdo->prepare("
        INSERT INTO water_logs (
            household_id, user_id, neighborhood_id, apartment_id,
            period_year, period_month, consumption_m3,
            previous_consumption_m3, saved_m3, saved_liters, saving_pct, earned_points, ai_verified,
            resident_count_snapshot, daily_liters_per_person,
            bill_amount, bill_image_path, provider_name, reading_days, notes, review_status,
            reviewed_by_user_id, reviewed_at, review_note
        ) VALUES (
            :hid, :uid, :nid, :aid,
            :y, :m, :m3,
            NULL, 0.00, 0, 0.00, 0, :aiver,
            :rcount, :daily,
            :bill, :bimg, :prov, :days, :notes, :review_status,
            NULL, NULL, NULL
        )
        ON DUPLICATE KEY UPDATE
            user_id                 = VALUES(user_id),
            consumption_m3          = VALUES(consumption_m3),
            ai_verified             = GREATEST(ai_verified, VALUES(ai_verified)),
            resident_count_snapshot = VALUES(resident_count_snapshot),
            daily_liters_per_person = VALUES(daily_liters_per_person),
            bill_amount             = VALUES(bill_amount),
            bill_image_path         = COALESCE(VALUES(bill_image_path), bill_image_path),
            provider_name           = VALUES(provider_name),
            reading_days            = VALUES(reading_days),
            notes                   = VALUES(notes),
            review_status           = VALUES(review_status),
            reviewed_by_user_id     = NULL,
            reviewed_at             = NULL,
            review_note             = NULL
    ");

    $stmt->execute([
        ':hid'    => (int) $user['household_id'],
        ':uid'    => (int) $user['id'],
        ':nid'    => (int) $user['neighborhood_id'],
        ':aid'    => !empty($user['apartment_id']) ? (int) $user['apartment_id'] : null,
        ':y'      => $year,
        ':m'      => $month,
        ':m3'     => $consumptionM3,
        ':aiver'  => $aiVerified,
        ':rcount' => $residentCount,
        ':daily'  => $dailyLitersPerPerson,
        ':bill'   => $billVal,
        ':bimg'   => $billImagePath !== '' ? $billImagePath : null,
        ':prov'   => $providerName !== '' ? $providerName : null,
        ':days'   => $readingDays,
        ':notes'  => $notes !== '' ? $notes : null,
        ':review_status' => $reviewStatus,
    ]);

    $monthName = turkish_month_name($month) . ' ' . $year;

    // Kronolojik olarak gerçek faturalar arasındaki tasarruf farkını ve daire göletini güncelle
    $calc = recalculate_household_water_savings((int) $user['household_id'], (int) $user['id'], $year, $month);
    $gecenAyM3       = $calc['previous_m3'];
    $tasarrufM3      = $calc['saved_m3'];
    $kurtarilanLitre = $calc['saved_liters'];
    $tasarrufYuzdesi = $calc['saving_pct'];
    $earnedPoints    = $calc['points_awarded'];
    $dropReasons     = $calc['drop_reasons'] ?? [];

    // Seçilen akışta (Arkadaşlar / Apartman / Genel Debi) fatura paylaşımı oluştur
    if ($feedScope !== 'none') {
        $provTag  = $providerName !== '' ? " ({$providerName})" : '';
        $postText = "💧 {$monthName}{$provTag}: {$consumptionM3} m³ ({$dailyLitersPerPerson} L/gün).";
        if ($kurtarilanLitre > 0) {
            $postText .= " {$kurtarilanLitre} L (%{$tasarrufYuzdesi}) tasarruf · +{$earnedPoints} puan!";
        } elseif ($earnedPoints > 0) {
            $postText .= " +{$earnedPoints} puan!";
        }
        if ($notes !== '') {
            $postText .= " — " . $notes;
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
        'period_label'     => $monthName,
        'consumption_m3'   => $consumptionM3,
        'daily_liters'     => $dailyLitersPerPerson,
        'previous_m3'      => $gecenAyM3,
        'saved_m3'         => $tasarrufM3,
        'saved_liters'     => $kurtarilanLitre,
        'saving_pct'       => $tasarrufYuzdesi,
        'points_awarded'   => $earnedPoints,
        'ai_verified'      => $aiVerified === 1,
        'drop_reasons'     => $dropReasons,
    ];
}

/**
 * Hanenin yüklediği gerçek fatura kayıtlarını kronolojik sırayla karşılaştırarak
 * Fatura tasarruf metriklerini, puan geçmişini ve ortak hane bakiyesini senkronize eder.
 */
function recalculate_household_water_savings(int $householdId, int $userId = 0, ?int $targetYear = null, ?int $targetMonth = null): array
{
    $emptyResult = [
        'previous_m3'    => null,
        'saved_m3'       => 0.0,
        'saved_liters'   => 0,
        'saving_pct'     => 0.0,
        'points_awarded' => 0,
        'drop_reasons'   => [],
    ];

    if ($householdId <= 0) {
        return $emptyResult;
    }

    try {
        $pdo = db();
        $typeStmt = $pdo->prepare("SELECT housing_type FROM households WHERE id = :hid LIMIT 1");
        $typeStmt->execute([':hid' => $householdId]);
        $housingType = (string) $typeStmt->fetchColumn();
        if (!in_array($housingType, ['apartment_unit', 'detached_house'], true)) {
            $pdo->prepare("UPDATE water_logs SET earned_points = 0 WHERE household_id = :hid")
                ->execute([':hid' => $householdId]);
            return $emptyResult;
        }

        $stmt = $pdo->prepare("
            SELECT id, user_id, period_year, period_month, consumption_m3, created_at,
                   previous_consumption_m3, saved_m3, saved_liters, saving_pct, earned_points, ai_verified,
                   review_status, points_credited
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

        $matchedResult    = null;
        $latestResult     = $emptyResult;
        $totalRows        = count($rows);
        $invoicePointRows = [];
        $rowsByPeriod = [];
        foreach ($rows as $periodRow) {
            if (($periodRow['review_status'] ?? 'approved') === 'approved') {
                $periodKey = (int) $periodRow['period_year'] . '-' . str_pad((int) $periodRow['period_month'], 2, '0', STR_PAD_LEFT);
                $rowsByPeriod[$periodKey] = round((float) $periodRow['consumption_m3'], 2);
            }
        }

        for ($i = 0; $i < $totalRows; $i++) {
            $cur        = $rows[$i];
            $curM3      = round((float) $cur['consumption_m3'], 2);
            $isApproved = ($cur['review_status'] ?? 'approved') === 'approved';
            $pointsAlreadyCredited = (int) ($cur['points_credited'] ?? 0) === 1;

            $prevM3       = null;
            $savedM3      = 0.0;
            $savedLiters  = 0;
            $savingPct    = 0.0;
            $earnedPoints = 0;
            $dropReasons  = [];

            // İnceleme bekleyen veya reddedilen faturalar puan kazanamaz.
            $previousPeriod = (int) $cur['period_month'] === 1
                ? ((int) $cur['period_year'] - 1) . '-12'
                : (int) $cur['period_year'] . '-' . str_pad((int) $cur['period_month'] - 1, 2, '0', STR_PAD_LEFT);
            $nextPeriod = (int) $cur['period_month'] === 12
                ? ((int) $cur['period_year'] + 1) . '-01'
                : (int) $cur['period_year'] . '-' . str_pad((int) $cur['period_month'] + 1, 2, '0', STR_PAD_LEFT);
            $comparisonUsesPrevious = $isApproved && isset($rowsByPeriod[$previousPeriod]);
            $comparisonM3 = $comparisonUsesPrevious
                ? $rowsByPeriod[$previousPeriod]
                : null;
            $nextM3 = $isApproved && isset($rowsByPeriod[$nextPeriod])
                ? $rowsByPeriod[$nextPeriod]
                : null;
            if ($comparisonM3 === null && $nextM3 !== null) {
                // Geçmişten yüklenen kayıtta yalnızca gelecek komşu varsa
                // tüketim farkının yönü tersinden değerlendirilir.
                $comparisonM3 = $nextM3;
            }
            if ($comparisonM3 !== null) {
                $prevM3 = $comparisonM3;
                if ($comparisonM3 > 0) {
                    $diff = $comparisonUsesPrevious
                        ? round($comparisonM3 - $curM3, 2)
                        : round($curM3 - $comparisonM3, 2);
                    if ($diff > 0) {
                        $savedM3      = $diff;
                        $savedLiters  = (int) round($savedM3 * 1000);
                        $savingPct    = round(($savedM3 / $comparisonM3) * 100, 2);
                        $earnedPoints = max(1, (int) round($savedM3 * 10));
                        $dropReasons[] = '📉 ' . number_format($savedM3, 2, ',', '.') . ' ton azalma · +' . $earnedPoints . ' puan';
                    } elseif ($diff === 0.0) {
                        $earnedPoints = 10;
                        $dropReasons[] = '↔️ Tüketim aynı kaldı · +10 puan';
                    } else {
                        $earnedPoints = 2;
                        $dropReasons[] = '📈 Tüketim arttı · fatura yükleme +2 puan';
                    }
                } else {
                    $earnedPoints = 10;
                    $dropReasons[] = '✨ İlk karşılaştırılabilir fatura · +10 puan';
                }
            } elseif ($isApproved) {
                $earnedPoints = 10;
                $dropReasons[] = '✨ İlk fatura yükleme · +10 puan';
            } else {
                $dropReasons[] = '⏳ Fatura değerleri admin incelemesi bekliyor; henüz puan verilmedi.';
            }

            $oldPrev = $cur['previous_consumption_m3'] !== null ? round((float) $cur['previous_consumption_m3'], 2) : null;
            if (
                $oldPrev !== $prevM3 ||
                round((float) ($cur['saved_m3'] ?? 0), 2) !== $savedM3 ||
                (int) ($cur['saved_liters'] ?? 0) !== $savedLiters ||
                round((float) ($cur['saving_pct'] ?? 0), 2) !== $savingPct ||
                max((int) ($cur['earned_points'] ?? 0), $earnedPoints) !== (int) ($cur['earned_points'] ?? 0)
            ) {
                $updStmt->execute([
                    ':prevm3'  => $prevM3,
                    ':savedm3' => $savedM3,
                    ':savedl'  => $savedLiters,
                    ':savepct' => $savingPct,
                    ':pts'     => max((int) $cur['earned_points'], $earnedPoints),
                    ':id'      => (int) $cur['id'],
                ]);
            }

            $displayPoints = max((int) ($cur['earned_points'] ?? 0), $earnedPoints);
            $pointsToCredit = max(0, $earnedPoints - (int) ($cur['earned_points'] ?? 0));
            if ($pointsToCredit > 0) {
                $targetUid = $userId > 0 ? $userId : (int) ($cur['user_id'] ?? 0);
                if ($targetUid > 0) {
                    $mLabel = turkish_month_name((int) $cur['period_month']) . ' ' . (int) $cur['period_year'];
                    $invoicePointRows[] = [
                        'log_id'  => (int) $cur['id'],
                        'points_credited' => $pointsAlreadyCredited,
                        'user_id' => $targetUid,
                        'year'    => (int) $cur['period_year'],
                        'month'   => (int) $cur['period_month'],
                        'points'  => $pointsToCredit,
                        'label'   => "💧 {$mLabel} faturasından ek +{$pointsToCredit} puan",
                    ];
                }
            }

            $rowResult = [
                'previous_m3'    => $prevM3,
                'saved_m3'       => $savedM3,
                'saved_liters'   => $savedLiters,
                'saving_pct'     => $savingPct,
                'points_awarded' => $displayPoints,
                'drop_reasons'   => $dropReasons,
            ];
            $latestResult = $rowResult;
            if ($targetYear !== null && $targetMonth !== null && (int) $cur['period_year'] === $targetYear && (int) $cur['period_month'] === $targetMonth) {
                $matchedResult = $rowResult;
            }
        }

        $pdo->beginTransaction();
        // Puan kararı yalnızca water_logs.points_credited ve hane bakiyesiyle
        // verilir. point_logs yalnızca sonradan görüntülenebilecek audit kaydıdır.
        $creditMarkerStmt = $pdo->prepare("
            UPDATE water_logs
            SET points_credited = 1
            WHERE id = :id AND points_credited = 0
        ");
        $creditBalanceStmt = $pdo->prepare("
            UPDATE households
            SET total_points = total_points + :points
            WHERE id = :hid
        ");
        $insertLogStmt = $pdo->prepare("
            INSERT INTO point_logs (
                user_id, household_id, action_code, points, description, ref_key
            ) VALUES (
                :uid, :hid, 'bill_drops_earned', :points, :description, :ref_key
            )
        ");
        foreach ($invoicePointRows as $pointRow) {
            $refKey = 'bill_drops_log_' . $pointRow['log_id'];
            $creditMarkerStmt->execute([':id' => $pointRow['log_id']]);
            if ($creditMarkerStmt->rowCount() !== 1 && !$pointRow['points_credited']) {
                continue;
            }
            $creditBalanceStmt->execute([
                ':points' => $pointRow['points'],
                ':hid' => $householdId,
            ]);
            $insertLogStmt->execute([
                ':uid'         => $pointRow['user_id'],
                ':hid'         => $householdId,
                ':points'      => $pointRow['points'],
                ':description' => $pointRow['label'],
                ':ref_key'     => $refKey,
            ]);
        }

        $pdo->commit();

        return $matchedResult ?? $latestResult;
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
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

function get_pending_water_log_reviews(): array
{
    $stmt = db()->query("
        SELECT w.*, u.full_name, h.unit_label
        FROM water_logs w
        INNER JOIN users u ON u.id = w.user_id
        INNER JOIN households h ON h.id = w.household_id
        WHERE w.review_status = 'pending'
        ORDER BY w.created_at ASC
    ");
    return $stmt->fetchAll();
}

function review_water_log(array $admin, int $logId, array $input): array
{
    if (empty($admin['is_admin'])) {
        return ['ok' => false, 'error' => 'Bu işlem için admin yetkisi gerekiyor.'];
    }

    $stmt = db()->prepare("SELECT * FROM water_logs WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $logId]);
    $log = $stmt->fetch();
    if (!$log || $log['review_status'] !== 'pending') {
        return ['ok' => false, 'error' => 'İncelenecek fatura bulunamadı.'];
    }

    $consumption = (float) str_replace(',', '.', trim((string) ($input['consumption_m3'] ?? $log['consumption_m3'])));
    $readingDays = max(1, min(90, (int) ($input['reading_days'] ?? $log['reading_days'])));
    if ($consumption <= 0 || $consumption > 500) {
        return ['ok' => false, 'error' => 'Geçerli bir tüketim değeri girin.'];
    }

    $residentStmt = db()->prepare("SELECT resident_count FROM households WHERE id = :hid LIMIT 1");
    $residentStmt->execute([':hid' => (int) $log['household_id']]);
    $residentCount = max(1, (int) $residentStmt->fetchColumn());
    $daily = round(($consumption * 1000) / ($readingDays * $residentCount), 1);
    $status = ($input['decision'] ?? '') === 'reject' ? 'rejected' : 'approved';

    db()->prepare("
        UPDATE water_logs
        SET consumption_m3 = :m3, reading_days = :days, daily_liters_per_person = :daily,
            review_status = :status, reviewed_by_user_id = :admin_id,
            reviewed_at = NOW(), review_note = :note
        WHERE id = :id
    ")->execute([
        ':m3' => $consumption,
        ':days' => $readingDays,
        ':daily' => $daily,
        ':status' => $status,
        ':admin_id' => (int) $admin['id'],
        ':note' => trim((string) ($input['review_note'] ?? '')) ?: null,
        ':id' => $logId,
    ]);

    if ($status === 'approved') {
        recalculate_household_water_savings(
            (int) $log['household_id'],
            (int) $log['user_id'],
            (int) $log['period_year'],
            (int) $log['period_month']
        );
    }

    return [
        'ok' => true,
        'message' => $status === 'approved'
            ? 'Fatura onaylandı ve puanlar yeniden hesaplandı.'
            : 'Fatura reddedildi; puan verilmedi.',
    ];
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

        return 0;
    }
}

/**
 * Ortak hane puanı ve fatura tasarrufu özetini döndürür.
 */
function get_household_pond_state(int $householdId): array
{
    $defaultState = [
        'household_id'       => $householdId,
        'total_drops'        => 0,
        'target_drops'       => 100,
        'saved_m3'           => 0.0,
        'saved_liters'       => 0,
        'doluluk_yuzdesi'    => 0.0,
        'stage'              => 'dry',
        'stage_title'        => 'Kurumuş',
        'next_stage_hint'    => 'Sonraki seviye: 30 💧 kaldı',
        'golet_mevcut_su_m3' => 0.0,
        'golet_mevcut_litre' => 0,
        'golet_toplam_puan'  => 0,
        'golet_hedef_m3'     => 100.0,
    ];

    if ($householdId <= 0) {
        return $defaultState;
    }

    try {
        $typeStmt = db()->prepare("SELECT housing_type FROM households WHERE id = :hid LIMIT 1");
        $typeStmt->execute([':hid' => $householdId]);
        if (!in_array($typeStmt->fetchColumn(), ['apartment_unit', 'detached_house'], true)) {
            return $defaultState;
        }

        // Fatura istatistiklerini ve ortak bakiyeyi senkronize et
        recalculate_household_water_savings($householdId);

        $pdo = db();
        $statsStmt = $pdo->prepare("
            SELECT
                total_points,
                COALESCE(SUM(saved_m3), 0) AS saved_m3,
                COALESCE(SUM(saved_liters), 0) AS saved_liters
            FROM households h
            LEFT JOIN water_logs wl ON wl.household_id = h.id
            WHERE h.id = :hid
            GROUP BY h.id, h.total_points
        ");
        $statsStmt->execute([':hid' => $householdId]);
        $stats = $statsStmt->fetch() ?: [];

        $totalDrops  = (int) ($stats['total_points'] ?? 0);
        $targetDrops = 100;
        $savedM3     = round((float) ($stats['saved_m3'] ?? 0.0), 2);
        $savedLiters = (int) ($stats['saved_liters'] ?? 0);
        $dolulukPct  = min(100.0, round(($totalDrops / $targetDrops) * 100, 1));

        if ($totalDrops >= 70) {
            $stage          = 'full';
            $stageTitle     = 'Tam Dolu';
            $nextStageHint  = 'Tam dolu 🌟';
        } elseif ($totalDrops >= 30) {
            $stage          = 'half';
            $stageTitle     = 'Yarı Dolu';
            $remaining      = max(1, 70 - $totalDrops);
            $nextStageHint  = "Tam doluya {$remaining} 💧 kaldı";
        } else {
            $stage          = 'dry';
            $stageTitle     = 'Kurumuş';
            $remaining      = max(1, 30 - $totalDrops);
            $nextStageHint  = "Yarı doluya {$remaining} 💧 kaldı";
        }

        return [
            'household_id'       => $householdId,
            'total_drops'        => $totalDrops,
            'target_drops'       => $targetDrops,
            'saved_m3'           => $savedM3,
            'saved_liters'       => $savedLiters,
            'doluluk_yuzdesi'    => $dolulukPct,
            'stage'              => $stage,
            'stage_title'        => $stageTitle,
            'next_stage_hint'    => $nextStageHint,
            'golet_mevcut_su_m3' => $savedM3,
            'golet_mevcut_litre' => $savedLiters,
            'golet_toplam_puan'  => $totalDrops,
            'golet_hedef_m3'     => (float) $targetDrops,
        ];
    } catch (Throwable $e) {
        return $defaultState;
    }
}

/**
 * Su Analizi Sayfası İçin Aylık/Yıllık m³ Zaman Çizelgesi ve Daire Göleti Verileri
 * Yalnızca kullanıcının yüklediği gerçek fatura kayıtlarını (water_logs) kullanır; sahte ay/yıl üretmez.
 */
function get_household_water_analytics(array $user): array
{
    $householdId = (int) ($user['household_id'] ?? 0);
    $userId      = (int) ($user['id'] ?? 0);

    // Dairenin gerçek fatura tasarruf ve damla toplamlarını senkronize et
    $pondState = get_household_pond_state($householdId);

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
            'earned_points'  => (int) ($row['earned_points'] ?? 0),
            'ai_verified'    => (int) ($row['ai_verified'] ?? 0) === 1,
        ];
    }

    // Yalnızca en az 2 gerçek fatura dönemi varsa karşılaştırma yapılır
    $n               = count($series);
    $hasComparison   = ($n >= 2);
    $prevMonthLabel  = 'Önceki Fatura';
    $currMonthLabel  = 'Son Fatura';
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
        $prevMonthLabel = $prevItem['period_label'];
        $currMonthLabel = $currItem['period_label'];
        $prevMonthM3    = (float) $prevItem['consumption_m3'];
        $currMonthM3    = (float) $currItem['consumption_m3'];
        $diffM3         = round($prevMonthM3 - $currMonthM3, 2);
        $savedM3        = $diffM3 > 0 ? $diffM3 : 0.0;
        $savedLiters    = $savedM3 > 0 ? (int) round($savedM3 * 1000) : 0;
        $savingPct      = ($prevMonthM3 > 0 && $savedM3 > 0) ? round(($savedM3 / $prevMonthM3) * 100, 1) : 0.0;
        $earnedPoints   = (int) ($currItem['earned_points'] ?? 0);
    } elseif ($n === 1) {
        $currItem       = $series[0];
        $currMonthLabel = $currItem['period_label'];
        $currMonthM3    = (float) $currItem['consumption_m3'];
        $earnedPoints   = (int) ($currItem['earned_points'] ?? 0);
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
        'user_wallet_points' => (int) $pondState['total_drops'],
        'household_pond'     => $pondState,
        'pond_stage'         => $pondState['stage'],
    ];
}
