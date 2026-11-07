-- ==========================================================
-- DEBI - Su Tasarrufu Sosyal Medya Platformu
-- Revizyon: 002_social_feed.sql
-- Açıklama: Sosyal medya akışı (posts), damla desteği (post_reactions)
--           ve yorumlar (comments) tabloları
-- ==========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Gönderiler Tablosu
CREATE TABLE IF NOT EXISTS `posts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `neighborhood_id` INT UNSIGNED NOT NULL,
    `apartment_id` INT UNSIGNED DEFAULT NULL COMMENT 'Gönderi apartman içiyse veya apartman üyesi tarafından paylaşıldıysa',
    `visibility` ENUM('apartment', 'neighborhood', 'public') NOT NULL DEFAULT 'neighborhood'
        COMMENT 'apartment: Sadece apartman, neighborhood: Mahalle geneli, public: Tüm Debi',
    `category` ENUM('tip', 'achievement', 'question', 'alert', 'announcement') NOT NULL DEFAULT 'tip'
        COMMENT 'tip: Tasarruf İpucu, achievement: Fatura/Sayaç Başarısı, question: Soru, alert: Arıza/Su Kesintisi, announcement: Yönetici Duyurusu',
    `content` TEXT NOT NULL,
    `water_saved_liters` INT UNSIGNED DEFAULT NULL COMMENT 'Tahmini tasarruf edilen su miktarı (Litre)',
    `is_pinned` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Apartman veya mahalle üstüne sabitlenmiş mi',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_posts_visibility_created` (`visibility`, `created_at`),
    KEY `idx_posts_neighborhood` (`neighborhood_id`, `created_at`),
    KEY `idx_posts_apartment` (`apartment_id`, `created_at`),
    KEY `idx_posts_user` (`user_id`),
    CONSTRAINT `fk_posts_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_posts_neighborhood`
        FOREIGN KEY (`neighborhood_id`) REFERENCES `neighborhoods` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_posts_apartment`
        FOREIGN KEY (`apartment_id`) REFERENCES `apartments` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Gönderi Tepkileri ("Damla" Desteği / Faydalı Bulma)
CREATE TABLE IF NOT EXISTS `post_reactions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `post_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_post_user_reaction` (`post_id`, `user_id`),
    KEY `idx_reactions_user` (`user_id`),
    CONSTRAINT `fk_reactions_post`
        FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_reactions_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Gönderi Yorumları
CREATE TABLE IF NOT EXISTS `comments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `post_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `content` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_comments_post` (`post_id`, `created_at`),
    KEY `idx_comments_user` (`user_id`),
    CONSTRAINT `fk_comments_post`
        FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_comments_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

