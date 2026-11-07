-- ==========================================================
-- DEBI - Su Tasarrufu Sosyal Medya Platformu
-- Revizyon: 006_gamification_points.sql
-- Açıklama: Oyunlaştırma (Damla Puanı / DP), Seviye, Günlük Su Görevleri
--           (daily_quests), Rozetler (badges) ve Puan Geçmişi (point_logs)
-- ==========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Kullanıcılar ve Haneler tablolarına toplam puan (total_points) ekleme
ALTER TABLE `users`
ADD COLUMN `total_points` INT UNSIGNED NOT NULL DEFAULT 50 COMMENT 'Toplam Damla Puanı (DP)' AFTER `avatar_color`;

ALTER TABLE `households`
ADD COLUMN `total_points` INT UNSIGNED NOT NULL DEFAULT 50 COMMENT 'Hane Toplam Damla Puanı (DP)' AFTER `resident_count`;

-- 2. Puan Geçmişi Tablosu (Hangi aksiyondan kaç puan kazanıldı)
CREATE TABLE IF NOT EXISTS `point_logs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `household_id` INT UNSIGNED NOT NULL,
    `action_code` VARCHAR(50) NOT NULL COMMENT 'Örn: welcome_bonus, daily_quest, water_log_entry, post_create',
    `points` INT NOT NULL COMMENT 'Kazanılan Damla Puanı (+DP)',
    `description` VARCHAR(255) NOT NULL,
    `ref_key` VARCHAR(100) DEFAULT NULL COMMENT 'Tekil aksiyon anahtarı (mükerrer puanı önlemek için)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_ref_key` (`user_id`, `ref_key`),
    KEY `idx_point_logs_user` (`user_id`, `created_at`),
    KEY `idx_point_logs_household` (`household_id`, `created_at`),
    CONSTRAINT `fk_pl_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_pl_household`
        FOREIGN KEY (`household_id`) REFERENCES `households` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Günlük Eğlenceli Su Görevleri Kataloğu
CREATE TABLE IF NOT EXISTS `daily_quests` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL,
    `title` VARCHAR(140) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `emoji` VARCHAR(16) NOT NULL DEFAULT '💧',
    `points` INT UNSIGNED NOT NULL DEFAULT 15,
    `water_saved_liters` INT UNSIGNED NOT NULL DEFAULT 25,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_quest_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Kullanıcı Günlük Görev Tamamlama Tablosu
CREATE TABLE IF NOT EXISTS `user_daily_quests` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `quest_id` INT UNSIGNED NOT NULL,
    `completed_date` DATE NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_quest_date` (`user_id`, `quest_id`, `completed_date`),
    KEY `idx_udq_user_date` (`user_id`, `completed_date`),
    CONSTRAINT `fk_udq_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_udq_quest`
        FOREIGN KEY (`quest_id`) REFERENCES `daily_quests` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Rozetler Kataloğu
CREATE TABLE IF NOT EXISTS `badges` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL,
    `title` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `emoji` VARCHAR(16) NOT NULL DEFAULT '🏅',
    `color_theme` VARCHAR(24) NOT NULL DEFAULT 'blue',
    `bonus_points` INT UNSIGNED NOT NULL DEFAULT 30,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_badge_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Kullanıcıların Kazandığı Rozetler
CREATE TABLE IF NOT EXISTS `user_badges` (
    `user_id` INT UNSIGNED NOT NULL,
    `badge_id` INT UNSIGNED NOT NULL,
    `earned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `badge_id`),
    CONSTRAINT `fk_ub_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_ub_badge`
        FOREIGN KEY (`badge_id`) REFERENCES `badges` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Varsayılan Günlük Görevler
INSERT INTO `daily_quests` (`code`, `title`, `description`, `emoji`, `points`, `water_saved_liters`) VALUES
('quick_shower', '5 Dakika Duş Meydan Okuması', 'Bugün duş süreni 5 dakikanın altında tut!', '🚿', 20, 45),
('veggie_bowl', 'Sebze & Meyve Suyunu Kurtar', 'Sebzeleri akan musluk yerine kapta yıka, suyunu çiçeklere ver.', '🪴', 15, 18),
('brush_tap_off', 'Fırçalarken Musluğu Kapat', 'Diş fırçalarken veya tıraş olurken musluğu hiç açık bırakma.', '🪥', 15, 12),
('eco_machine', 'Tam Dolu Ekonomi Modu', 'Bulaşık veya çamaşır makinesini tam dolmadan çalıştırma.', '🫧', 20, 35),
('kettle_measure', 'İhtiyacın Kadar Su Kaynat', 'Çay/kahve için ısıtıcıya sadece içeceğin bardak kadar su koy.', '☕', 10, 5)
ON DUPLICATE KEY UPDATE
    `title` = VALUES(`title`),
    `description` = VALUES(`description`),
    `points` = VALUES(`points`),
    `water_saved_liters` = VALUES(`water_saved_liters`);

-- Varsayılan Rozetler
INSERT INTO `badges` (`code`, `title`, `description`, `emoji`, `color_theme`, `bonus_points`) VALUES
('first_drop', 'İlk Damla', 'Debi topluluğuna katıldı ve ilk adımını attı!', '💧', 'aqua', 25),
('storyteller', 'Komşu Muhabiri', 'Akışta ilk tasarruf ipucunu veya başarısını paylaştı.', '📣', 'green', 30),
('habit_master', 'Tasarruf Ustası', 'Evinde en az 3 kalıcı su tasarrufu alışkanlığını hayata geçirdi.', '🛠️', 'amber', 50),
('meter_hero', 'Şeffaf Sayaç', 'Aylık su sayaç verisini girerek mahalle ortalamasına katkı sağladı.', '📊', 'purple', 40),
('club_100l', '100 Litre Kulübü', 'Kişi başı günlük su tüketimini 100 Litrenin altına indirmeyi başardı!', '🏆', 'gold', 100),
('good_neighbor', 'Mahalle Neşesi', 'Komşularının paylaşımlarına damla desteği ve yorumlarla güç verdi.', '🤝', 'coral', 35)
ON DUPLICATE KEY UPDATE
    `title` = VALUES(`title`),
    `description` = VALUES(`description`),
    `bonus_points` = VALUES(`bonus_points`);

-- Mevcut Örnek Kullanıcılara Başlangıç Puanları ve Rozetleri (Eğer 004 yüklendiyse)
UPDATE `users` SET `total_points` = 420 WHERE `id` = 1;
UPDATE `users` SET `total_points` = 560 WHERE `id` = 2;
UPDATE `users` SET `total_points` = 210 WHERE `id` = 3;
UPDATE `users` SET `total_points` = 340 WHERE `id` = 4;
UPDATE `users` SET `total_points` = 290 WHERE `id` = 5;
UPDATE `users` SET `total_points` = 310 WHERE `id` = 6;
UPDATE `users` SET `total_points` = 380 WHERE `id` = 7;

UPDATE `households` SET `total_points` = 420 WHERE `id` = 1;
UPDATE `households` SET `total_points` = 560 WHERE `id` = 2;
UPDATE `households` SET `total_points` = 210 WHERE `id` = 3;
UPDATE `households` SET `total_points` = 340 WHERE `id` = 4;
UPDATE `households` SET `total_points` = 290 WHERE `id` = 5;
UPDATE `households` SET `total_points` = 310 WHERE `id` = 6;
UPDATE `households` SET `total_points` = 380 WHERE `id` = 7;

SET FOREIGN_KEY_CHECKS = 1;
