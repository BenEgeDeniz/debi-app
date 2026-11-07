-- ==========================================================
-- DEBI - 103: Yalnızca ortak daire/müstakil ev puanı
-- Kullanıcı cüzdanlarını, kolektif göleti ve bireysel hane puanlarını kaldırır.
-- ==========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `debi_schema_versions` (
    `version` VARCHAR(80) NOT NULL,
    `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `collective_pond`;

ALTER TABLE `users` DROP COLUMN `total_points`;

DELETE pl
FROM `point_logs` pl
INNER JOIN `households` h ON h.id = pl.household_id
WHERE h.housing_type = 'individual_unit';

UPDATE `water_logs` wl
INNER JOIN `households` h ON h.id = wl.household_id
SET wl.earned_points = 0
WHERE h.housing_type = 'individual_unit';

DELETE hp
FROM `household_ponds` hp
INNER JOIN `households` h ON h.id = hp.household_id
WHERE h.housing_type = 'individual_unit';

UPDATE `households`
SET `total_points` = 0
WHERE `housing_type` = 'individual_unit';

INSERT IGNORE INTO `debi_schema_versions` (`version`)
VALUES ('103_household_only_points');

SET FOREIGN_KEY_CHECKS = 1;
