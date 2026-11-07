-- ==========================================================
-- DEBI - Su Tasarrufu Sosyal Medya Platformu
-- Revizyon: 003_water_tracking.sql
-- Açıklama: Su tüketim kayıtları (water_logs), tasarruf alışkanlıkları
--           (saving_habits) ve hane alışkanlık eşleşmeleri (household_habits)
-- ==========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Aylık Su Tüketim ve Fatura Kayıtları
CREATE TABLE IF NOT EXISTS `water_logs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `household_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `neighborhood_id` INT UNSIGNED NOT NULL,
    `apartment_id` INT UNSIGNED DEFAULT NULL,
    `period_year` SMALLINT UNSIGNED NOT NULL COMMENT 'Örn: 2026',
    `period_month` TINYINT UNSIGNED NOT NULL COMMENT '1-12 arası ay',
    `consumption_m3` DECIMAL(7,2) NOT NULL COMMENT 'Aylık tüketim (Metreküp m3)',
    `resident_count_snapshot` TINYINT UNSIGNED NOT NULL DEFAULT 2 COMMENT 'Kayıt anındaki hane kişi sayısı',
    `daily_liters_per_person` DECIMAL(7,1) NOT NULL COMMENT '(consumption_m3 * 1000) / (30 * resident_count)',
    `bill_amount` DECIMAL(9,2) DEFAULT NULL COMMENT 'Fatura tutarı (TL - opsiyonel)',
    `notes` VARCHAR(255) DEFAULT NULL COMMENT 'Bu ay yapılan tasarruf notu',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_household_period` (`household_id`, `period_year`, `period_month`),
    KEY `idx_water_neighborhood_period` (`neighborhood_id`, `period_year`, `period_month`),
    KEY `idx_water_apartment_period` (`apartment_id`, `period_year`, `period_month`),
    CONSTRAINT `fk_water_household`
        FOREIGN KEY (`household_id`) REFERENCES `households` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_water_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_water_neighborhood`
        FOREIGN KEY (`neighborhood_id`) REFERENCES `neighborhoods` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_water_apartment`
        FOREIGN KEY (`apartment_id`) REFERENCES `apartments` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tasarruf Alışkanlıkları Kataloğu
CREATE TABLE IF NOT EXISTS `saving_habits` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL,
    `title` VARCHAR(140) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `estimated_monthly_liters` INT UNSIGNED NOT NULL DEFAULT 300,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_habit_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Hanelerin Uyguladığı Tasarruf Alışkanlıkları
CREATE TABLE IF NOT EXISTS `household_habits` (
    `household_id` INT UNSIGNED NOT NULL,
    `habit_id` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`household_id`, `habit_id`),
    CONSTRAINT `fk_hh_household`
        FOREIGN KEY (`household_id`) REFERENCES `households` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_hh_habit`
        FOREIGN KEY (`habit_id`) REFERENCES `saving_habits` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Varsayılan Su Tasarrufu Alışkanlıkları
INSERT INTO `saving_habits` (`code`, `title`, `description`, `estimated_monthly_liters`) VALUES
('aerator_faucet', 'Musluklarda Perlatör (Hava Karıştırıcı) Kullanımı', 'Mutfak ve banyo musluklarına takılan perlatör ile debiyi %40 azaltır.', 1200),
('short_shower', 'Duş Süresini 5 Dakikanın Altında Tutmak', 'Her duşta ortalama 45-60 litre su tasarrufu sağlar.', 1500),
('dual_flush', 'Çift Kademeli Rezervuar / Şişe Yöntemi', 'Sifon çekimlerinde kullanılan su miktarını yarıya indirir.', 900),
('full_dishwasher', 'Bulaşık ve Çamaşır Makinesini Tam Dolu Çalıştırmak', 'Elde yıkamaya ve yarım makineye göre ayda yüzlerce litre korur.', 1100),
('cold_water_bucket', 'Duşta Sıcak Su Gelene Kadar Akan Suyu Kovada Biriktirmek', 'Isınmasını beklerken boşa akan suyu temizlik veya çiçek sulamada kullanmak.', 450),
('leak_check', 'Damlatan Musluk ve Sızdıran Rezervuar Kontrolü', 'Saniyede bir damla su ayda yaklaşık 750 litre kayba yol açar.', 750)
ON DUPLICATE KEY UPDATE
    `title` = VALUES(`title`),
    `description` = VALUES(`description`),
    `estimated_monthly_liters` = VALUES(`estimated_monthly_liters`);

SET FOREIGN_KEY_CHECKS = 1;

