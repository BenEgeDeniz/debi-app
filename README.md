# Debi
[![mühür](https://muhur-phi.vercel.app/api/rozet/ekozeka-2026/2)](https://muhur-phi.vercel.app/sergi/ekozeka-2026/2)

Debi, mahalle ve apartman ölçeğinde su tasarrufunu görünür, ölçülebilir ve
paylaşılabilir hale getiren bir web uygulamasıdır.

Kullanıcılar su faturalarını yükler, tüketimlerini kişi başı günlük litre
olarak takip eder, aynı hanedeki kişilerle ortak bir tasarruf puanı biriktirir,
mahalle içindeki sosyal akışta gelişmeleri paylaşır ve su tasarrufu odaklı
etkinliklere katılır.

## Debi tam olarak ne yapıyor?

### 1. Hane merkezli su takibi

- Fatura fotoğrafını yükleyip bilgileri yapay zekâ ile okutabilir.
- Dönem, tüketim, okuma günü, tutar ve kurum bilgilerini kontrol ederek
  kaydedebilir.
- Tüketimi hane sakinlerinin sayısına göre **kişi başı litre/gün** olarak
  hesaplar.
- Önceki faturayla karşılaştırarak tüketim azalmasını, sabit kalmasını veya
  artmasını gösterir.
- Geçmiş faturaları ve tüketim trendini grafiklerle izler.
- Fatura kaydı sonrası hane göletine damlaların düştüğü bir kutlama animasyonu
  gösterir.

### 2. Ortak hane puanı ve daire göleti

Debi’de puan kişiye değil, haneye aittir. Aynı dairede yaşayan sakinler ve
daire yöneticisi aynı `household` kaydını paylaşıyorsa puan bakiyesi de
ortaktır.

- Apartman daireleri, apartmansız bireysel daireler ve müstakil evler
  desteklenir.
- Fatura tasarrufu ve diğer uygun aksiyonlar hanenin ortak damla bakiyesine
  eklenir.
- Hane bakiyesi, daire göletinin doluluk oranını ve görsel aşamasını belirler.
- Apartman, arkadaş ve genel sıralamalarda hane katkısı karşılaştırılabilir.

### 3. Sosyal mahalle akışı

- Kullanıcılar tasarruf başarılarını ve diğer içerikleri akışta paylaşabilir.
- Görünürlük arkadaşlar, apartman veya genel akış olarak seçilebilir.
- Gönderilere yorum, yanıt ve beğeni eklenebilir.
- Yorum yanıtları zincir halinde açılıp kapatılabilir.
- `@nickname` mention’ları profil bağlantısı ve bildirim oluşturur.
- Kullanıcı profillerinde yalnızca profil sahibinin kendi gönderileri listelenir.

### 4. Etkinlikler ve katılım

- Apartman veya mahalle ölçeğinde etkinlik oluşturulabilir.
- Etkinliğe katılma kararı başlangıç saatinden önce geri çekilebilir.
- Başlangıç saati geldiğinde etkinlik otomatik olarak başlamış kabul edilir.
- Katılımcılar için yoklama ve katılım fotoğrafı yükleme akışı bulunur.
- Etkinlik başladıktan sonra sahibi bilgilendirilir; katılımcılara menü
  bildirim noktası gösterilir.
- Başlangıçtan sonraki bir saat içinde yoklama alınmazsa etkinlik cron worker
  tarafından iptal edilir.
- Etkinlik oluşturulurken AI, kriterlerdeki fiziksel katkı ve su kazanımı
  metriklerinden tahmini sanal su etkisi çıkarır; etkinlik kartında bu tahminin
  temel DP puanına etkisi ve doğrulanan katılımcının kazanacağı puan gösterilir.

### 5. Debi mağazası ve kampanyalar

- Biriken puanlarla mağaza kuponları alınabilir.
- Anlaşmalı su tasarrufu kampanyalarına puan bağışlanabilir.
- Bağış miktarı arayüzde 10 puanlık `− / +` adımlarıyla seçilir.
- Kampanya ilerlemesi, destekçi sayısı ve toplam katkı takip edilebilir.
- Bazı katkılar için teşekkür belgesi oluşturulabilir.

### 6. Debbie yapay zekâ asistanı

Debbie, akışta `@debbie` mention’ı ile çağrılan su tasarrufu asistanıdır.

- Fatura fotoğrafındaki bilgileri okumaya yardımcı olur.
- Kullanıcıların su tasarrufu sorularına yanıt verir.
- Yanıtları ana kullanıcı isteğini yavaşlatmamak için kuyruk üzerinden
  oluşturabilir.
- Debbie yanıt worker’ı ayrı bir cron görevi olarak çalıştırılabilir.

## Teknik yapı

Debi, framework bağımlılığı olmayan, PHP ve vanilla JavaScript ile hazırlanmış
bir web uygulamasıdır.

- **Backend:** PHP 8.4+ önerilir
- **Veritabanı:** MySQL veya MariaDB, `utf8mb4`
- **Veritabanı erişimi:** PDO, prepared statements
- **Frontend:** Server-rendered PHP, vanilla JavaScript, CSS
- **Yapay zekâ:** Gemini API; fatura okuma ve Debbie için kullanılır
- **Asenkron davranış:** Genel form gönderimlerinde progressive AJAX
- **Zamanlanmış işler:** PHP CLI veya hosting scheduled task

Ana giriş noktaları:

- [`index.php`](./index.php): sosyal akış
- [`water.php`](./water.php): fatura yükleme ve su analizi
- [`events.php`](./events.php): etkinlikler
- [`store.php`](./store.php): mağaza ve kampanyalar
- [`profile.php`](./profile.php): profil ve profil gönderileri
- [`analytics.php`](./analytics.php): su analitiği
- [`migration.php`](./migration.php): web tabanlı migration ekranı
- [`migrate.php`](./migrate.php): CLI migration çalıştırıcısı

## Kurulum

### Gereksinimler

- PHP 8.4 veya üzeri
- PDO MySQL eklentisi
- MySQL 8+ veya MariaDB 10.6+
- Web sunucusu (Apache/Nginx)
- Fatura okuma ve Debbie için Gemini API anahtarı

### 1. Projeyi sunucuya alın

Web sunucusunun document root'u proje dizinini göstermelidir. PHP'nin şu
klasörlere yazabilmesi gerekir:

- `uploads/`
- gerekiyorsa `cache/locations/`

### 2. Yerel veritabanı ayarlarını oluşturun

`config/database.local.php` dosyasını oluşturun. Bu dosya `.gitignore`
kapsamındadır ve GitHub’a gönderilmemelidir.

```php
<?php

define('DB_HOST', 'localhost');
define('DB_NAME', 'debi_db');
define('DB_USER', 'debi_user');
define('DB_PASS', 'guclu-bir-sifre');
define('DB_CHARSET', 'utf8mb4');

define('GEMINI_API_KEY', 'gemini-api-key');
define('DEBBIE_GEMINI_MODEL', 'gemini-3.5-flash-lite');
```

Gemini anahtarı yerine ortam değişkeni de kullanılabilir:

```bash
export GEMINI_API_KEY="gemini-api-key"
export DEBBIE_GEMINI_MODEL="gemini-3.5-flash-lite"
```

### 3. Veritabanını oluşturun

İlk kurulumda canonical dump ile temel şemayı oluşturun:

```bash
mysql -u debi_user -p debi_db < sql-dum/001_initial_schema.sql
```

Sonraki değişiklikler incremental migration olarak sırayla uygulanır:

```bash
php migrate.php migrate
```

Belirli bir aralığı çalıştırmak için:

```bash
php migrate.php migrate --from=9 --to=14
```

Migration durumunu görmek için:

```bash
php migrate.php status
```

`sql-dum/001_initial_schema.sql` canlı veritabanının canonical snapshot’ıdır;
uygulandıktan sonra mevcut migration dosyaları değiştirilmemelidir. Yeni şema
değişiklikleri yeni, sıralı bir migration dosyası olarak eklenmelidir.

### 4. Migration ekranı

CLI kullanılamayan hosting ortamlarında [`migration.php`](./migration.php)
üzerinden migration çalıştırılabilir. Ekran geçici erişim parolası ve CSRF
kontrolüyle korunur.

Production ortamında migration ekranını gereksiz yere açık bırakmayın.

## Cron görevleri

Aşağıdaki worker’lar her dakika çalıştırılabilir:

```bash
php /path/to/debi-app/cron/debbie_worker.php
php /path/to/debi-app/cron/events_worker.php
```

- [`cron/debbie_worker.php`](./cron/debbie_worker.php): Debbie yanıt kuyruğunu
  işler.
- [`cron/events_worker.php`](./cron/events_worker.php): süresi içinde yoklama
  alınmayan başlayan etkinlikleri iptal eder.

Plesk gibi panellerde PHP binary’si ile doğrudan scheduled task tanımlayın ve
çıktıyı yalnızca hata olduğunda kaydetmeyi tercih edin.

## Güvenlik ve operasyon notları

- `config/database.local.php` hiçbir koşulda commit edilmemelidir.
- API anahtarlarını kaynak koduna veya public dump dosyalarına koymayın.
- Production’da `uploads/` erişim kurallarını kontrol edin.
- Migration’ları tekrar çalıştırmadan önce durum ekranını ve checksum’ları
  kontrol edin.
- Fatura puanları `water_logs.points_credited` ile bir kez kredilendirilir;
  aynı fatura için tekrar puan yazılmaması beklenen davranıştır.
- Hane puan bakiyesi `households.total_points` üzerinde tutulur; kişi bazlı
  eski puan mantığına yeni özellik eklenmemelidir.

## Dizin yapısı

```text
.
├── assets/
│   ├── css/style.css
│   └── js/app.js
├── config/
│   └── database.php
├── cron/
│   ├── debbie_worker.php
│   └── events_worker.php
├── includes/
│   ├── header.php
│   ├── sidebar_left.php
│   └── sidebar_right.php
├── modules/
│   ├── database/
│   ├── events/
│   ├── feed/
│   ├── gamification/
│   ├── store/
│   └── water/
├── sql-dum/
├── events.php
├── index.php
├── migrate.php
├── migration.php
├── store.php
├── water.php
└── README.md
```

## Lisans

Bu repository için henüz ayrı bir lisans dosyası tanımlanmamıştır. Yeniden
dağıtım veya ticari kullanım kararları için repository sahibinden izin alın.