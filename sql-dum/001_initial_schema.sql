-- ==========================================================
-- DEBI - Su Tasarrufu Sosyal Medya Platformu
-- Revizyon: 001_initial_schema.sql
-- Açıklama: Mahalle, Apartman, Hane (Bireysel/Müstakil/Daire),
--           Kullanıcı ve Davet tabloları
-- ==========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Mahalleler Tablosu (Üst Çatı)
CREATE TABLE IF NOT EXISTS `neighborhoods` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `city` VARCHAR(80) NOT NULL COMMENT 'İl (Örn: İstanbul)',
    `district` VARCHAR(80) NOT NULL COMMENT 'İlçe (Örn: Kadıköy)',
    `name` VARCHAR(120) NOT NULL COMMENT 'Mahalle Adı (Örn: Moda Mah.)',
    `slug` VARCHAR(255) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_neighborhood_location` (`city`, `district`, `name`),
    KEY `idx_neighborhood_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Apartmanlar Tablosu (Mahalleye Bağlı)
CREATE TABLE IF NOT EXISTS `apartments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `neighborhood_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(150) NOT NULL COMMENT 'Apartman veya Site Adı',
    `address_detail` VARCHAR(255) DEFAULT NULL COMMENT 'Sokak / Bina No bilgisi',
    `invite_code` VARCHAR(24) NOT NULL COMMENT 'Genel apartman davet kodu (Örn: DEBI-4829)',
    `total_units` SMALLINT UNSIGNED NOT NULL DEFAULT 10 COMMENT 'Toplam daire sayısı',
    `monthly_goal_m3` DECIMAL(8,2) DEFAULT NULL COMMENT 'Apartman aylık hedef m3 (opsiyonel)',
    `manager_user_id` INT UNSIGNED DEFAULT NULL COMMENT 'Apartman yöneticisi kullanıcı ID',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_apartment_invite_code` (`invite_code`),
    KEY `idx_apartment_neighborhood` (`neighborhood_id`),
    CONSTRAINT `fk_apartments_neighborhood`
        FOREIGN KEY (`neighborhood_id`) REFERENCES `neighborhoods` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Haneler Tablosu (Apartman Dairesi, Bireysel Daire veya Müstakil Ev)
CREATE TABLE IF NOT EXISTS `households` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `neighborhood_id` INT UNSIGNED NOT NULL,
    `apartment_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL ise mahallede bireysel veya müstakil katılımcı',
    `housing_type` ENUM('apartment_unit', 'individual_unit', 'detached_house') NOT NULL DEFAULT 'individual_unit'
        COMMENT 'apartment_unit: Apartmana bağlı daire, individual_unit: Apartmansız bireysel daire, detached_house: Müstakil ev',
    `unit_label` VARCHAR(80) NOT NULL COMMENT 'Örn: Daire 4, Müstakil No:12, Bireysel Hane',
    `resident_count` TINYINT UNSIGNED NOT NULL DEFAULT 2 COMMENT 'Hanede yaşayan kişi sayısı (kişi başı hesap için)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_household_neighborhood` (`neighborhood_id`),
    KEY `idx_household_apartment` (`apartment_id`),
    CONSTRAINT `fk_households_neighborhood`
        FOREIGN KEY (`neighborhood_id`) REFERENCES `neighborhoods` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_households_apartment`
        FOREIGN KEY (`apartment_id`) REFERENCES `apartments` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Kullanıcılar Tablosu
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `household_id` INT UNSIGNED NOT NULL,
    `neighborhood_id` INT UNSIGNED NOT NULL,
    `apartment_id` INT UNSIGNED DEFAULT NULL,
    `full_name` VARCHAR(120) NOT NULL,
    `email` VARCHAR(160) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('manager', 'resident') NOT NULL DEFAULT 'resident' COMMENT 'manager: Apartman Yöneticisi, resident: Sakin/Bireysel',
    `bio` VARCHAR(255) DEFAULT NULL,
    `avatar_color` VARCHAR(16) NOT NULL DEFAULT '#1b5670',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_household` (`household_id`),
    KEY `idx_users_neighborhood` (`neighborhood_id`),
    KEY `idx_users_apartment` (`apartment_id`),
    CONSTRAINT `fk_users_household`
        FOREIGN KEY (`household_id`) REFERENCES `households` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_users_neighborhood`
        FOREIGN KEY (`neighborhood_id`) REFERENCES `neighborhoods` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_users_apartment`
        FOREIGN KEY (`apartment_id`) REFERENCES `apartments` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Daire Bazlı Özel Davetler Tablosu (Yöneticinin dairelere özel ürettiği kodlar)
CREATE TABLE IF NOT EXISTS `invitations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `apartment_id` INT UNSIGNED NOT NULL,
    `unit_label` VARCHAR(80) NOT NULL COMMENT 'Örn: Daire 5',
    `invite_token` VARCHAR(32) NOT NULL COMMENT 'Daireye özel davet kodu',
    `is_used` TINYINT(1) NOT NULL DEFAULT 0,
    `used_by_user_id` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `used_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_invitation_token` (`invite_token`),
    KEY `idx_invitation_apartment` (`apartment_id`),
    CONSTRAINT `fk_invitations_apartment`
        FOREIGN KEY (`apartment_id`) REFERENCES `apartments` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_invitations_user`
        FOREIGN KEY (`used_by_user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

