<?php
$document = $_GET['doc'] ?? 'privacy';
$documents = [
    'kvkk' => ['title' => 'KVKK Aydınlatma Metni', 'body' => 'Debi; hesap, hane, fatura ve kullanım verilerini hizmetin çalışması, su tüketimi analizi, etkinlik doğrulaması ve güvenlik amacıyla işler. Veriler yalnızca gerekli süre boyunca saklanır; üçüncü taraf yapay zekâ hizmetine gönderilen fatura görselleri bu aktarım hakkında açık bilgilendirme ile işlenir.'],
    'terms' => ['title' => 'Kullanım Sözleşmesi', 'body' => 'Debi’yi kullanırken doğru bilgi vermeyi, yalnızca kendi hanenize ait verileri yüklemeyi, etkinlik ve topluluk özelliklerini kötüye kullanmamayı kabul edersiniz. Puanlar ve etkinlik doğrulamaları hizmet kurallarına göre verilir.'],
    'privacy' => ['title' => 'Gizlilik Politikası', 'body' => 'Şifreler geri döndürülemez biçimde hashlenir. Fatura görselleri sunucu tarafında şifreli saklanır ve yalnızca yetkili hane sahibi, ilgili sakin veya admin incelemesi için yetkilendirilmiş kullanıcı tarafından görüntülenebilir. Şifreler cookie veya tarayıcı depolamasında tutulmaz.'],
];
$doc = $documents[$document] ?? $documents['privacy'];
?>
<!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= htmlspecialchars($doc['title'], ENT_QUOTES, 'UTF-8') ?> — Debi</title></head>
<body style="max-width:760px;margin:48px auto;padding:0 20px;font:16px system-ui,sans-serif;line-height:1.6;color:#17212b">
<h1><?= htmlspecialchars($doc['title'], ENT_QUOTES, 'UTF-8') ?></h1>
<p><?= htmlspecialchars($doc['body'], ENT_QUOTES, 'UTF-8') ?></p>
<p><a href="register.php">Kayıt sayfasına dön</a></p>
</body></html>
