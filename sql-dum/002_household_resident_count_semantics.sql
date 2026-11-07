-- resident_count is the total number of people living in the household.
-- It is intentionally independent from the number of registered user accounts.

ALTER TABLE `households`
    MODIFY COLUMN `resident_count` tinyint(3) UNSIGNED NOT NULL DEFAULT 2
    COMMENT 'Hanede yaşayan kişi sayısı (kişi başı hesap için)';

ALTER TABLE `water_logs`
    MODIFY COLUMN `resident_count_snapshot` tinyint(3) UNSIGNED NOT NULL DEFAULT 2
    COMMENT 'Kayıt anındaki hane kişi sayısı';
