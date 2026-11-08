-- DEBI 101: Mağaza (store_items, store_redemptions) ve Anlaşmalı Kampanya Destek (support_campaigns, campaign_supports) Tabloları

CREATE TABLE IF NOT EXISTS `store_items` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` varchar(80) NOT NULL,
  `category` enum('cafe','restaurant','detergent','eco_product') NOT NULL DEFAULT 'cafe',
  `brand_name` varchar(120) NOT NULL,
  `title` varchar(180) NOT NULL,
  `description` text NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `discount_pct` tinyint(3) UNSIGNED NOT NULL DEFAULT 30,
  `cost_points` int(10) UNSIGNED NOT NULL DEFAULT 30,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_store_item_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_redemptions` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `household_id` int(10) UNSIGNED NOT NULL,
  `store_item_id` int(10) UNSIGNED NOT NULL,
  `spent_points` int(10) UNSIGNED NOT NULL DEFAULT 30,
  `discount_pct` tinyint(3) UNSIGNED NOT NULL DEFAULT 30,
  `coupon_code` varchar(40) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_store_redemptions_user` (`user_id`),
  KEY `idx_store_redemptions_household` (`household_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `support_campaigns` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` varchar(80) NOT NULL,
  `partner_name` varchar(140) NOT NULL,
  `title` varchar(180) NOT NULL,
  `description` text NOT NULL,
  `min_points` int(10) UNSIGNED NOT NULL DEFAULT 50,
  `target_dp` int(10) UNSIGNED NOT NULL DEFAULT 5000,
  `current_dp` int(10) UNSIGNED NOT NULL DEFAULT 1250,
  `supporter_count` int(10) UNSIGNED NOT NULL DEFAULT 25,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_support_campaign_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `campaign_supports` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `household_id` int(10) UNSIGNED NOT NULL,
  `campaign_id` int(10) UNSIGNED NOT NULL,
  `spent_points` int(10) UNSIGNED NOT NULL DEFAULT 50,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_campaign_supports_user` (`user_id`),
  KEY `idx_campaign_supports_household` (`household_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `store_items` (`slug`, `category`, `brand_name`, `title`, `description`, `image_path`, `discount_pct`, `cost_points`, `sort_order`) VALUES
('damla-kafe', 'cafe', 'Damla Kafe', '', 'Porselen fincan ile servis yaparak gizli su tüketimini azaltmaktadır.', 'assets/store/damla-kafe.jpg', 30, 30, 1),
('yesil-sofra-restoran', 'restaurant', 'Yeşil Sofra Restoran', '', 'Menüdeki yemek tercihlerini düşük su ayak izli ve mevsimsel içeriklere göre ayarlayarak mutfakta su tüketimini azaltmaktadır.', 'assets/store/yesil-sofra-restoran.jpg', 50, 50, 2),
('aquapure-deterjan', 'detergent', 'AquaPure Su Dostu Deterjan', '', 'Su tasarrufuna katkı sağlayan ve su ayak izini azaltmayı amaçlamış konsantre çamaşır ve bulaşık deterjanı.', 'assets/store/aquapure-deterjan.jpg', 10, 10, 3),
('ekoakis-perlator', 'eco_product', 'EkoAkış', '', 'Havalandırmalı Musluk Perlatörü', 'assets/store/ekoakis-perlator.jpg', 15, 15, 4)
ON DUPLICATE KEY UPDATE
  `brand_name` = VALUES(`brand_name`),
  `title` = VALUES(`title`),
  `description` = VALUES(`description`),
  `image_path` = VALUES(`image_path`),
  `discount_pct` = VALUES(`discount_pct`),
  `cost_points` = VALUES(`cost_points`),
  `sort_order` = VALUES(`sort_order`);

INSERT INTO `support_campaigns` (`slug`, `partner_name`, `title`, `description`, `min_points`, `target_dp`, `current_dp`, `supporter_count`, `sort_order`) VALUES
('okul-cesmeleri-tasarruf-donusumu', 'Yeşil Okullar & Belediye Su İşbirliği', 'Mahalle Okullarında Tasarruflu Musluk & Gri Su Dönüşüm Kampanyası', 'Okullardaki muslukların tasarruflu başlıklarla dönüştürülmesi ve gri su geri kazanım altyapısının kurulması için puanlarınızla destek olabilirsiniz.', 50, 3500, 950, 19, 1),
('kuruyan-gollere-can-suyu', 'Debi & Doğa Su Vakfı İşbirliği', 'Anlaşmalı Kuruyan Göllere ve Havzalara Can Suyu Kampanyası', 'Kuruma tehdidi altındaki yerel göl ve su havzalarının korunması ve ekosistem restorasyonu için puanlarınızla destek gönderebilirsiniz.', 50, 5000, 1450, 29, 2)
ON DUPLICATE KEY UPDATE
  `partner_name` = VALUES(`partner_name`),
  `title` = VALUES(`title`),
  `description` = VALUES(`description`),
  `min_points` = VALUES(`min_points`),
  `target_dp` = VALUES(`target_dp`),
  `sort_order` = VALUES(`sort_order`);
