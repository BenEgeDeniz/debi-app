-- ==========================================================
-- DEBI - 104: Puan yalnızca fatura yükleme/tasarruf aksiyonundan gelir
-- Tek ortak bakiye: households.total_points
-- ==========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `debi_schema_versions` (
    `version` VARCHAR(80) NOT NULL,
    `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `households`
    MODIFY COLUMN `total_points` INT UNSIGNED NOT NULL DEFAULT 0;

DELETE FROM `point_logs`;

UPDATE `daily_quests` SET `points` = 0;
UPDATE `badges` SET `bonus_points` = 0;

ALTER TABLE `point_logs`
    DROP INDEX `uq_user_ref_key`,
    ADD UNIQUE KEY `uq_household_ref_key` (`household_id`, `ref_key`);

INSERT INTO `point_logs` (
    `user_id`, `household_id`, `action_code`, `points`, `description`, `ref_key`
)
SELECT
    wl.`user_id`,
    wl.`household_id`,
    'bill_drops_earned',
    wl.`earned_points`,
    CONCAT('💧 ', wl.`period_year`, '-', LPAD(wl.`period_month`, 2, '0'), ' faturasından +', wl.`earned_points`, ' Puan'),
    CONCAT('bill_drops_', wl.`period_year`, '_', wl.`period_month`)
FROM `water_logs` wl
INNER JOIN `households` h ON h.`id` = wl.`household_id`
WHERE wl.`earned_points` > 0
  AND h.`housing_type` IN ('apartment_unit', 'detached_house');

UPDATE `households` h
LEFT JOIN (
    SELECT household_id, COALESCE(SUM(points), 0) AS invoice_points
    FROM point_logs
    WHERE action_code = 'bill_drops_earned'
    GROUP BY household_id
) pl ON pl.household_id = h.id
SET h.total_points = CASE
    WHEN h.housing_type IN ('apartment_unit', 'detached_house')
        THEN COALESCE(pl.invoice_points, 0)
    ELSE 0
END;

INSERT IGNORE INTO `debi_schema_versions` (`version`)
VALUES ('104_invoice_only_points');

SET FOREIGN_KEY_CHECKS = 1;
