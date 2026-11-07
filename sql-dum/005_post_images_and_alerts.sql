-- ==========================================================
-- DEBI - Su Tasarrufu Sosyal Medya Platformu
-- Revizyon: 005_post_images_and_alerts.sql
-- Açıklama: Gönderilere görsel yükleme desteği (image_path) ve
--           Mahalle Su Kesintisi / Tesisat Arıza Uyarıları tablosu
-- ==========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Gönderiler tablosuna sayaç / tasarruf / tesisat görseli alanı
ALTER TABLE `posts`
ADD COLUMN `image_path` VARCHAR(255) DEFAULT NULL AFTER `water_saved_liters`;

-- 2. Mahalle Su Kesintisi ve Tesisat Acil Durum Bildirimleri
CREATE TABLE IF NOT EXISTS `neighborhood_alerts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `neighborhood_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `alert_type` ENUM('outage', 'low_pressure', 'pipe_leak', 'contamination') NOT NULL DEFAULT 'outage'
        COMMENT 'outage: Planlı/Plansız Kesinti, low_pressure: Düşük Basınç, pipe_leak: Sokak/Bina Ana Hat Sızıntısı, contamination: Bulanıklık/Koku',
    `title` VARCHAR(150) NOT NULL,
    `description` TEXT NOT NULL,
    `expected_resolution` VARCHAR(100) DEFAULT NULL COMMENT 'Örn: Bugün 18:00 tahmini',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `resolved_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_alerts_neighborhood_active` (`neighborhood_id`, `is_active`, `created_at`),
    CONSTRAINT `fk_alerts_neighborhood`
        FOREIGN KEY (`neighborhood_id`) REFERENCES `neighborhoods` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_alerts_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Örnek Mahalle Uyarısı (Moda Mahallesi için)
INSERT INTO `neighborhood_alerts` (`id`, `neighborhood_id`, `user_id`, `alert_type`, `title`, `description`, `expected_resolution`, `is_active`, `created_at`) VALUES
(1, 1, 1, 'outage', 'Moda Caddesi & Bademaltı Hat Yenileme Kesintisi', 'İSKİ ana şebeke vana yenilemesi nedeniyle mahalle genelinde kısmi kesinti planlanmıştır. Tedbirli olunması rica olunur.', 'Bugün 17:30', 1, NOW())
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

SET FOREIGN_KEY_CHECKS = 1;

