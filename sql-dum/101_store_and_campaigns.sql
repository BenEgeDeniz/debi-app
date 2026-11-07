-- DEBI 101: Puan Kullanım & Yeşil Mağaza (store_items, store_redemptions) ve Anlaşmalı Kampanya Destek (support_campaigns, campaign_supports) Tabloları

CREATE TABLE IF NOT EXISTS `store_items` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` varchar(80) NOT NULL,
  `category` enum('cafe','detergent','eco_product') NOT NULL DEFAULT 'cafe',
  `brand_name` varchar(120) NOT NULL,
  `title` varchar(180) NOT NULL,
  `description` text NOT NULL,
  `eco_badge` varchar(140) NOT NULL,
  `discount_pct` tinyint(3) UNSIGNED NOT NULL DEFAULT 50,
  `cost_points` int(10) UNSIGNED NOT NULL DEFAULT 50,
  `icon_emoji` varchar(16) NOT NULL DEFAULT '🌿',
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
  `spent_points` int(10) UNSIGNED NOT NULL DEFAULT 50,
  `discount_pct` tinyint(3) UNSIGNED NOT NULL DEFAULT 50,
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
  `impact_unit_label` varchar(60) NOT NULL DEFAULT '50 L/m³',
  `cost_points` int(10) UNSIGNED NOT NULL DEFAULT 50,
  `water_contrib_amount` int(10) UNSIGNED NOT NULL DEFAULT 50,
  `target_contrib_amount` int(10) UNSIGNED NOT NULL DEFAULT 5000,
  `current_contrib_amount` int(10) UNSIGNED NOT NULL DEFAULT 1250,
  `supporter_count` int(10) UNSIGNED NOT NULL DEFAULT 25,
  `icon_emoji` varchar(16) NOT NULL DEFAULT '💧',
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
  `contrib_amount` int(10) UNSIGNED NOT NULL DEFAULT 50,
  `impact_label` varchar(60) NOT NULL DEFAULT '50 L/m³',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_campaign_supports_user` (`user_id`),
  KEY `idx_campaign_supports_household` (`household_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `store_items` (`slug`, `category`, `brand_name`, `title`, `description`, `eco_badge`, `discount_pct`, `cost_points`, `icon_emoji`, `sort_order`) VALUES
('cam-fincan-ekokafe', 'cafe', 'Damla & Fincan Yeşil Kafe', 'Cam Bardak ve Porselen Fincan Kullanan Anlaşmalı Kafe İndirimi', 'Tek kullanımlık kağıt ve plastik bardak harcamasını ortadan kaldıran; yalnızca cam bardak ve porselen fincan ile servis yaparak gizli su tüketimini azaltan anlaşmalı kafede tüm içeceklerde %50 indirim.', '☕ Cam Bardak & Fincan · Sıfır Kağıt/Plastik', 50, 50, '☕', 1),
('yesil-damla-roastery', 'cafe', 'Yeşil Damla Roastery & Kafe', 'Kağıt ve Plastik Harcamasını Azaltan Kafe %50 İndirim Kuponu', 'Kendi termosunu getirenlere veya kafe içi cam bardak/fincan tercih edenlere özel; kağıt-plastik atığını ve üretim suyu ayak izini azaltan anlaşmalı kahve mağazasında %50 indirim.', '🥛 Cam Bardak Kullanımı · Su Tasarrufu Dostu', 50, 50, '🍵', 2),
('biodamla-eko-deterjan', 'detergent', 'BioDamla Ekolojik Deterjan', 'Su Ayak İzini Azaltmayı Amaçlamış Bitkisel Deterjan Ürününde %50 İndirim', 'Düşük köpüren formülüyle durulama suyundan %45 tasarruf sağlayan ve gri su kirliliğini önleyerek su ayak izini azaltmayı amaçlamış anlaşmalı deterjan markasının ürününde %50 indirim.', '🫧 Düşük Su Ayak İzi · Ekolojik Deterjan', 50, 50, '🧴', 3),
('aquapure-konsantre-deterjan', 'detergent', 'AquaPure Su Dostu Deterjan', 'Su Tasarrufuna Katkı Sağlayan Konsantre Çamaşır & Bulaşık Deterjanı', 'Susuz konsantre tablet teknolojisiyle hem üretimde hem de evdeki yıkamalarda su tasarrufuna doğrudan katkı sağlayan anlaşmalı deterjan ürününde %50 indirim.', '💧 Su Tasarruflu Durulama · Doğada Çözünür', 50, 50, '🧼', 4),
('ekoakis-perlator-magaza', 'eco_product', 'EkoAkış Su Tasarruf Ekipmanları', 'Su Tasarrufuna Katkı Sağlayan Havalandırmalı Musluk Perlatörü', 'Standart musluklara saniyeler içinde takılan ve su akış hızını düşürmeden %50 su tasarrufu sağlayan anlaşmalı yeşil mağaza ürününde %50 indirim.', '🚰 %50 Musluk Su Tasarrufu · Yeşil Marka', 50, 50, '🚰', 5);

INSERT IGNORE INTO `support_campaigns` (`slug`, `partner_name`, `title`, `description`, `impact_unit_label`, `cost_points`, `water_contrib_amount`, `target_contrib_amount`, `current_contrib_amount`, `supporter_count`, `icon_emoji`, `sort_order`) VALUES
('kuruyan-gollere-can-suyu', 'Debi & Doğa Su Vakfı Anlaşmalı Kampanyası', 'Anlaşmalı Kuruyan Göllere ve Havzalara Can Suyu Kampanyası', '50 puan harcayarak 50 L/m³''lük bir su tasarrufu karşılığını kuruma tehdidi altındaki yerel göl ve sulak alanların restorasyon kampanyasına gönderin.', '50 L/m³', 50, 50, 5000, 1450, 29, '🌊', 1),
('okul-cesmeleri-tasarruf-donusumu', 'Yeşil Okullar & Belediye Su İşbirliği', 'Mahalle Okullarında Tasarruflu Musluk & Gri Su Dönüşüm Kampanyası', '50 puan harcayarak 50 L/m³''lük tasarruf desteğini devlet okullarındaki muslukların fotoselli ve perlatörlü başlıklarla değiştirilmesi kampanyasına gönderin.', '50 L/m³', 50, 50, 3500, 950, 19, '🏫', 2),
('yerel-tarim-damla-sulama', 'Anadolu Su Hasadı & Damla Sulama Girişimi', 'Yerel Çiftçilere Damla Sulama ve Yağmur Suyu Hasadı Destek Kampanyası', '50 puan harcayarak 50 L/m³''lük su tasarrufu katkısını tarımda vahşi sulama yerine damla sulama borularına geçiş yapan anlaşmalı kampanyaya aktarın.', '50 L/m³', 50, 50, 4000, 1100, 22, '🌱', 3);

