-- DEBI - Current canonical schema and data dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 07, 2026 at 08:30 PM
-- Server version: 10.6.23-MariaDB-0ubuntu0.22.04.1
-- PHP Version: 8.4.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET FOREIGN_KEY_CHECKS = 0;

-- Bu dosya incremental migration değil, canonical live snapshot'tır.
-- Tekrar import edildiğinde veritabanını bu snapshot ile tamamen eşitler.
DROP TABLE IF EXISTS
  `comments`,
  `friendships`,
  `household_habits`,
  `invitations`,
  `neighborhood_alerts`,
  `point_logs`,
  `post_reactions`,
  `posts`,
  `saving_habits`,
  `water_logs`,
  `users`,
  `households`,
  `apartments`,
  `neighborhoods`,
  `debi_schema_versions`;

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `benegedeniz_debi`
--

-- --------------------------------------------------------

--
-- Table structure for table `apartments`
--

CREATE TABLE `apartments` (
  `id` int(10) UNSIGNED NOT NULL,
  `neighborhood_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL COMMENT 'Apartman veya Site Adı',
  `address_detail` varchar(255) DEFAULT NULL COMMENT 'Sokak / Bina No bilgisi',
  `invite_code` varchar(24) NOT NULL COMMENT 'Genel apartman davet kodu (Örn: DEBI-4829)',
  `total_units` smallint(5) UNSIGNED NOT NULL DEFAULT 10 COMMENT 'Toplam daire sayısı',
  `monthly_goal_m3` decimal(8,2) DEFAULT NULL COMMENT 'Apartman aylık hedef m3 (opsiyonel)',
  `manager_user_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Apartman yöneticisi kullanıcı ID',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `apartments`
--

INSERT INTO `apartments` (`id`, `neighborhood_id`, `name`, `address_detail`, `invite_code`, `total_units`, `monthly_goal_m3`, `manager_user_id`, `created_at`) VALUES
(1, 1, 'Çınar Apartmanı', 'Bademaltı Sok. No: 14', 'DEBI-CINAR', 10, 85.00, 1, '2026-11-07 19:33:44'),
(2, 1, 'Defne Sitesi A Blok', 'Moda Cad. No: 48', 'DEBI-DEFNE', 16, 140.00, 4, '2026-11-07 19:33:44'),
(3, 2, 'Karakoç', NULL, 'DEBI-PSE2A', 5, NULL, 8, '2026-11-07 19:35:02');

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `id` int(10) UNSIGNED NOT NULL,
  `post_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `content` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `comments`
--

INSERT INTO `comments` (`id`, `post_id`, `user_id`, `content`, `created_at`) VALUES
(1, 2, 1, 'Harika bir adım Zeynep Hanım, apartman ortalamamıza da direkt yansıdı.', '2026-11-06 19:33:45'),
(2, 2, 6, 'Hangi model perlatör aldınız acaba? Ben de mutfağa takmak istiyorum.', '2026-11-06 23:33:45'),
(3, 2, 2, 'M24 standart dişli pirinç perlatör aldık Mehmet Bey, hırdavatçılarda kolayca bulunuyor.', '2026-11-07 01:33:45'),
(4, 5, 1, 'Bizim köydeki müstakil evde 12V mini pompa kullanmıştık Selim Bey, yerçekimi tek başına damlamada son noktalara yetişmeyebiliyor.', '2026-11-07 17:33:45');

-- --------------------------------------------------------

--
-- Table structure for table `friendships`
--

CREATE TABLE `friendships` (
  `id` int(10) UNSIGNED NOT NULL,
  `requester_id` int(10) UNSIGNED NOT NULL COMMENT 'İsteği gönderen kullanıcı',
  `addressee_id` int(10) UNSIGNED NOT NULL COMMENT 'İsteği alan kullanıcı',
  `status` enum('pending','accepted') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `friendships`
--

INSERT INTO `friendships` (`id`, `requester_id`, `addressee_id`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 'accepted', '2026-11-07 19:33:50', '2026-11-07 19:33:50'),
(2, 1, 6, 'accepted', '2026-11-07 19:33:50', '2026-11-07 19:33:50'),
(3, 2, 7, 'accepted', '2026-11-07 19:33:50', '2026-11-07 19:33:50'),
(4, 3, 1, 'pending', '2026-11-07 19:33:50', '2026-11-07 19:33:50');

-- --------------------------------------------------------

--
-- Table structure for table `households`
--

CREATE TABLE `households` (
  `id` int(10) UNSIGNED NOT NULL,
  `neighborhood_id` int(10) UNSIGNED NOT NULL,
  `apartment_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'NULL ise mahallede bireysel veya müstakil katılımcı',
  `owner_user_id` int(10) UNSIGNED DEFAULT NULL,
  `housing_type` enum('apartment_unit','individual_unit','detached_house') NOT NULL DEFAULT 'individual_unit' COMMENT 'apartment_unit: Apartmana bağlı daire, individual_unit: Apartmansız bireysel daire, detached_house: Müstakil ev',
  `unit_label` varchar(80) NOT NULL COMMENT 'Örn: Daire 4, Müstakil No:12, Bireysel Hane',
  `resident_count` tinyint(3) UNSIGNED NOT NULL DEFAULT 2 COMMENT 'Hanede yaşayan kişi sayısı (kişi başı hesap için)',
  `unit_invite_code` varchar(32) DEFAULT NULL,
  `total_points` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `households`
--

INSERT INTO `households` (`id`, `neighborhood_id`, `apartment_id`, `owner_user_id`, `housing_type`, `unit_label`, `resident_count`, `unit_invite_code`, `total_points`, `created_at`) VALUES
(1, 1, 1, 1, 'apartment_unit', 'Daire 1 (Yönetici)', 3, 'DAIRE-Z3TN8', 0, '2026-11-07 19:33:44'),
(2, 1, 1, 2, 'apartment_unit', 'Daire 3', 2, 'DAIRE-2YFF2', 0, '2026-11-07 19:33:44'),
(3, 1, 1, 3, 'apartment_unit', 'Daire 7', 4, 'DAIRE-S7AEW', 0, '2026-11-07 19:33:44'),
(4, 1, 2, 4, 'apartment_unit', 'A Blok Daire 4 (Yönetici)', 2, 'DAIRE-349FD', 0, '2026-11-07 19:33:44'),
(5, 1, 2, 5, 'apartment_unit', 'A Blok Daire 9', 3, 'DAIRE-FY7Q5', 0, '2026-11-07 19:33:44'),
(6, 1, NULL, 6, 'individual_unit', 'Bireysel Daire (Sarı Sok. No:6)', 2, 'DAIRE-2JWHZ', 0, '2026-11-07 19:33:44'),
(7, 1, NULL, 7, 'detached_house', 'Müstakil Bahçeli Ev (No: 21)', 4, 'DAIRE-TL7BC', 0, '2026-11-07 19:33:44'),
(8, 2, 3, 8, 'apartment_unit', '5', 3, 'DAIRE-QUF55', 10, '2026-11-07 19:35:02');

-- --------------------------------------------------------

--
-- Table structure for table `household_habits`
--

CREATE TABLE `household_habits` (
  `household_id` int(10) UNSIGNED NOT NULL,
  `habit_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `household_habits`
--

INSERT INTO `household_habits` (`household_id`, `habit_id`, `created_at`) VALUES
(1, 1, '2026-11-07 19:33:44'),
(1, 3, '2026-11-07 19:33:44'),
(2, 1, '2026-11-07 19:33:44'),
(2, 2, '2026-11-07 19:33:44'),
(2, 4, '2026-11-07 19:33:44'),
(4, 1, '2026-11-07 19:33:44'),
(5, 2, '2026-11-07 19:33:44'),
(6, 1, '2026-11-07 19:33:44'),
(7, 5, '2026-11-07 19:33:44'),
(7, 6, '2026-11-07 19:33:44');

-- --------------------------------------------------------

--
-- Table structure for table `invitations`
--

CREATE TABLE `invitations` (
  `id` int(10) UNSIGNED NOT NULL,
  `apartment_id` int(10) UNSIGNED NOT NULL,
  `unit_label` varchar(80) NOT NULL COMMENT 'Örn: Daire 5',
  `invite_token` varchar(32) NOT NULL COMMENT 'Daireye özel davet kodu',
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  `used_by_user_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `used_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `invitations`
--

INSERT INTO `invitations` (`id`, `apartment_id`, `unit_label`, `invite_token`, `is_used`, `used_by_user_id`, `created_at`, `used_at`) VALUES
(1, 1, 'Daire 4', 'DAIRE-C4X1', 0, NULL, '2026-11-07 19:33:44', NULL),
(2, 1, 'Daire 5', 'DAIRE-C5Y2', 0, NULL, '2026-11-07 19:33:44', NULL),
(3, 1, 'Daire 6', 'DAIRE-C6Z3', 0, NULL, '2026-11-07 19:33:44', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `neighborhoods`
--

CREATE TABLE `neighborhoods` (
  `id` int(10) UNSIGNED NOT NULL,
  `city` varchar(80) NOT NULL COMMENT 'İl (Örn: İstanbul)',
  `district` varchar(80) NOT NULL COMMENT 'İlçe (Örn: Kadıköy)',
  `name` varchar(120) NOT NULL COMMENT 'Mahalle Adı (Örn: Moda Mah.)',
  `slug` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `neighborhoods`
--

INSERT INTO `neighborhoods` (`id`, `city`, `district`, `name`, `slug`, `created_at`) VALUES
(1, 'İstanbul', 'Kadıköy', 'Moda Mah.', 'istanbul-kadikoy-moda-mah', '2026-11-07 19:33:44'),
(2, 'İzmir', 'Karabağlar', 'Arap Hasan Mah.', 'izmir-karabaglar-arap-hasan-mah', '2026-11-07 19:35:02');

-- --------------------------------------------------------

--
-- Table structure for table `neighborhood_alerts`
--

CREATE TABLE `neighborhood_alerts` (
  `id` int(10) UNSIGNED NOT NULL,
  `neighborhood_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `alert_type` enum('outage','low_pressure','pipe_leak','contamination') NOT NULL DEFAULT 'outage' COMMENT 'outage: Planlı/Plansız Kesinti, low_pressure: Düşük Basınç, pipe_leak: Sokak/Bina Ana Hat Sızıntısı, contamination: Bulanıklık/Koku',
  `title` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `expected_resolution` varchar(100) DEFAULT NULL COMMENT 'Örn: Bugün 18:00 tahmini',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `resolved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `neighborhood_alerts`
--

INSERT INTO `neighborhood_alerts` (`id`, `neighborhood_id`, `user_id`, `alert_type`, `title`, `description`, `expected_resolution`, `is_active`, `created_at`, `resolved_at`) VALUES
(1, 1, 1, 'outage', 'Moda Caddesi & Bademaltı Hat Yenileme Kesintisi', 'İSKİ ana şebeke vana yenilemesi nedeniyle mahalle genelinde kısmi kesinti planlanmıştır. Tedbirli olunması rica olunur.', 'Bugün 17:30', 1, '2026-11-07 19:33:46', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `point_logs`
--

CREATE TABLE `point_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `household_id` int(10) UNSIGNED NOT NULL,
  `action_code` varchar(50) NOT NULL COMMENT 'Örn: welcome_bonus, daily_quest, water_log_entry, post_create',
  `points` int(11) NOT NULL COMMENT 'Kazanılan Damla Puanı (+DP)',
  `description` varchar(255) NOT NULL,
  `ref_key` varchar(100) DEFAULT NULL COMMENT 'Tekil aksiyon anahtarı (mükerrer puanı önlemek için)',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `point_logs`
--

INSERT INTO `point_logs` (`id`, `user_id`, `household_id`, `action_code`, `points`, `description`, `ref_key`, `created_at`) VALUES
(3, 8, 8, 'bill_drops_earned', 10, '💧 Aralık 2023 faturasından +10 Puan', 'bill_drops_2023_12', '2026-11-07 19:57:37');

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `neighborhood_id` int(10) UNSIGNED NOT NULL,
  `apartment_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Gönderi apartman içiyse veya apartman üyesi tarafından paylaşıldıysa',
  `visibility` enum('apartment','friends','neighborhood','public') NOT NULL DEFAULT 'public' COMMENT 'apartment: Sadece apartman, friends: Arkadaşlar, public: Genel Debi',
  `category` enum('tip','achievement','question','alert','announcement') NOT NULL DEFAULT 'tip' COMMENT 'tip: Tasarruf İpucu, achievement: Fatura/Sayaç Başarısı, question: Soru, alert: Arıza/Su Kesintisi, announcement: Yönetici Duyurusu',
  `content` text NOT NULL,
  `water_saved_liters` int(10) UNSIGNED DEFAULT NULL COMMENT 'Tahmini tasarruf edilen su miktarı (Litre)',
  `image_path` varchar(255) DEFAULT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Apartman veya mahalle üstüne sabitlenmiş mi',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `posts`
--

INSERT INTO `posts` (`id`, `user_id`, `neighborhood_id`, `apartment_id`, `visibility`, `category`, `content`, `water_saved_liters`, `image_path`, `is_pinned`, `created_at`) VALUES
(1, 1, 1, 1, 'apartment', 'announcement', 'Komşular selamlar, apartmanımızın ana giriş vanasına basınç düşürücü regülatör taktırdık. Yüksek basınçtan kaynaklanan gereksiz su kaybı ve boru vuruntuları önlenecek. Sayaçlara olumlu yansıyacaktır.', 2500, NULL, 1, '2026-11-03 19:33:44'),
(2, 2, 1, 1, 'public', 'tip', 'Mutfak musluğuna 25 TL\'ye aldığımız perlatörü taktık. Tazyik hissi hiç azalmadı ama debi neredeyse yarı yarıya düştü. Faturamızda net 1.5 m³ tasarruf gördük, tüm mahalleye tavsiye ederim!', 1500, NULL, 0, '2026-11-05 19:33:44'),
(3, 4, 1, 2, 'public', 'alert', 'Moda Caddesi tarafında ana hatta küçük bir bakım çalışması var. Yarın 14:00 - 17:00 arası kısa süreli basınç düşmesi yaşanabilirmiş, bilginiz olsun.', NULL, NULL, 0, '2026-11-06 19:33:44'),
(4, 6, 1, NULL, 'public', 'achievement', 'Mahallede bireysel katılımcı olarak bu ayki faturamızı girdik: Kişi başı günlük 85 Litreye indik. Komşularla tatlı rekabet tasarruf yapma motivasyonunu çok artırıyor!', 800, NULL, 0, '2026-11-07 11:33:44'),
(5, 7, 1, NULL, 'public', 'question', 'Bahçesi olan komşulara bir soru: Damlama sulama sistemini yağmur suyu deposuna bağlayan oldu mu? Basınç yeterli geliyor mu yoksa küçük bir mini hidrofor şart mı?', NULL, NULL, 0, '2026-11-07 16:33:44'),
(6, 8, 2, 3, 'apartment', 'achievement', '💧 Aralık 2023 (İZSU): 10 m³ (107.5 L/gün). +10 Damla!', NULL, NULL, 0, '2026-11-07 19:57:37');

-- --------------------------------------------------------

--
-- Table structure for table `post_reactions`
--

CREATE TABLE `post_reactions` (
  `id` int(10) UNSIGNED NOT NULL,
  `post_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `post_reactions`
--

INSERT INTO `post_reactions` (`id`, `post_id`, `user_id`, `created_at`) VALUES
(1, 1, 2, '2026-11-07 19:33:44'),
(2, 1, 3, '2026-11-07 19:33:44'),
(3, 2, 1, '2026-11-07 19:33:44'),
(4, 2, 4, '2026-11-07 19:33:44'),
(5, 2, 6, '2026-11-07 19:33:44'),
(6, 3, 1, '2026-11-07 19:33:44'),
(7, 3, 2, '2026-11-07 19:33:44'),
(8, 4, 1, '2026-11-07 19:33:44'),
(9, 4, 2, '2026-11-07 19:33:44'),
(10, 4, 7, '2026-11-07 19:33:44');

-- --------------------------------------------------------

--
-- Table structure for table `saving_habits`
--

CREATE TABLE `saving_habits` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `title` varchar(140) NOT NULL,
  `description` varchar(255) NOT NULL,
  `estimated_monthly_liters` int(10) UNSIGNED NOT NULL DEFAULT 300
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `saving_habits`
--

INSERT INTO `saving_habits` (`id`, `code`, `title`, `description`, `estimated_monthly_liters`) VALUES
(1, 'aerator_faucet', 'Musluklarda Perlatör (Hava Karıştırıcı) Kullanımı', 'Mutfak ve banyo musluklarına takılan perlatör ile debiyi %40 azaltır.', 1200),
(2, 'short_shower', 'Duş Süresini 5 Dakikanın Altında Tutmak', 'Her duşta ortalama 45-60 litre su tasarrufu sağlar.', 1500),
(3, 'dual_flush', 'Çift Kademeli Rezervuar / Şişe Yöntemi', 'Sifon çekimlerinde kullanılan su miktarını yarıya indirir.', 900),
(4, 'full_dishwasher', 'Bulaşık ve Çamaşır Makinesini Tam Dolu Çalıştırmak', 'Elde yıkamaya ve yarım makineye göre ayda yüzlerce litre korur.', 1100),
(5, 'cold_water_bucket', 'Duşta Sıcak Su Gelene Kadar Akan Suyu Kovada Biriktirmek', 'Isınmasını beklerken boşa akan suyu temizlik veya çiçek sulamada kullanmak.', 450),
(6, 'leak_check', 'Damlatan Musluk ve Sızdıran Rezervuar Kontrolü', 'Saniyede bir damla su ayda yaklaşık 750 litre kayba yol açar.', 750);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `household_id` int(10) UNSIGNED NOT NULL,
  `neighborhood_id` int(10) UNSIGNED NOT NULL,
  `apartment_id` int(10) UNSIGNED DEFAULT NULL,
  `full_name` varchar(120) NOT NULL,
  `email` varchar(160) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('manager','resident') NOT NULL DEFAULT 'resident' COMMENT 'manager: Apartman Yöneticisi, resident: Sakin/Bireysel',
  `bio` varchar(255) DEFAULT NULL,
  `avatar_color` varchar(16) NOT NULL DEFAULT '#1b5670',
  `avatar_photo` varchar(255) DEFAULT NULL COMMENT 'Profil fotoğrafı dosya yolu',
  `cover_photo` varchar(255) DEFAULT NULL COMMENT 'Zaman tüneli kapak fotoğrafı dosya yolu',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `household_id`, `neighborhood_id`, `apartment_id`, `full_name`, `email`, `password_hash`, `role`, `bio`, `avatar_color`, `avatar_photo`, `cover_photo`, `created_at`) VALUES
(1, 1, 1, 1, 'Ali Kaya', 'ali@debi.test', '$2y$10$wT02jWb20z1Vp4e9c7V5feQzJg7bZ28pY5hQy9b4zH8bY.vQj6WlG', 'manager', 'Çınar Apartmanı Yöneticisi', '#1b5670', NULL, NULL, '2026-11-07 19:33:44'),
(2, 2, 1, 1, 'Zeynep Aksoy', 'zeynep@debi.test', '$2y$10$wT02jWb20z1Vp4e9c7V5feQzJg7bZ28pY5hQy9b4zH8bY.vQj6WlG', 'resident', 'Mutfakta perlatör kullanıyoruz', '#2b6e46', NULL, NULL, '2026-11-07 19:33:44'),
(3, 3, 1, 1, 'Burak Öztürk', 'burak@debi.test', '$2y$10$wT02jWb20z1Vp4e9c7V5feQzJg7bZ28pY5hQy9b4zH8bY.vQj6WlG', 'resident', 'Güneş enerjisi & tasarruflu sifon', '#7a4b2a', NULL, NULL, '2026-11-07 19:33:44'),
(4, 4, 1, 2, 'Ayşe Demir', 'ayse@debi.test', '$2y$10$wT02jWb20z1Vp4e9c7V5feQzJg7bZ28pY5hQy9b4zH8bY.vQj6WlG', 'manager', 'Defne Sitesi Yöneticisi', '#3b5968', NULL, NULL, '2026-11-07 19:33:44'),
(5, 5, 1, 2, 'Emre Şahin', 'emre@debi.test', '$2y$10$wT02jWb20z1Vp4e9c7V5feQzJg7bZ28pY5hQy9b4zH8bY.vQj6WlG', 'resident', 'Duşta kova biriktirme alışkanlığı', '#4f6248', NULL, NULL, '2026-11-07 19:33:44'),
(6, 6, 1, NULL, 'Mehmet Can', 'mehmet@debi.test', '$2y$10$wT02jWb20z1Vp4e9c7V5feQzJg7bZ28pY5hQy9b4zH8bY.vQj6WlG', 'resident', 'Apartmanımız katılana kadar mahallede bireysel üyeyim', '#8a5a36', NULL, NULL, '2026-11-07 19:33:44'),
(7, 7, 1, NULL, 'Selim Korkmaz', 'selim@debi.test', '$2y$10$wT02jWb20z1Vp4e9c7V5feQzJg7bZ28pY5hQy9b4zH8bY.vQj6WlG', 'resident', 'Müstakil evde yağmur hasadı yapıyoruz', '#1b5670', NULL, NULL, '2026-11-07 19:33:44'),
(8, 8, 2, 3, 'Ege Deniz Kaçar', 'apt@test.com', '$2y$12$mzT4duRAl5yl6L5GCwWbqOvh6IS94OJlQ7393zpIgMvM/rkHPxzRu', 'manager', NULL, '#3b5968', NULL, NULL, '2026-11-07 19:35:03');

-- --------------------------------------------------------

--
-- Table structure for table `water_logs`
--

CREATE TABLE `water_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `household_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `neighborhood_id` int(10) UNSIGNED NOT NULL,
  `apartment_id` int(10) UNSIGNED DEFAULT NULL,
  `period_year` smallint(5) UNSIGNED NOT NULL COMMENT 'Örn: 2026',
  `period_month` tinyint(3) UNSIGNED NOT NULL COMMENT '1-12 arası ay',
  `consumption_m3` decimal(7,2) NOT NULL COMMENT 'Aylık tüketim (Metreküp m3)',
  `previous_consumption_m3` decimal(8,2) DEFAULT NULL COMMENT 'Geçen ayki tüketim (m3)',
  `saved_m3` decimal(8,2) NOT NULL DEFAULT 0.00 COMMENT 'Tasarruf edilen su (gecen_ay_m3 - bu_ay_m3)',
  `saved_liters` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Kurtarılan su (Litre = saved_m3 * 1000)',
  `saving_pct` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Önceki aya göre tasarruf yüzdesi (%)',
  `earned_points` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Fatura farkından kazanılan Debi Puanı (+10 Puan)',
  `ai_verified` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Yapay zeka verisi değiştirilmeden onaylandı mı',
  `resident_count_snapshot` tinyint(3) UNSIGNED NOT NULL DEFAULT 2 COMMENT 'Kayıt anındaki hane kişi sayısı',
  `daily_liters_per_person` decimal(7,1) NOT NULL COMMENT '(consumption_m3 * 1000) / (30 * resident_count)',
  `bill_amount` decimal(9,2) DEFAULT NULL COMMENT 'Fatura tutarı (TL - opsiyonel)',
  `bill_image_path` varchar(255) DEFAULT NULL COMMENT 'Yüklenen su faturası fotoğrafı',
  `provider_name` varchar(100) DEFAULT NULL COMMENT 'Su idaresi adı (Örn: İSKİ, ASKİ, İZSU)',
  `reading_days` int(10) UNSIGNED NOT NULL DEFAULT 30 COMMENT 'Fatura okuma gün sayısı',
  `notes` varchar(255) DEFAULT NULL COMMENT 'Bu ay yapılan tasarruf notu',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `water_logs`
--

INSERT INTO `water_logs` (`id`, `household_id`, `user_id`, `neighborhood_id`, `apartment_id`, `period_year`, `period_month`, `consumption_m3`, `previous_consumption_m3`, `saved_m3`, `saved_liters`, `saving_pct`, `earned_points`, `ai_verified`, `resident_count_snapshot`, `daily_liters_per_person`, `bill_amount`, `bill_image_path`, `provider_name`, `reading_days`, `notes`, `created_at`) VALUES
(1, 1, 1, 1, 1, 2026, 10, 8.40, NULL, 0.00, 0, 0.00, 0, 0, 3, 93.3, 310.00, NULL, NULL, 30, 'Klozet sızıntısını tamir ettik.', '2026-11-07 19:33:44'),
(2, 2, 2, 1, 1, 2026, 10, 4.80, NULL, 0.00, 0, 0.00, 0, 0, 2, 80.0, 185.00, NULL, NULL, 30, 'Perlatörler sayesinde tüketim düştü.', '2026-11-07 19:33:44'),
(3, 3, 3, 1, 1, 2026, 10, 14.50, NULL, 0.00, 0, 0.00, 0, 0, 4, 120.8, 520.00, NULL, NULL, 30, 'Misafir vardı bu ay biraz arttı.', '2026-11-07 19:33:44'),
(4, 4, 4, 1, 2, 2026, 10, 5.20, NULL, 0.00, 0, 0.00, 0, 0, 2, 86.7, 195.00, NULL, NULL, 30, 'Bahçe sulamasını damlamaya geçirdik.', '2026-11-07 19:33:44'),
(5, 5, 5, 1, 2, 2026, 10, 8.90, NULL, 0.00, 0, 0.00, 0, 0, 3, 98.9, 330.00, NULL, NULL, 30, 'Çamaşır makinesini tam dolum yaptık.', '2026-11-07 19:33:44'),
(6, 6, 6, 1, NULL, 2026, 10, 5.40, NULL, 0.00, 0, 0.00, 0, 0, 2, 90.0, 205.00, NULL, NULL, 30, 'Bireysel sayaç takibi.', '2026-11-07 19:33:44'),
(7, 7, 7, 1, NULL, 2026, 10, 11.20, NULL, 0.00, 0, 0.00, 0, 0, 4, 93.3, 420.00, NULL, NULL, 30, 'Yağmur suyu ile bahçe sulandı.', '2026-11-07 19:33:44'),
(8, 1, 1, 1, 1, 2026, 11, 7.80, NULL, 0.00, 0, 0.00, 0, 0, 3, 86.7, 290.00, NULL, NULL, 30, 'Basınç düşürücü regülatör takıldı.', '2026-11-07 19:33:44'),
(9, 2, 2, 1, 1, 2026, 11, 4.50, NULL, 0.00, 0, 0.00, 0, 0, 2, 75.0, 175.00, NULL, NULL, 30, 'Kişi başı 75 litreye kadar indik!', '2026-11-07 19:33:44'),
(10, 3, 3, 1, 1, 2026, 11, 12.00, NULL, 0.00, 0, 0.00, 0, 0, 4, 100.0, 440.00, NULL, NULL, 30, 'Düşüş sağladık, 100 L bandındayız.', '2026-11-07 19:33:44'),
(11, 4, 4, 1, 2, 2026, 11, 4.90, NULL, 0.00, 0, 0.00, 0, 0, 2, 81.7, 190.00, NULL, NULL, 30, 'Sabit tasarruf devam ediyor.', '2026-11-07 19:33:44'),
(12, 5, 5, 1, 2, 2026, 11, 8.10, NULL, 0.00, 0, 0.00, 0, 0, 3, 90.0, 305.00, NULL, NULL, 30, 'Duş süresini 5 dakikaya indirdik.', '2026-11-07 19:33:44'),
(13, 6, 6, 1, NULL, 2026, 11, 5.10, NULL, 0.00, 0, 0.00, 0, 0, 2, 85.0, 195.00, NULL, NULL, 30, 'Mahallede bireysel yarışıyoruz.', '2026-11-07 19:33:44'),
(14, 7, 7, 1, NULL, 2026, 11, 9.80, NULL, 0.00, 0, 0.00, 0, 0, 4, 81.7, 380.00, NULL, NULL, 30, 'Şebeke suyunu sulamada hiç kullanmadık.', '2026-11-07 19:33:44'),
(15, 8, 8, 2, 3, 2023, 12, 10.00, NULL, 0.00, 0, 0.00, 10, 1, 3, 107.5, 715.00, 'uploads/bills/bill_20261107_195726_41d9932985.jpg', 'İZSU', 31, NULL, '2026-11-07 19:57:37');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `apartments`
--
ALTER TABLE `apartments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_apartment_invite_code` (`invite_code`),
  ADD KEY `idx_apartment_neighborhood` (`neighborhood_id`);

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_comments_post` (`post_id`,`created_at`),
  ADD KEY `idx_comments_user` (`user_id`);

-- Indexes for table `friendships`
--
ALTER TABLE `friendships`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_friendship_pair` (`requester_id`,`addressee_id`),
  ADD KEY `idx_friendships_addressee_status` (`addressee_id`,`status`),
  ADD KEY `idx_friendships_requester_status` (`requester_id`,`status`);

--
-- Indexes for table `households`
--
ALTER TABLE `households`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_households_unit_invite_code` (`unit_invite_code`),
  ADD KEY `idx_household_neighborhood` (`neighborhood_id`),
  ADD KEY `idx_household_apartment` (`apartment_id`);

--
-- Indexes for table `household_habits`
--
ALTER TABLE `household_habits`
  ADD PRIMARY KEY (`household_id`,`habit_id`),
  ADD KEY `fk_hh_habit` (`habit_id`);

--
-- Indexes for table `invitations`
--
ALTER TABLE `invitations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_invitation_token` (`invite_token`),
  ADD KEY `idx_invitation_apartment` (`apartment_id`),
  ADD KEY `fk_invitations_user` (`used_by_user_id`);

--
-- Indexes for table `neighborhoods`
--
ALTER TABLE `neighborhoods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_neighborhood_location` (`city`,`district`,`name`),
  ADD KEY `idx_neighborhood_slug` (`slug`);

--
-- Indexes for table `neighborhood_alerts`
--
ALTER TABLE `neighborhood_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_alerts_neighborhood_active` (`neighborhood_id`,`is_active`,`created_at`),
  ADD KEY `fk_alerts_user` (`user_id`);

--
-- Indexes for table `point_logs`
--
ALTER TABLE `point_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_ref_key` (`user_id`,`ref_key`),
  ADD KEY `idx_point_logs_user` (`user_id`,`created_at`),
  ADD KEY `idx_point_logs_household` (`household_id`,`created_at`);

--
-- Indexes for table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_posts_visibility_created` (`visibility`,`created_at`),
  ADD KEY `idx_posts_neighborhood` (`neighborhood_id`,`created_at`),
  ADD KEY `idx_posts_apartment` (`apartment_id`,`created_at`),
  ADD KEY `idx_posts_user` (`user_id`);

--
-- Indexes for table `post_reactions`
--
ALTER TABLE `post_reactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_post_user_reaction` (`post_id`,`user_id`),
  ADD KEY `idx_reactions_user` (`user_id`);

--
-- Indexes for table `saving_habits`
--
ALTER TABLE `saving_habits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_habit_code` (`code`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `idx_users_household` (`household_id`),
  ADD KEY `idx_users_neighborhood` (`neighborhood_id`),
  ADD KEY `idx_users_apartment` (`apartment_id`);

--
-- Indexes for table `water_logs`
--
ALTER TABLE `water_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_household_period` (`household_id`,`period_year`,`period_month`),
  ADD KEY `idx_water_neighborhood_period` (`neighborhood_id`,`period_year`,`period_month`),
  ADD KEY `idx_water_apartment_period` (`apartment_id`,`period_year`,`period_month`),
  ADD KEY `fk_water_user` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `apartments`
--
ALTER TABLE `apartments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `friendships`
--
ALTER TABLE `friendships`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `households`
--
ALTER TABLE `households`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `invitations`
--
ALTER TABLE `invitations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `neighborhoods`
--
ALTER TABLE `neighborhoods`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `neighborhood_alerts`
--
ALTER TABLE `neighborhood_alerts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `point_logs`
--
ALTER TABLE `point_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `post_reactions`
--
ALTER TABLE `post_reactions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `saving_habits`
--
ALTER TABLE `saving_habits`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `water_logs`
--
ALTER TABLE `water_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `apartments`
--
ALTER TABLE `apartments`
  ADD CONSTRAINT `fk_apartments_neighborhood` FOREIGN KEY (`neighborhood_id`) REFERENCES `neighborhoods` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `fk_comments_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `friendships`
--
ALTER TABLE `friendships`
  ADD CONSTRAINT `fk_friend_addressee` FOREIGN KEY (`addressee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_friend_requester` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `households`
--
ALTER TABLE `households`
  ADD CONSTRAINT `fk_households_apartment` FOREIGN KEY (`apartment_id`) REFERENCES `apartments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_households_neighborhood` FOREIGN KEY (`neighborhood_id`) REFERENCES `neighborhoods` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `household_habits`
--
ALTER TABLE `household_habits`
  ADD CONSTRAINT `fk_hh_habit` FOREIGN KEY (`habit_id`) REFERENCES `saving_habits` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_hh_household` FOREIGN KEY (`household_id`) REFERENCES `households` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `invitations`
--
ALTER TABLE `invitations`
  ADD CONSTRAINT `fk_invitations_apartment` FOREIGN KEY (`apartment_id`) REFERENCES `apartments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_invitations_user` FOREIGN KEY (`used_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `neighborhood_alerts`
--
ALTER TABLE `neighborhood_alerts`
  ADD CONSTRAINT `fk_alerts_neighborhood` FOREIGN KEY (`neighborhood_id`) REFERENCES `neighborhoods` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_alerts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `point_logs`
--
ALTER TABLE `point_logs`
  ADD CONSTRAINT `fk_pl_household` FOREIGN KEY (`household_id`) REFERENCES `households` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pl_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `posts`
--
ALTER TABLE `posts`
  ADD CONSTRAINT `fk_posts_apartment` FOREIGN KEY (`apartment_id`) REFERENCES `apartments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_posts_neighborhood` FOREIGN KEY (`neighborhood_id`) REFERENCES `neighborhoods` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_posts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `post_reactions`
--
ALTER TABLE `post_reactions`
  ADD CONSTRAINT `fk_reactions_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_apartment` FOREIGN KEY (`apartment_id`) REFERENCES `apartments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_users_household` FOREIGN KEY (`household_id`) REFERENCES `households` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_users_neighborhood` FOREIGN KEY (`neighborhood_id`) REFERENCES `neighborhoods` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `water_logs`
--
ALTER TABLE `water_logs`
  ADD CONSTRAINT `fk_water_apartment` FOREIGN KEY (`apartment_id`) REFERENCES `apartments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_water_household` FOREIGN KEY (`household_id`) REFERENCES `households` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_water_neighborhood` FOREIGN KEY (`neighborhood_id`) REFERENCES `neighborhoods` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_water_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
