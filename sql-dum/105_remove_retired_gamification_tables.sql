-- ==========================================================
-- DEBI - 105: Kullanılmayan eski oyunlaştırma tablolarını kaldır
-- Ortak puan bakiyesi households.total_points üzerinde kalır.
-- Fatura geçmişi point_logs üzerinde kalır.
-- ==========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `user_daily_quests`;
DROP TABLE IF EXISTS `daily_quests`;
DROP TABLE IF EXISTS `user_badges`;
DROP TABLE IF EXISTS `badges`;
DROP TABLE IF EXISTS `household_ponds`;

CREATE TABLE IF NOT EXISTS `debi_schema_versions` (
    `version` VARCHAR(80) NOT NULL,
    `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `debi_schema_versions` (`version`)
VALUES ('105_remove_retired_gamification_tables');

SET FOREIGN_KEY_CHECKS = 1;
