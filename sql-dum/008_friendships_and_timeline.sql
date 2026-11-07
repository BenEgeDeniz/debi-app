-- ==========================================================
-- DEBI - Su Tasarrufu Sosyal Medya Platformu
-- Revizyon: 008_friendships_and_timeline.sql
-- Açıklama: Arkadaşlık sistemi (friendships tablosu) ve
--           Gönderi görünürlüğüne 'friends' (Arkadaşlar) seçeneği eklenmesi
-- ==========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Gönderiler tablosundaki visibility alanına 'friends' ekle
ALTER TABLE `posts`
MODIFY COLUMN `visibility` ENUM('apartment', 'friends', 'neighborhood', 'public') NOT NULL DEFAULT 'public'
COMMENT 'apartment: Sadece apartman, friends: Arkadaşlar, public: Genel Debi';

-- Mevcut 'neighborhood' gönderilerini 'public' ve 'friends' akışlarında görünür kılmak için güncelle
UPDATE `posts` SET `visibility` = 'public' WHERE `visibility` = 'neighborhood';

-- 2. Arkadaşlık Tablosu (İstekler ve Kabul Edilen Arkadaşlıklar)
CREATE TABLE IF NOT EXISTS `friendships` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `requester_id` INT UNSIGNED NOT NULL COMMENT 'İsteği gönderen kullanıcı',
    `addressee_id` INT UNSIGNED NOT NULL COMMENT 'İsteği alan kullanıcı',
    `status` ENUM('pending', 'accepted') NOT NULL DEFAULT 'pending',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_friendship_pair` (`requester_id`, `addressee_id`),
    KEY `idx_friendships_addressee_status` (`addressee_id`, `status`),
    KEY `idx_friendships_requester_status` (`requester_id`, `status`),
    CONSTRAINT `fk_friend_requester`
        FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_friend_addressee`
        FOREIGN KEY (`addressee_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Örnek Arkadaşlık Bağlantıları (Demo verileri yüklüyse)
INSERT IGNORE INTO `friendships` (`requester_id`, `addressee_id`, `status`) VALUES
(1, 2, 'accepted'),
(1, 6, 'accepted'),
(2, 7, 'accepted'),
(3, 1, 'pending');

SET FOREIGN_KEY_CHECKS = 1;
