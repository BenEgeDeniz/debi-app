-- ==========================================================
-- DEBI - Su Tasarrufu Sosyal Medya Platformu
-- Revizyon: 007_bill_images.sql
-- Açıklama: Su faturalarının görsel olarak yüklenmesi ve
--           Gemini AI ile okunan fatura detaylarının saklanması
-- ==========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `water_logs`
ADD COLUMN `bill_image_path` VARCHAR(255) DEFAULT NULL COMMENT 'Yüklenen su faturası fotoğrafı' AFTER `bill_amount`,
ADD COLUMN `provider_name` VARCHAR(100) DEFAULT NULL COMMENT 'Su idaresi adı (Örn: İSKİ, ASKİ, İZSU)' AFTER `bill_image_path`,
ADD COLUMN `reading_days` INT UNSIGNED NOT NULL DEFAULT 30 COMMENT 'Fatura okuma gün sayısı' AFTER `provider_name`;

SET FOREIGN_KEY_CHECKS = 1;
