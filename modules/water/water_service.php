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
    $shareOnFeed   = !empty($input['share_on_feed']);

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

    $pdo = db();

    $stmt = $pdo->prepare("
        INSERT INTO water_logs (
            household_id, user_id, neighborhood_id, apartment_id,
            period_year, period_month, consumption_m3,
            resident_count_snapshot, daily_liters_per_person,
            bill_amount, bill_image_path, provider_name, reading_days, notes
        ) VALUES (
            :hid, :uid, :nid, :aid,
            :y, :m, :m3,
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

    // İsteğe bağlı olarak sosyal akışta başarı paylaşımı oluştur
    if ($shareOnFeed) {
        $provTag  = $providerName !== '' ? " ({$providerName})" : '';
        $postText = "{$monthName} dönemi{$provTag} su faturamızı kaydettik: Toplam {$consumptionM3} m³ (Kişi başı günde ortalama {$dailyLitersPerPerson} L).";
        if ($notes !== '') {
            $postText .= " Notumuz: " . $notes;
        }

        $pStmt = $pdo->prepare("
            INSERT INTO posts (user_id, neighborhood_id, apartment_id, visibility, category, content)
            VALUES (:uid, :nid, :aid, 'public', 'achievement', :content)
        ");
        $pStmt->execute([
            ':uid'     => (int) $user['id'],
            ':nid'     => (int) $user['neighborhood_id'],
            ':aid'     => !empty($user['apartment_id']) ? (int) $user['apartment_id'] : null,
            ':content' => $postText,
        ]);
    }

    $pointsAwarded = 0;
    if (function_exists('award_points')) {
        $pointsAwarded += award_points(
            (int) $user['id'],
            (int) $user['household_id'],
            'water_log_entry',
            100,
            "📊 {$monthName} su sayacı verisini kaydettin!",
            "water_log_{$year}_{$month}"
        );

        if ($dailyLitersPerPerson <= 100.0) {
            $pointsAwarded += award_points(
                (int) $user['id'],
                (int) $user['household_id'],
                'water_saving_bonus',
                150,
                "🏆 {$monthName} döneminde 100L/gün tasarruf hedefini yakaladın!",
                "water_bonus_{$year}_{$month}"
            );
        }
    }

    return ['ok' => true, 'daily_liters' => $dailyLitersPerPerson, 'points_awarded' => $pointsAwarded];
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

