# Debi — Su Tasarrufu Sosyal Medya Platformu

Mahalle ve apartman ölçeğinde haneleri bir araya getiren, su tüketimini takip edip tasarruf yöntemlerini paylaşmayı sağlayan modüler PHP ve MySQL tabanlı yerel sosyal medya platformu.

---

## 1. Veritabanı Kurulumu (`sql-dum/`)

Veritabanınızı MySQL sunucunuzda (örn. `debi_db`) oluşturduktan sonra `sql-dum/` klasöründeki dosyaları **sırayla** içe aktarın:

1. `sql-dum/001_initial_schema.sql` — Mahalle, Apartman, Hane (Daire/Bireysel/Müstakil), Kullanıcı ve Davet tabloları.
2. `sql-dum/002_social_feed.sql` — Sosyal medya akışı (posts), damla desteği (post_reactions) ve yorumlar (comments).
3. `sql-dum/003_water_tracking.sql` — Aylık sayaç kayıtları (water_logs) ve su tasarrufu alışkanlıkları kataloğu.
4. *(İsteğe Bağlı Demo)* `sql-dum/004_sample_data.sql` — Test ve arayüzü hemen dolu görmek için örnek mahalle (İstanbul / Kadıköy / Moda Mah.), apartmanlar, müstakil/bireysel evler, sayaç kayıtları ve paylaşımlar.
   - Demo kullanıcıların varsayılan şifresi: `debi123`
   - Örnek yönetici: `ali@debi.test`
   - Örnek bireysel katılımcı: `mehmet@debi.test`
   - Örnek müstakil ev: `selim@debi.test`
5. `sql-dum/005_post_images_and_alerts.sql` — Gönderi fotoğraf yükleme alanı ve mahalle su kesintisi/arıza bildirim tablosu.
6. `sql-dum/006_gamification_points.sql` — Damla Puanı (DP), Seviye sistemi, Günlük Su Görevleri (`daily_quests`) ve Rozet (`badges`) tabloları.

---

## 2. Veritabanı Ayarı

`config/database.php` dosyasını açıp MySQL bağlantı bilgilerinizi girin:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'debi_db');
define('DB_USER', 'kullanici_adiniz');
define('DB_PASS', 'sifreniz');
define('DB_CHARSET', 'utf8mb4');
```

---

## 3. Platform Hiyerarşisi ve Katılım Modelleri

Debi'de üst çatı her zaman **Mahalle**'dir.

```
Mahalle (neighborhoods)
 ├── Apartmanlar (apartments)
 │    └── Daireler (households) ── Sakinler (users)
 ├── Bireysel Katılımcı Daireleri (households) ── Kullanıcı
 └── Müstakil Evler (households) ── Kullanıcı
```

- **Bireysel / Müstakil Katılım:** Apartmanı sistemde olmayanlar veya müstakil evde yaşayanlar doğrudan İl / İlçe / Mahalle seçerek mahallesine katılır. İleride apartmanı sisteme girdiğinde tek tıkla davet koduyla verilerini kaybetmeden apartmanına bağlanabilir.
- **Apartman Davetli Katılım:** Yöneticiden alınan genel apartman kodu (`DEBI-XXXXX`) veya daireye özel kod (`DAIRE-XXXXX`) ile hem apartmana hem mahalleye dahil olunur.
- **Apartman Yöneticisi:** Kendi apartmanını mahallesine kaydeder, daireler için davet anahtarları üretir ve aylık apartman su hedefi belirler.

---

## 4. Tasarım İlkeleri

- **Yapay Zeka Klişelerinden Uzak:** Glow/neon efektleri, mor-mavi gradyanlar veya cam efektleri kullanılmamıştır.
- **İşlevsel ve Doğal:** Net kenarlıklar, düz renk yüzeyler ve okunaklı tipografi.
- **Tam Responsive:** Masaüstünde 3 sütunlu bilgi mimarisi, mobilde tek sütun ve alt sabit hızlı erişim barı.
