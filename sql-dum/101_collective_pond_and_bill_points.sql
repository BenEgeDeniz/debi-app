-- ==========================================================
-- DEBI - Su Tasarrufu Sosyal Medya Platformu
-- Revizyon: 101_collective_pond_and_bill_points.sql
-- Açıklama: 1. Puanlama Yöntemi (Fatura Farkı -> %10 Barajlı Debi Puanı)
--           ve Ortak Kolektif Havuz / Sanal Gölet (collective_pond) Tablosu
-- ==========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Aylık su faturaları (water_logs) tablosuna geçen ayki tüketim,
--    tasarruf (m3 / litre / yüzde) ve kazanılan Debi Puanı sütunları eklenir
ALTER TABLE `water_logs`
    ADD COLUMN `previous_consumption_m3` DECIMAL(8,2) DEFAULT NULL COMMENT 'Geçen ayki tüketim (m3)' AFTER `consumption_m3`,
    ADD COLUMN `saved_m3` DECIMAL(8,2) NOT NULL DEFAULT 0.00 COMMENT 'Tasarruf edilen su (gecen_ay_m3 - bu_ay_m3)' AFTER `previous_consumption_m3`,
    ADD COLUMN `saved_liters` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Kurtarılan su (Litre = saved_m3 * 1000)' AFTER `saved_m3`,
    ADD COLUMN `saving_pct` DECIMAL(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Önceki aya göre tasarruf yüzdesi (%)' AFTER `saved_liters`,
    ADD COLUMN `earned_points` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Fatura farkından kazanılan Debi Puanı (+10 Puan)' AFTER `saving_pct`;

-- 2. Ortak Kolektif Havuz / Debi Puan Göleti (Sanal Gölet / Baraj) Tablosu
CREATE TABLE IF NOT EXISTS `collective_pond` (
    `id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `golet_mevcut_su_m3` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Gölette biriken toplam kurtarılan su (m3)',
    `golet_mevcut_litre` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Gölette biriken toplam kurtarılan su (Litre)',
    `golet_toplam_puan` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Gölette biriken toplam Debi Puanı',
    `golet_hedef_m3` DECIMAL(12,2) NOT NULL DEFAULT 500.00 COMMENT 'Gölet hedef su kapasitesi (m3)',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Varsayılan tekil gölet satırını oluştur (Hedef kapasite: 500 m3)
INSERT IGNORE INTO `collective_pond` (`id`, `golet_mevcut_su_m3`, `golet_mevcut_litre`, `golet_toplam_puan`, `golet_hedef_m3`)
VALUES (1, 0.00, 0, 0, 500.00);

SET FOREIGN_KEY_CHECKS = 1;

