-- ==========================================================
-- DEBI - 010: Daire (Hane) Yöneticisi ve Daire Sakin Davet Kodu
-- Daireyi oluşturan kullanıcı Daire Yöneticisi (owner_user_id) olur
-- ve unit_invite_code (DAIRE-XXXXX) ile dairesine sakin davet edebilir.
-- ==========================================================

SET NAMES utf8mb4;

ALTER TABLE `households`
    ADD COLUMN `owner_user_id` INT UNSIGNED DEFAULT NULL AFTER `apartment_id`,
    ADD COLUMN `unit_invite_code` VARCHAR(32) DEFAULT NULL AFTER `resident_count`,
    ADD UNIQUE KEY `uq_households_unit_invite_code` (`unit_invite_code`);
