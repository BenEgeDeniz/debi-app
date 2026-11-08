<?php
$document = $_GET['doc'] ?? 'privacy';
$documents = [
    'kvkk' => [
        'title' => 'KVKK Aydınlatma Metni',
        'intro' => 'Bu metin, Debi su tasarrufu ve mahalle dayanışması platformunu kullanırken kişisel verilerinizin hangi amaçlarla işlendiğini açıklar.',
        'sections' => [
            ['title' => '1. Veri sorumlusu ve kapsam', 'text' => 'Debi platformunun işletmecisi, kayıt sırasında ve kullanım boyunca elde edilen kişisel verilerin veri sorumlusudur. Bu metin; hesap, hane, fatura, sosyal içerik, etkinlik, puan ve teknik kullanım verilerini kapsar. İşletmeci iletişim bilgileri platformun iletişim kanalında ayrıca yayımlanır.'],
            ['title' => '2. İşlenen veri kategorileri', 'list' => ['Kimlik ve hesap: ad soyad, nickname, e-posta, profil bilgileri ve avatar tercihleri.', 'Hane ve konum: konut türü, daire/hane etiketi, mahalle, apartman, sakin sayısı ve davet bağlantıları.', 'Su tüketimi: fatura dönemi, tüketim miktarı, okuma günleri, tutar, kurum bilgisi, fatura görseli ve hesaplanan litre/tasarruf metrikleri.', 'Platform kullanımı: gönderi, yorum, yanıt, mention, bildirim, arkadaşlık, etkinlik katılımı, yoklama ve etkinlik fotoğrafı.', 'Puan ve işlem: ortak hane damla bakiyesi, gölet durumu, mağaza kuponu ve kampanya katkıları.', 'Güvenlik ve teknik kayıtlar: oturum, CSRF, işlem zamanı ve hata/güvenlik kayıtları.']],
            ['title' => '3. İşleme amaçları', 'text' => 'Veriler; hesap açmak ve giriş yaptırmak, kullanıcıyı doğru haneye bağlamak, faturaları analiz etmek, kişi başı tüketimi ve hane göletini hesaplamak, sosyal akışı ve bildirimleri çalıştırmak, etkinlik katılımını ve fotoğraf kanıtını doğrulamak, puan/mağaza işlemlerini yürütmek, kötüye kullanımı önlemek ve yasal yükümlülükleri yerine getirmek için işlenir.'],
            ['title' => '4. Yapay zekâ ve hizmet sağlayıcıları', 'text' => 'Fatura fotoğrafı, kullanıcı tarafından başlatılan AI okuma işleminde gerekli alanları çıkarmak için üçüncü taraf yapay zekâ hizmetine aktarılabilir. Etkinlik fotoğrafı da etkinlik doğrulaması amacıyla işlenebilir. Kullanıcı, kayıt sırasında ve ilgili yükleme akışında bu kullanımı kabul etmeden AI özelliğini kullanmamalıdır.'],
            ['title' => '5. Paylaşım ve görünürlük', 'text' => 'Kullanıcı, gönderi oluştururken arkadaşlar, apartman, genel akış veya paylaşmama seçeneklerinden birini seçebilir. Paylaşılan içerik seçilen kitleye görünür. Profil sayfasında kullanıcının kendi gönderileri, etkinliklerde ise etkinlik bilgileri ve katılım durumu ilgili akışlarda gösterilebilir. Fatura görselleri sosyal akışta otomatik olarak yayımlanmaz.'],
            ['title' => '6. Saklama ve güvenlik', 'text' => 'Veriler yalnızca ilgili hizmet, muhasebe/uyuşmazlık, güvenlik ve yasal yükümlülük amaçları için gerekli süre boyunca saklanır. Şifreler geri döndürülemez parola hash’i olarak tutulur; ham parola cookie, localStorage veya benzeri tarayıcı depolamasına yazılmaz.'],
            ['title' => '7. Haklarınız', 'text' => 'KVKK kapsamındaki bilgi alma, erişim, düzeltme, silme veya anonimleştirme, işleme amacını öğrenme ve kanuni şartlar çerçevesinde itiraz/başvuru haklarınız için platform işletmecisinin iletişim kanalını kullanabilirsiniz. Başvurular kimlik doğrulaması ve yasal süreler çerçevesinde değerlendirilir.'],
        ],
    ],
    'terms' => [
        'title' => 'Debi Kullanım Sözleşmesi',
        'intro' => 'Debi; su tüketimini izlemek, haneleri ortak bir tasarruf hedefinde buluşturmak ve mahalle ölçeğinde doğrulanabilir su tasarrufu faaliyetlerini desteklemek için kullanılan bir platformdur.',
        'sections' => [
            ['title' => '1. Hesap ve hane üyeliği', 'text' => 'Kayıt bilgilerinizin doğru ve güncel olmasından siz sorumlusunuz. Davet kodları yalnızca yetkili olduğunuz apartman veya haneye katılmak için kullanılabilir. Aynı haneye bağlı kullanıcıların puan ve gölet bakiyesi ortak hane hesabında tutulur; bu bakiye kişisel banka/para hesabı değildir.'],
            ['title' => '2. Fatura ve su verileri', 'text' => 'Yalnızca kendi hanenize ait, gerçek ve okunabilir faturaları yükleyin. Fatura dönemi, tüketim, kişi sayısı ve diğer alanları bilerek yanlış girmek; başka kişilerin faturasını izinsiz yüklemek veya puan kazanmak amacıyla sistemi yanıltmak yasaktır. AI tarafından okunan bilgiler kaydetmeden önce kontrol edilmelidir.'],
            ['title' => '3. Puan, damla ve gölet sistemi', 'text' => 'Damla/DP puanları Debi içi tasarruf ve katılım göstergesidir; nakde çevrilemez, devredilemez ve garanti edilmiş bir ödül veya finansal değer oluşturmaz. Fatura puanları hane bakiyesine yazılır. Puanlar; doğrulama sonucu, aynı kaydın daha önce kredilendirilmiş olması, inceleme veya kötüye kullanım tespiti nedeniyle verilmeyebilir ya da düzeltilebilir.'],
            ['title' => '4. Sosyal içerik ve iletişim', 'text' => 'Gönderi, yorum, yanıt, mention ve profil bilgileri paylaşım seçiminize göre diğer kullanıcılara gösterilebilir. Başkasının kişisel verisini, fotoğrafını veya içeriğini izinsiz paylaşmayın. Hakaret, tehdit, spam, yanıltıcı tasarruf iddiası, reklam/istismar, otomatik trafik ve güvenlik açığı arama amacıyla kullanım yasaktır.'],
            ['title' => '5. Etkinlikler', 'text' => 'Etkinlikler fiziksel katılım gerektiren topluluk faaliyetleridir. Katılım bildirimi tek başına gerçekleşmiş katılım kanıtı değildir. Etkinlik başladıktan sonra yoklama alınabilir; yoklamada katıldı olarak işaretlenmek ve etkinlik fotoğrafının AI doğrulamasından geçmesi puan koşullarındandır. Etkinlik sahibi yoklamayı zamanında almazsa etkinlik otomatik olarak iptal edilebilir.'],
            ['title' => '6. Mağaza ve kampanyalar', 'text' => 'Mağaza kuponları ve anlaşmalı kampanyalar, ilgili sağlayıcının koşullarına ve Debi’de gösterilen limitlere tabidir. Kampanya katkıları gerçek para bağışı olarak sunulmaz; puan bakiyesinden düşülen platform içi katkıdır. Sağlayıcı, stok, süre veya kampanya koşullarını değiştirebilir.'],
            ['title' => '7. Sorumluluk sınırları', 'text' => 'Debi, su tüketimi hesabını ve sanal su etkisi tahminlerini farkındalık ve karşılaştırma amacıyla sunar; resmi ölçüm, mühendislik raporu, sağlık/finans tavsiyesi veya tasarruf garantisi değildir. Kullanıcıların etkinlik alanındaki fiziksel güvenliği, ekipman kullanımı ve yerel izinler kullanıcıların ve etkinlik sorumlularının sorumluluğundadır.'],
            ['title' => '8. Hesap işlemleri', 'text' => 'Kullanıcı hesabı, içerik veya etkinlik; güvenlik, yasal zorunluluk, yanlış bilgi, kötüye kullanım veya sözleşme ihlali halinde geçici olarak kısıtlanabilir. Kullanıcı hesabını kapatmayı talep edebilir; yasal olarak saklanması gereken kayıtlar silme talebinden etkilenmeyebilir.'],
        ],
    ],
    'privacy' => [
        'title' => 'Debi Gizlilik Politikası',
        'intro' => 'Bu politika, Debi’de toplanan verilerin pratikte nasıl kullanıldığını ve kullanıcı görünürlüğünün nasıl çalıştığını açıklar.',
        'sections' => [
            ['title' => 'Hangi bilgiler görünür?', 'text' => 'Nickname, ad soyad, profil bilgileri ve sosyal gönderiler; seçilen paylaşım kapsamına göre görünür. Etkinlik başlığı, yeri, zamanı, oluşturucusu, katılımcı sayısı ve puan gerekçesi etkinlik görünürlüğüne göre gösterilebilir. Fatura görselleri ve ayrıntılı fatura alanları sosyal akışta paylaşılmaz.'],
            ['title' => 'Faturalar nasıl kullanılır?', 'text' => 'Fatura görseli, fatura alanlarını okumak, kayıt ekranını doldurmak ve gerektiğinde inceleme akışını yürütmek için kullanılır. Kullanıcı tarafından değiştirilen AI değerleri admin incelemesine gidebilir. Fatura kayıtları hane su analizi, puan ve geçmiş ekranları için saklanır.'],
            ['title' => 'Puan ve hane gizliliği', 'text' => 'Aynı hanedeki sakinler ortak hane puanını ve gölet ilerlemesini görür. Bireysel fatura ayrıntıları ise yalnızca ilgili uygulama ekranlarında, yetki ve görünürlük kurallarına göre gösterilir.'],
            ['title' => 'Tarayıcı ve oturum', 'text' => 'Oturum güvenlik çerezi olarak yönetilir. Kullanıcı parolası veya paroladan türetilmiş çözme anahtarı tarayıcı depolamasında tutulmaz. Yorum zincirlerinin açık/kapalı durumu gibi tercihler, hassas kimlik bilgisi içermeyen localStorage verileriyle tutulabilir.'],
            ['title' => 'Değişiklikler ve iletişim', 'text' => 'Bu metin platform özellikleri, mevzuat veya veri işleme yöntemleri değiştiğinde güncellenebilir. Önemli değişikliklerde kullanıcılar uygun kanallardan bilgilendirilir. Sorular ve veri başvuruları platform işletmecisinin yayımladığı iletişim kanalından iletilmelidir.'],
        ],
    ],
];
$doc = $documents[$document] ?? $documents['privacy'];
?>
<!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= htmlspecialchars($doc['title'], ENT_QUOTES, 'UTF-8') ?> — Debi</title>
<style>body{max-width:820px;margin:40px auto;padding:0 20px;font:16px/1.65 system-ui,sans-serif;color:#17212b}h1{line-height:1.2}h2{font-size:1.08rem;margin:28px 0 8px}p{margin:0 0 12px}.legal-intro{font-size:1.05rem;color:#475569}.legal-nav{display:flex;gap:8px;flex-wrap:wrap;margin:24px 0}.legal-nav a{padding:7px 11px;border:1px solid #cbd5e1;border-radius:999px;color:#0f5f8f;text-decoration:none}.legal-footer{margin-top:36px;padding-top:16px;border-top:1px solid #e2e8f0}</style></head>
<body>
<h1><?= htmlspecialchars($doc['title'], ENT_QUOTES, 'UTF-8') ?></h1>
<p class="legal-intro"><?= htmlspecialchars($doc['intro'], ENT_QUOTES, 'UTF-8') ?></p>
<?php foreach ($doc['sections'] as $section): ?>
    <section>
        <h2><?= htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8') ?></h2>
        <?php if (!empty($section['text'])): ?><p><?= htmlspecialchars($section['text'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
        <?php if (!empty($section['list'])): ?><ul><?php foreach ($section['list'] as $item): ?><li><?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul><?php endif; ?>
    </section>
<?php endforeach; ?>
<div class="legal-footer"><a href="legal.php?doc=kvkk">KVKK</a> · <a href="legal.php?doc=terms">Kullanım Sözleşmesi</a> · <a href="legal.php?doc=privacy">Gizlilik Politikası</a> · <a href="register.php">Kayıt sayfasına dön</a></div>
</body></html>
