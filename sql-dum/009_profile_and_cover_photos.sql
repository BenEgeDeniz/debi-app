-- ==========================================================
-- DEBI - Su Tasarrufu Sosyal Medya Platformu
-- Revizyon: 009_profile_and_cover_photos.sql
-- Açıklama: Kullanıcılara Profil Fotoğrafı (avatar_photo) ve
--           Zaman Tüneli Kapak Fotoğrafı (cover_photo) desteği
-- ==========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `users`
ADD COLUMN `avatar_photo` VARCHAR(255) DEFAULT NULL COMMENT 'Profil fotoğrafı dosya yolu' AFTER `avatar_color`,
ADD COLUMN `cover_photo` VARCHAR(255) DEFAULT NULL COMMENT 'Zaman tüneli kapak fotoğrafı dosya yolu' AFTER `avatar_photo`;

SET FOREIGN_KEY_CHECKS = 1;
