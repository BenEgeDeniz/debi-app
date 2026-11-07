-- ==========================================================
-- DEBI - Su Tasarrufu Sosyal Medya Platformu
-- Revizyon: 103_events_and_ai_rewards.sql
-- Açıklama: Akış Etkinlikleri, Yapay Zeka Ödül Puanı & Gerekçesi,
--           Canlı Sohbet Odası, Yoklama ve Özet Bildirimi
-- ==========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Etkinlikler Tablosu (events)
CREATE TABLE IF NOT EXISTS `events` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL COMMENT 'Etkinliği oluşturan kullanıcı',
    `apartment_id` INT UNSIGNED DEFAULT NULL COMMENT 'Apartman etkinliği ise apartman ID',
    `neighborhood_id` INT UNSIGNED NOT NULL COMMENT 'Mahalle ID',
    `visibility` ENUM('friends', 'apartment', 'public') NOT NULL DEFAULT 'public' COMMENT 'Hangi akışta yayınlanacak',
    `title` VARCHAR(180) NOT NULL COMMENT 'Etkinlik Başlığı',
    `description` TEXT NOT NULL COMMENT 'Etkinlik Açıklaması',
    `location` VARCHAR(180) NOT NULL COMMENT 'Etkinlik Yeri',
    `event_date` DATETIME NOT NULL COMMENT 'Etkinlik Tarihi ve Saati',
    `status` ENUM('upcoming', 'live', 'completed', 'cancelled') NOT NULL DEFAULT 'upcoming' COMMENT 'Etkinlik durumu',
    `reward_points` INT UNSIGNED NOT NULL DEFAULT 25 COMMENT 'Yapay zeka tarafından belirlenen ödül puanı (Damla)',
    `ai_reason` VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'Yapay zeka gerekçesi (maksimum 100 karakter)',
    `attendance_taken` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Yoklama alındı mı',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_events_visibility` (`visibility`),
    KEY `idx_events_status` (`status`),
    KEY `idx_events_date` (`event_date`),
    KEY `idx_events_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Katılımcılar & Yoklama & Özet Tablosu (event_participants)
CREATE TABLE IF NOT EXISTS `event_participants` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `household_id` INT UNSIGNED NOT NULL,
    `rsvp_status` ENUM('attending', 'declined') NOT NULL DEFAULT 'attending',
    `attended` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Yoklamada geldi olarak işaretlendi mi',
    `points_awarded` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Ödül puanı haneye yüklendi mi',
    `summary_note` TEXT DEFAULT NULL COMMENT 'Ne yaptım & ne öğrendim özeti',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_event_user` (`event_id`, `user_id`),
    KEY `idx_ep_event` (`event_id`),
    KEY `idx_ep_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Canlı Etkinlik Sohbet Mesajları (event_messages)
CREATE TABLE IF NOT EXISTS `event_messages` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `message` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_em_event` (`event_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
