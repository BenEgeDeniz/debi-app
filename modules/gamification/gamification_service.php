<?php
/**
 * DEBI - Oyunlaştırma (Damla Puanı / DP, Seviye, Günlük Görevler ve Rozetler) Modülü
 */

/**
 * Tabloların varlığını kontrol eder, henüz 005 veya 006 SQL yüklenmemişse hata vermemesi için güvenli kurulum yapar.
 */
function ensure_gamification_schema(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $pdo = db();

        // 1. 005_post_images_and_alerts.sql kontrolü (neighborhood_alerts tablosu ve posts.image_path sütunu)
        $hasAlerts = (bool) $pdo->query("SHOW TABLES LIKE 'neighborhood_alerts'")->fetchColumn();
        if (!$hasAlerts) {
            $sql005 = __DIR__ . '/../../sql-dum/005_post_images_and_alerts.sql';
            if (file_exists($sql005)) {
                run_sql_migration_file($pdo, $sql005);
            }
        }

        // 2. 006_gamification_points.sql kontrolü (point_logs tablosu ve hane puanı)
        $hasPointLogs  = (bool) $pdo->query("SHOW TABLES LIKE 'point_logs'")->fetchColumn();
        $hasHouseholdPoints = (bool) $pdo->query("SHOW COLUMNS FROM `households` LIKE 'total_points'")->fetchColumn();

        if (!$hasPointLogs || !$hasHouseholdPoints) {
            $sql006 = __DIR__ . '/../../sql-dum/006_gamification_points.sql';
            if (file_exists($sql006)) {
                run_sql_migration_file($pdo, $sql006);
            }
        }

        // 3. 007_bill_images.sql kontrolü (water_logs.bill_image_path sütunu)
        $hasBillImage = (bool) $pdo->query("SHOW COLUMNS FROM `water_logs` LIKE 'bill_image_path'")->fetchColumn();
        if (!$hasBillImage) {
            $sql007 = __DIR__ . '/../../sql-dum/007_bill_images.sql';
            if (file_exists($sql007)) {
                run_sql_migration_file($pdo, $sql007);
            }
        }

        // 4. 008_friendships_and_timeline.sql kontrolü (friendships tablosu ve posts.visibility ENUM güncellemesi)
        $hasFriendships = (bool) $pdo->query("SHOW TABLES LIKE 'friendships'")->fetchColumn();
        if (!$hasFriendships) {
            $sql008 = __DIR__ . '/../../sql-dum/008_friendships_and_timeline.sql';
            if (file_exists($sql008)) {
                run_sql_migration_file($pdo, $sql008);
            }
        }

        // 5. 009_profile_and_cover_photos.sql kontrolü (users.avatar_photo ve users.cover_photo sütunları)
        $hasAvatarPhoto = (bool) $pdo->query("SHOW COLUMNS FROM `users` LIKE 'avatar_photo'")->fetchColumn();
        if (!$hasAvatarPhoto) {
            $sql009 = __DIR__ . '/../../sql-dum/009_profile_and_cover_photos.sql';
            if (file_exists($sql009)) {
                run_sql_migration_file($pdo, $sql009);
            }
        }

        // 6. 010_household_unit_manager_and_residents.sql kontrolü (households.owner_user_id ve unit_invite_code)
        $hasUnitInvite = (bool) $pdo->query("SHOW COLUMNS FROM `households` LIKE 'unit_invite_code'")->fetchColumn();
        if (!$hasUnitInvite) {
            $sql010 = __DIR__ . '/../../sql-dum/010_household_unit_manager_and_residents.sql';
            if (file_exists($sql010)) {
                run_sql_migration_file($pdo, $sql010);
            }
        }

        // 7. 101_collective_pond_and_bill_points.sql içindeki fatura alanlarını kur
        $hasSavedM3Column  = (bool) $pdo->query("SHOW COLUMNS FROM `water_logs` LIKE 'saved_m3'")->fetchColumn();
        if (!$hasSavedM3Column) {
            $sql101 = __DIR__ . '/../../sql-dum/101_collective_pond_and_bill_points.sql';
            if (file_exists($sql101)) {
                run_sql_migration_file($pdo, $sql101);
            }
        }

        // 8. 102_household_pond_and_ai_drops.sql kontrolü (household_ponds tablosu ve water_logs.ai_verified sütunu)
        $hasHouseholdPond = (bool) $pdo->query("SHOW TABLES LIKE 'household_ponds'")->fetchColumn();
        $hasAiVerifiedCol = (bool) $pdo->query("SHOW COLUMNS FROM `water_logs` LIKE 'ai_verified'")->fetchColumn();
        if (!$hasHouseholdPond || !$hasAiVerifiedCol) {
            $sql102 = __DIR__ . '/../../sql-dum/102_household_pond_and_ai_drops.sql';
            if (file_exists($sql102)) {
                run_sql_migration_file($pdo, $sql102);
            }
        }

        // 9. Eski kullanıcı/kolektif puanlarını ortak hane sistemine dönüştür
        $hasPointCleanup = (bool) $pdo->query("
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = 'debi_schema_versions'
        ")->fetchColumn();
        $cleanupDone = $hasPointCleanup && (bool) $pdo->query("
            SELECT 1 FROM debi_schema_versions WHERE version = '103_household_only_points'
        ")->fetchColumn();
        if (!$cleanupDone) {
            $sql103 = __DIR__ . '/../../sql-dum/103_household_only_points.sql';
            if (file_exists($sql103)) {
                run_sql_migration_file($pdo, $sql103);
            }
        }

        // Mevcut hanelerde owner_user_id veya unit_invite_code eksikse otomatik tamamla
        $pdo->exec("
            UPDATE households h
            SET h.owner_user_id = (
                SELECT MIN(u.id) FROM users u WHERE u.household_id = h.id
            )
            WHERE h.owner_user_id IS NULL
        ");

        $missingCodes = $pdo->query("SELECT id FROM households WHERE unit_invite_code IS NULL OR unit_invite_code = ''")->fetchAll();
        if (!empty($missingCodes)) {
            $updCode = $pdo->prepare("UPDATE households SET unit_invite_code = :code WHERE id = :id");
            foreach ($missingCodes as $row) {
                $updCode->execute([
                    ':code' => generate_invite_code('DAIRE'),
                    ':id'   => (int) $row['id'],
                ]);
            }
        }
    } catch (Throwable $e) {
        // Sessizce geç
    }
}

/**
 * SQL dosyasındaki ifadeleri güvenli şekilde tek tek çalıştırır
 */
function run_sql_migration_file(PDO $pdo, string $sqlFile): void
{
    $sql   = file_get_contents($sqlFile);
    $lines = explode("\n", (string) $sql);
    $clean = [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || strpos($trimmed, '--') === 0) {
            continue;
        }
        $clean[] = $line;
    }
    $statements = explode(';', implode("\n", $clean));
    foreach ($statements as $st) {
        $st = trim($st);
        if ($st !== '') {
            try {
                $pdo->exec($st);
            } catch (Throwable $ignore) {
                // Sütun/tablo zaten varsa devam et
            }
        }
    }
}

/**
 * Puan değerine göre Seviye (Level), Unvan ve İlerleme Yüzdesi döndürür.
 */
function get_level_info(int $points): array
{
    $levels = [
        1 => ['min' => 0,    'max' => 100,  'title' => 'Su Meraklısı',      'emoji' => '🌱', 'color' => '#2a9d8f'],
        2 => ['min' => 100,  'max' => 250,  'title' => 'Damla Koruyucusu',  'emoji' => '💧', 'color' => '#1cb0f6'],
        3 => ['min' => 250,  'max' => 500,  'title' => 'Musluk Ustası',     'emoji' => '🔧', 'color' => '#ffb703'],
        4 => ['min' => 500,  'max' => 1000, 'title' => 'Mahalle Kahramanı', 'emoji' => '🏡', 'color' => '#fb8500'],
        5 => ['min' => 1000, 'max' => 2500, 'title' => 'Debi Efsanesi',     'emoji' => '👑', 'color' => '#8338ec'],
    ];

    $currentLevel = 1;
    foreach ($levels as $lvl => $meta) {
        if ($points >= $meta['min']) {
            $currentLevel = $lvl;
        }
    }

    $meta = $levels[$currentLevel];
    if ($currentLevel === 5 && $points >= $meta['max']) {
        $progressPct = 100;
        $remaining   = 0;
    } else {
        $span        = max(1, $meta['max'] - $meta['min']);
        $gained      = max(0, $points - $meta['min']);
        $progressPct = min(100, (int) round(($gained / $span) * 100));
        $remaining   = max(0, $meta['max'] - $points);
    }

    return [
        'level'             => $currentLevel,
        'title'             => $meta['title'],
        'emoji'             => $meta['emoji'],
        'color'             => $meta['color'],
        'points'            => $points,
        'next_level_points' => $meta['max'],
        'progress_pct'      => $progressPct,
        'remaining_points'  => $remaining,
    ];
}

/**
 * Kullanıcıya ve Hanesine Damla Puanı (DP) Kazandırır
 * @return int Kazanılan puan (Mükerrer ise 0 döner)
 */
function award_points(
    int $userId,
    int $householdId,
    string $actionCode,
    int $points,
    string $description,
    ?string $refKey = null
): int {
    ensure_gamification_schema();
    if ($points <= 0 || $userId <= 0 || $householdId <= 0) {
        return 0;
    }

    $pdo = db();

    try {
        $typeStmt = $pdo->prepare("SELECT housing_type FROM households WHERE id = :hid LIMIT 1");
        $typeStmt->execute([':hid' => $householdId]);
        if (!in_array($typeStmt->fetchColumn(), ['apartment_unit', 'detached_house'], true)) {
            return 0;
        }

        if ($refKey !== null) {
            $chk = $pdo->prepare("SELECT id FROM point_logs WHERE household_id = :hid AND ref_key = :rkey LIMIT 1");
            $chk->execute([':hid' => $householdId, ':rkey' => $refKey]);
            if ($chk->fetchColumn()) {
                return 0; // Bu tekil aksiyondan daha önce puan kazanılmış
            }
        }

        $ins = $pdo->prepare("
            INSERT INTO point_logs (user_id, household_id, action_code, points, description, ref_key)
            VALUES (:uid, :hid, :code, :pts, :desc, :rkey)
        ");
        $ins->execute([
            ':uid'  => $userId,
            ':hid'  => $householdId,
            ':code' => $actionCode,
            ':pts'  => $points,
            ':desc' => $description,
            ':rkey' => $refKey,
        ]);

        $pdo->prepare("UPDATE households SET total_points = total_points + :pts WHERE id = :hid")
            ->execute([':pts' => $points, ':hid' => $householdId]);

        // Rozet kontrolü yap
        check_and_award_badges($userId, $householdId);

        return $points;
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Kullanıcının Rozet Koşullarını Kontrol Eder ve Hak Edilenleri Verir
 */
function check_and_award_badges(int $userId, int $householdId): array
{
    ensure_gamification_schema();
    $pdo = db();
    $newlyEarned = [];

    try {
        // Zaten kazanılmış rozet kodlarını al
        $earnedStmt = $pdo->prepare("
            SELECT b.code
            FROM user_badges ub
            INNER JOIN badges b ON b.id = ub.badge_id
            WHERE ub.user_id = :uid
        ");
        $earnedStmt->execute([':uid' => $userId]);
        $earnedCodes = array_flip($earnedStmt->fetchAll(PDO::FETCH_COLUMN));

        // Tüm rozetleri çek
        $allBadges = $pdo->query("SELECT * FROM badges")->fetchAll();
        $badgesByCode = [];
        foreach ($allBadges as $b) {
            $badgesByCode[$b['code']] = $b;
        }

        $toAward = [];

        // 1. first_drop: Her kayıtlı üye kazanır
        if (!isset($earnedCodes['first_drop']) && isset($badgesByCode['first_drop'])) {
            $toAward[] = $badgesByCode['first_drop'];
        }

        // 2. storyteller: En az 1 gönderi paylaşmış mı
        if (!isset($earnedCodes['storyteller']) && isset($badgesByCode['storyteller'])) {
            $st = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE user_id = :uid");
            $st->execute([':uid' => $userId]);
            if ((int) $st->fetchColumn() >= 1) {
                $toAward[] = $badgesByCode['storyteller'];
            }
        }

        // 3. habit_master: En az 3 tasarruf alışkanlığı aktif mi
        if (!isset($earnedCodes['habit_master']) && isset($badgesByCode['habit_master'])) {
            $st = $pdo->prepare("SELECT COUNT(*) FROM household_habits WHERE household_id = :hid");
            $st->execute([':hid' => $householdId]);
            if ((int) $st->fetchColumn() >= 3) {
                $toAward[] = $badgesByCode['habit_master'];
            }
        }

        // 4. meter_hero: En az 1 su sayacı verisi girmiş mi
        if (!isset($earnedCodes['meter_hero']) && isset($badgesByCode['meter_hero'])) {
            $st = $pdo->prepare("SELECT COUNT(*) FROM water_logs WHERE household_id = :hid");
            $st->execute([':hid' => $householdId]);
            if ((int) $st->fetchColumn() >= 1) {
                $toAward[] = $badgesByCode['meter_hero'];
            }
        }

        // 5. club_100l: Kişi başı günlük 100 L veya altı kaydı var mı
        if (!isset($earnedCodes['club_100l']) && isset($badgesByCode['club_100l'])) {
            $st = $pdo->prepare("SELECT COUNT(*) FROM water_logs WHERE household_id = :hid AND daily_liters_per_person <= 100");
            $st->execute([':hid' => $householdId]);
            if ((int) $st->fetchColumn() >= 1) {
                $toAward[] = $badgesByCode['club_100l'];
            }
        }

        // 6. good_neighbor: Toplam yorum + damla tepkisi >= 3 mü
        if (!isset($earnedCodes['good_neighbor']) && isset($badgesByCode['good_neighbor'])) {
            $st1 = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE user_id = :uid");
            $st1->execute([':uid' => $userId]);
            $st2 = $pdo->prepare("SELECT COUNT(*) FROM post_reactions WHERE user_id = :uid");
            $st2->execute([':uid' => $userId]);
            if (((int) $st1->fetchColumn() + (int) $st2->fetchColumn()) >= 3) {
                $toAward[] = $badgesByCode['good_neighbor'];
            }
        }

        foreach ($toAward as $badge) {
            $ins = $pdo->prepare("INSERT IGNORE INTO user_badges (user_id, badge_id) VALUES (:uid, :bid)");
            $ins->execute([':uid' => $userId, ':bid' => (int) $badge['id']]);
            if ($ins->rowCount() > 0) {
                $bonus = (int) $badge['bonus_points'];
                if ($bonus > 0) {
                    $pdo->prepare("
                        INSERT IGNORE INTO point_logs (user_id, household_id, action_code, points, description, ref_key)
                        VALUES (:uid, :hid, 'badge_earned', :pts, :desc, :rkey)
                    ")->execute([
                        ':uid'  => $userId,
                        ':hid'  => $householdId,
                        ':pts'  => $bonus,
                        ':desc' => $badge['emoji'] . ' "' . $badge['title'] . '" rozeti kazanıldı!',
                        ':rkey' => 'badge_' . $badge['code'],
                    ]);
                    $pdo->prepare("UPDATE households SET total_points = total_points + :pts WHERE id = :hid")
                        ->execute([':pts' => $bonus, ':hid' => $householdId]);
                }
                $newlyEarned[] = $badge;
            }
        }
    } catch (Throwable $e) {
        // Sessizce geç
    }

    return $newlyEarned;
}

/**
 * Kullanıcının Bugünkü Günlük Su Görevlerini Listeler
 */
function get_daily_quests_for_user(int $userId): array
{
    ensure_gamification_schema();
    $today = date('Y-m-d');

    try {
        $stmt = db()->prepare("
            SELECT
                dq.*,
                CASE WHEN udq.id IS NOT NULL THEN 1 ELSE 0 END AS completed_today
            FROM daily_quests dq
            LEFT JOIN user_daily_quests udq
                ON udq.quest_id = dq.id AND udq.user_id = :uid AND udq.completed_date = :today
            ORDER BY dq.id ASC
        ");
        $stmt->execute([':uid' => $userId, ':today' => $today]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Günlük Görevi Tamamla ve Puan Kazandır
 */
function complete_daily_quest(array $user, int $questId): array
{
    ensure_gamification_schema();
    $pdo   = db();
    $today = date('Y-m-d');

    try {
        $qStmt = $pdo->prepare("SELECT * FROM daily_quests WHERE id = :id LIMIT 1");
        $qStmt->execute([':id' => $questId]);
        $quest = $qStmt->fetch();

        if (!$quest) {
            return ['ok' => false, 'error' => 'Görev bulunamadı.'];
        }

        $chk = $pdo->prepare("
            SELECT id FROM user_daily_quests
            WHERE user_id = :uid AND quest_id = :qid AND completed_date = :today
            LIMIT 1
        ");
        $chk->execute([
            ':uid'   => (int) $user['id'],
            ':qid'   => $questId,
            ':today' => $today,
        ]);

        if ($chk->fetchColumn()) {
            return ['ok' => false, 'error' => 'Bu görevi bugün zaten tamamladın! Yarın tekrar yapabilirsin.'];
        }

        $ins = $pdo->prepare("
            INSERT INTO user_daily_quests (user_id, quest_id, completed_date)
            VALUES (:uid, :qid, :today)
        ");
        $ins->execute([
            ':uid'   => (int) $user['id'],
            ':qid'   => $questId,
            ':today' => $today,
        ]);

        $pts = (int) $quest['points'];
        $ref = 'quest_' . $questId . '_' . $today;
        award_points(
            (int) $user['id'],
            (int) $user['household_id'],
            'daily_quest',
            $pts,
            $quest['emoji'] . ' Günlük Görev: ' . $quest['title'],
            $ref
        );

        return [
            'ok'     => true,
            'points' => $pts,
            'title'  => $quest['title'],
            'emoji'  => $quest['emoji'],
            'liters' => (int) $quest['water_saved_liters'],
        ];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Görev tamamlanırken bir hata oluştu.'];
    }
}

/**
 * Kullanıcının Rozet Durumu (Kazanılanlar ve Kilitli Olanlar)
 */
function get_user_badges_with_catalog(int $userId): array
{
    ensure_gamification_schema();
    try {
        $stmt = db()->prepare("
            SELECT
                b.*,
                CASE WHEN ub.user_id IS NOT NULL THEN 1 ELSE 0 END AS is_earned,
                ub.earned_at
            FROM badges b
            LEFT JOIN user_badges ub ON ub.badge_id = b.id AND ub.user_id = :uid
            ORDER BY is_earned DESC, b.id ASC
        ");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Kullanıcının Son Puan Kazanım Geçmişi
 */
function get_user_point_history(int $userId, int $limit = 10): array
{
    ensure_gamification_schema();
    try {
        $stmt = db()->prepare("
            SELECT *
            FROM point_logs
            WHERE user_id = :uid
            ORDER BY created_at DESC, id DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Mahalledeki En Yüksek Damla Puanlı (DP) Haneler / Komşular Sıralaması
 */
function get_neighborhood_points_leaderboard(int $neighborhoodId, int $limit = 8): array
{
    ensure_gamification_schema();
    try {
        $stmt = db()->prepare("
            SELECT
                u.id AS user_id,
                u.full_name,
                u.avatar_color,
                h.total_points,
                h.unit_label,
                h.housing_type,
                a.name AS apartment_name
            FROM households h
            INNER JOIN users u ON u.id = COALESCE(
                NULLIF(h.owner_user_id, 0),
                (SELECT MIN(hu.id) FROM users hu WHERE hu.household_id = h.id)
            )
            LEFT JOIN apartments a ON a.id = u.apartment_id
            WHERE h.neighborhood_id = :nid
              AND h.housing_type IN ('apartment_unit', 'detached_house')
            ORDER BY h.total_points DESC, h.id ASC
            LIMIT :lim
        ");
        $stmt->bindValue(':nid', $neighborhoodId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}
