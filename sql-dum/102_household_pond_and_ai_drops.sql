-- ==========================================================
-- DEBI - Su Tasarrufu Sosyal Medya Platformu
-- Revizyon: 102_household_pond_and_ai_drops.sql
-- Açıklama: Daire Bazlı Gölet (household_ponds) ve Yapay Zeka
--           Doğrulamalı Fatura Damla Ödülü (water_logs.ai_verified)
-- ==========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. water_logs tablosuna yapay zeka verisi değiştirilmeden onaylandı mı sütunu eklenir
ALTER TABLE `water_logs`
    ADD COLUMN `ai_verified` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Yapay zeka verisi değiştirilmeden onaylandı mı' AFTER `earned_points`;

-- 2. Daire / Hane Bazlı Gölet (household_ponds) Tablosu
CREATE TABLE IF NOT EXISTS `household_ponds` (
    `household_id` INT UNSIGNED NOT NULL,
    `total_drops` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Dairenin göletinde biriken toplam Damla sayısı',
    `saved_m3` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Dairenin faturalardan kurtardığı toplam su (m3)',
    `saved_liters` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Dairenin kurtardığı toplam su (Litre)',
    `target_drops` INT UNSIGNED NOT NULL DEFAULT 100 COMMENT 'Daire göleti tam doluluk hedefi (Damla)',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`household_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
