-- ==========================================================
-- DEBI - Su Tasarrufu Sosyal Medya Platformu
-- Revizyon: 004_sample_data.sql
-- Açıklama: Test ve demo için örnek mahalle, apartmanlar,
--           bireysel haneler, su sayaç kayıtları ve sosyal paylaşımlar.
-- Tüm örnek kullanıcıların şifresi: debi123
-- ==========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Örnek Mahalle: İstanbul / Kadıköy / Moda Mah.
INSERT INTO `neighborhoods` (`id`, `city`, `district`, `name`, `slug`, `created_at`) VALUES
(1, 'İstanbul', 'Kadıköy', 'Moda Mah.', 'istanbul-kadikoy-moda-mah', NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 2. Örnek Apartmanlar (Moda Mahallesine Bağlı)
-- Çınar Apartmanı (Davet Kodu: DEBI-CINAR)
-- Defne Sitesi A Blok (Davet Kodu: DEBI-DEFNE)
INSERT INTO `apartments` (`id`, `neighborhood_id`, `name`, `address_detail`, `invite_code`, `total_units`, `monthly_goal_m3`, `manager_user_id`, `created_at`) VALUES
(1, 1, 'Çınar Apartmanı', 'Bademaltı Sok. No: 14', 'DEBI-CINAR', 10, 85.00, 1, NOW()),
(2, 1, 'Defne Sitesi A Blok', 'Moda Cad. No: 48', 'DEBI-DEFNE', 16, 140.00, 4, NOW())
ON DUPLICATE KEY UPDATE `invite_code` = VALUES(`invite_code`);

-- 3. Örnek Haneler (Daireler, Bireysel Daire ve Müstakil Ev)
INSERT INTO `households` (`id`, `neighborhood_id`, `apartment_id`, `housing_type`, `unit_label`, `resident_count`, `created_at`) VALUES
-- Çınar Apartmanı Haneleri
(1, 1, 1, 'apartment_unit', 'Daire 1 (Yönetici)', 3, NOW()),
(2, 1, 1, 'apartment_unit', 'Daire 3', 2, NOW()),
(3, 1, 1, 'apartment_unit', 'Daire 7', 4, NOW()),
-- Defne Sitesi Haneleri
(4, 1, 2, 'apartment_unit', 'A Blok Daire 4 (Yönetici)', 2, NOW()),
(5, 1, 2, 'apartment_unit', 'A Blok Daire 9', 3, NOW()),
-- Mahallede Bireysel Katılımcı (Apartmanı henüz katılmamış)
(6, 1, NULL, 'individual_unit', 'Bireysel Daire (Sarı Sok. No:6)', 2, NOW()),
-- Mahallede Müstakil Ev
(7, 1, NULL, 'detached_house', 'Müstakil Bahçeli Ev (No: 21)', 4, NOW())
ON DUPLICATE KEY UPDATE `unit_label` = VALUES(`unit_label`);

-- 4. Örnek Kullanıcılar (Şifre: debi123 -> $2y$10$wT02jWb20z1Vp4e9c7V5feQzJg7bZ28pY5hQy9b4zH8bY.vQj6WlG)
INSERT INTO `users` (`id`, `household_id`, `neighborhood_id`, `apartment_id`, `full_name`, `email`, `password_hash`, `role`, `bio`, `avatar_color`, `created_at`) VALUES
(1, 1, 1, 1, 'Ali Kaya', 'ali@debi.test', '$2y$10$wT02jWb20z1Vp4e9c7V5feQzJg7bZ28pY5hQy9b4zH8bY.vQj6WlG', 'manager', 'Çınar Apartmanı Yöneticisi', '#1b5670', NOW()),
(2, 2, 1, 1, 'Zeynep Aksoy', 'zeynep@debi.test', '$2y$10$wT02jWb20z1Vp4e9c7V5feQzJg7bZ28pY5hQy9b4zH8bY.vQj6WlG', 'resident', 'Mutfakta perlatör kullanıyoruz', '#2b6e46', NOW()),
(3, 3, 1, 1, 'Burak Öztürk', 'burak@debi.test', '$2y$10$wT02jWb20z1Vp4e9c7V5feQzJg7bZ28pY5hQy9b4zH8bY.vQj6WlG', 'resident', 'Güneş enerjisi & tasarruflu sifon', '#7a4b2a', NOW()),
(4, 4, 1, 2, 'Ayşe Demir', 'ayse@debi.test', '$2y$10$wT02jWb20z1Vp4e9c7V5feQzJg7bZ28pY5hQy9b4zH8bY.vQj6WlG', 'manager', 'Defne Sitesi Yöneticisi', '#3b5968', NOW()),
(5, 5, 1, 2, 'Emre Şahin', 'emre@debi.test', '$2y$10$wT02jWb20z1Vp4e9c7V5feQzJg7bZ28pY5hQy9b4zH8bY.vQj6WlG', 'resident', 'Duşta kova biriktirme alışkanlığı', '#4f6248', NOW()),
(6, 6, 1, NULL, 'Mehmet Can', 'mehmet@debi.test', '$2y$10$wT02jWb20z1Vp4e9c7V5feQzJg7bZ28pY5hQy9b4zH8bY.vQj6WlG', 'resident', 'Apartmanımız katılana kadar mahallede bireysel üyeyim', '#8a5a36', NOW()),
(7, 7, 1, NULL, 'Selim Korkmaz', 'selim@debi.test', '$2y$10$wT02jWb20z1Vp4e9c7V5feQzJg7bZ28pY5hQy9b4zH8bY.vQj6WlG', 'resident', 'Müstakil evde yağmur hasadı yapıyoruz', '#1b5670', NOW())
ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`);

-- 5. Daireye Özel Örnek Davet Kodları
INSERT INTO `invitations` (`apartment_id`, `unit_label`, `invite_token`, `is_used`, `used_by_user_id`, `created_at`, `used_at`) VALUES
(1, 'Daire 4', 'DAIRE-C4X1', 0, NULL, NOW(), NULL),
(1, 'Daire 5', 'DAIRE-C5Y2', 0, NULL, NOW(), NULL),
(1, 'Daire 6', 'DAIRE-C6Z3', 0, NULL, NOW(), NULL)
ON DUPLICATE KEY UPDATE `unit_label` = VALUES(`unit_label`);

-- 6. Örnek Su Sayaç Verileri (Geçerli Ay ve Bir Önceki Ay)
-- Hesap formülü: (consumption_m3 * 1000) / (30 * resident_count)
INSERT INTO `water_logs` (`household_id`, `user_id`, `neighborhood_id`, `apartment_id`, `period_year`, `period_month`, `consumption_m3`, `resident_count_snapshot`, `daily_liters_per_person`, `bill_amount`, `notes`, `created_at`) VALUES
-- 2026 yılı 10. Ay (Ekim)
(1, 1, 1, 1, 2026, 10, 8.40, 3, 93.3, 310.00, 'Klozet sızıntısını tamir ettik.', NOW()),
(2, 2, 1, 1, 2026, 10, 4.80, 2, 80.0, 185.00, 'Perlatörler sayesinde tüketim düştü.', NOW()),
(3, 3, 1, 1, 2026, 10, 14.50, 4, 120.8, 520.00, 'Misafir vardı bu ay biraz arttı.', NOW()),
(4, 4, 1, 2, 2026, 10, 5.20, 2, 86.7, 195.00, 'Bahçe sulamasını damlamaya geçirdik.', NOW()),
(5, 5, 1, 2, 2026, 10, 8.90, 3, 98.9, 330.00, 'Çamaşır makinesini tam dolum yaptık.', NOW()),
(6, 6, 1, NULL, 2026, 10, 5.40, 2, 90.0, 205.00, 'Bireysel sayaç takibi.', NOW()),
(7, 7, 1, NULL, 2026, 10, 11.20, 4, 93.3, 420.00, 'Yağmur suyu ile bahçe sulandı.', NOW()),
-- 2026 yılı 11. Ay (Kasım - Güncel)
(1, 1, 1, 1, 2026, 11, 7.80, 3, 86.7, 290.00, 'Basınç düşürücü regülatör takıldı.', NOW()),
(2, 2, 1, 1, 2026, 11, 4.50, 2, 75.0, 175.00, 'Kişi başı 75 litreye kadar indik!', NOW()),
(3, 3, 1, 1, 2026, 11, 12.00, 4, 100.0, 440.00, 'Düşüş sağladık, 100 L bandındayız.', NOW()),
(4, 4, 1, 2, 2026, 11, 4.90, 2, 81.7, 190.00, 'Sabit tasarruf devam ediyor.', NOW()),
(5, 5, 1, 2, 2026, 11, 8.10, 3, 90.0, 305.00, 'Duş süresini 5 dakikaya indirdik.', NOW()),
(6, 6, 1, NULL, 2026, 11, 5.10, 2, 85.0, 195.00, 'Mahallede bireysel yarışıyoruz.', NOW()),
(7, 7, 1, NULL, 2026, 11, 9.80, 4, 81.7, 380.00, 'Şebeke suyunu sulamada hiç kullanmadık.', NOW())
ON DUPLICATE KEY UPDATE `consumption_m3` = VALUES(`consumption_m3`);

-- 7. Örnek Hane Alışkanlıkları
INSERT INTO `household_habits` (`household_id`, `habit_id`, `created_at`) VALUES
(1, 1, NOW()),
(1, 3, NOW()),
(2, 1, NOW()),
(2, 2, NOW()),
(2, 4, NOW()),
(4, 1, NOW()),
(5, 2, NOW()),
(6, 1, NOW()),
(7, 5, NOW()),
(7, 6, NOW())
ON DUPLICATE KEY UPDATE `created_at` = VALUES(`created_at`);

-- 8. Örnek Sosyal Medya Paylaşımları (Posts)
INSERT INTO `posts` (`id`, `user_id`, `neighborhood_id`, `apartment_id`, `visibility`, `category`, `content`, `water_saved_liters`, `is_pinned`, `created_at`) VALUES
-- Yönetici Sabit Duyurusu
(1, 1, 1, 1, 'apartment', 'announcement', 'Komşular selamlar, apartmanımızın ana giriş vanasına basınç düşürücü regülatör taktırdık. Yüksek basınçtan kaynaklanan gereksiz su kaybı ve boru vuruntuları önlenecek. Sayaçlara olumlu yansıyacaktır.', 2500, 1, DATE_SUB(NOW(), INTERVAL 4 DAY)),
-- Mahalle İpucu Paylaşımı
(2, 2, 1, 1, 'neighborhood', 'tip', 'Mutfak musluğuna 25 TL\'ye aldığımız perlatörü taktık. Tazyik hissi hiç azalmadı ama debi neredeyse yarı yarıya düştü. Faturamızda net 1.5 m³ tasarruf gördük, tüm mahalleye tavsiye ederim!', 1500, 0, DATE_SUB(NOW(), INTERVAL 2 DAY)),
-- Mahalle Su Arıza / Kesinti Uyarısı
(3, 4, 1, 2, 'neighborhood', 'alert', 'Moda Caddesi tarafında ana hatta küçük bir bakım çalışması var. Yarın 14:00 - 17:00 arası kısa süreli basınç düşmesi yaşanabilirmiş, bilginiz olsun.', NULL, 0, DATE_SUB(NOW(), INTERVAL 1 DAY)),
-- Bireysel Üyenin Sayaç Başarısı
(4, 6, 1, NULL, 'neighborhood', 'achievement', 'Mahallede bireysel katılımcı olarak bu ayki faturamızı girdik: Kişi başı günlük 85 Litreye indik. Komşularla tatlı rekabet tasarruf yapma motivasyonunu çok artırıyor!', 800, 0, DATE_SUB(NOW(), INTERVAL 8 HOUR)),
-- Müstakil Ev Bahçe Sulama Sorusu
(5, 7, 1, NULL, 'public', 'question', 'Bahçesi olan komşulara bir soru: Damlama sulama sistemini yağmur suyu deposuna bağlayan oldu mu? Basınç yeterli geliyor mu yoksa küçük bir mini hidrofor şart mı?', NULL, 0, DATE_SUB(NOW(), INTERVAL 3 HOUR))
ON DUPLICATE KEY UPDATE `content` = VALUES(`content`);

-- 9. Örnek Damla Destekleri (Reactions)
INSERT INTO `post_reactions` (`post_id`, `user_id`, `created_at`) VALUES
(1, 2, NOW()),
(1, 3, NOW()),
(2, 1, NOW()),
(2, 4, NOW()),
(2, 6, NOW()),
(3, 1, NOW()),
(3, 2, NOW()),
(4, 1, NOW()),
(4, 2, NOW()),
(4, 7, NOW())
ON DUPLICATE KEY UPDATE `created_at` = VALUES(`created_at`);

-- 10. Örnek Yorumlar
INSERT INTO `comments` (`post_id`, `user_id`, `content`, `created_at`) VALUES
(2, 1, 'Harika bir adım Zeynep Hanım, apartman ortalamamıza da direkt yansıdı.', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 6, 'Hangi model perlatör aldınız acaba? Ben de mutfağa takmak istiyorum.', DATE_SUB(NOW(), INTERVAL 20 HOUR)),
(2, 2, 'M24 standart dişli pirinç perlatör aldık Mehmet Bey, hırdavatçılarda kolayca bulunuyor.', DATE_SUB(NOW(), INTERVAL 18 HOUR)),
(5, 1, 'Bizim köydeki müstakil evde 12V mini pompa kullanmıştık Selim Bey, yerçekimi tek başına damlamada son noktalara yetişmeyebiliyor.', DATE_SUB(NOW(), INTERVAL 2 HOUR))
ON DUPLICATE KEY UPDATE `content` = VALUES(`content`);

SET FOREIGN_KEY_CHECKS = 1;

