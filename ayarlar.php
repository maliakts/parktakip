<?php
/*
 * ayarlar.php — Sistem ayarları (yalnızca yönetici)
 * Ziyaretçi araç tarifesi buradan değiştirilir.
 */
require_once 'includes/functions.php';
yoneticiGerek();

$hata = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $saatlik = (float)str_replace(',', '.', formMetni('saatlik'));
    $gunluk  = (float)str_replace(',', '.', formMetni('gunluk'));
    if ($saatlik <= 0 || $gunluk <= 0) {
        $hata = 'Ücretler 0\'dan büyük olmalıdır.';
    } elseif ($gunluk < $saatlik) {
        $hata = 'Günlük tavan ücret saatlik ücretten küçük olamaz.';
    } else {
        $_SESSION['ayarlar'] = ['saatlik' => round($saatlik, 2), 'gunluk' => round($gunluk, 2)];
        logKaydet('Ziyaretçi tarifesi güncellendi (' . paraYaz($saatlik) . ' / saat, ' . paraYaz($gunluk) . ' / gün)');
        mesajKaydet('basari', 'Tarife güncellendi. İçerideki ziyaretçi araçlar da yeni tarifeyle ücretlendirilir.');
        yonlendir('ayarlar.php');
    }
}

$sayfaBasligi = 'Ayarlar';
$aktifSayfa   = 'ayar';
require 'includes/header.php';
?>

<div class="sayfa-baslik">
    <div>
        <h1>Ayarlar</h1>
        <p>Ziyaretçi araç tarifesi ve sistem bilgileri.</p>
    </div>
</div>

<?php if ($hata !== ''): ?>
    <div class="bildirim bildirim-hata" role="alert"><?= ikon('hata', 20) ?><span class="bildirim-metin"><?= e($hata) ?></span></div>
<?php endif; ?>

<section class="panel-izgara">
    <form class="kart kol-5" method="post" action="ayarlar.php" novalidate>
        <div class="kart-ic">
            <div class="kart-ust"><div><h2>Ziyaretçi Tarifesi</h2><p>Başlanan her saat ücretlidir</p></div></div>
            <div class="alan"><label for="saatlik">Saatlik ücret (₺)</label><input type="text" id="saatlik" name="saatlik" inputmode="decimal" value="<?= e(number_format(ayar('saatlik'), 2, ',', '')) ?>"></div>
            <div class="alan"><label for="gunluk">Günlük tavan ücret (₺)</label><input type="text" id="gunluk" name="gunluk" inputmode="decimal" value="<?= e(number_format(ayar('gunluk'), 2, ',', '')) ?>"><span class="ipucu">24 saatlik dilimde bu tutar aşılmaz.</span></div>
            <div class="form-butonlar"><button type="submit" class="buton"><?= ikon('kaydet', 16) ?>Kaydet</button></div>
        </div>
    </form>
    <div class="kart kol-7">
        <div class="kart-ic">
            <div class="kart-ust"><div><h2>Sistem</h2><p>Bilgi</p></div></div>
            <ul class="kural-listesi">
                <li><?= ikon('bilgi', 16) ?><span>Toplam kapasite <strong><?= TOPLAM_KAPASITE ?></strong> park yeridir (<code>includes/functions.php</code> içindeki <code>TOPLAM_KAPASITE</code>).</span></li>
                <li><?= ikon('bilgi', 16) ?><span>Tüm veriler oturumda (<code>$_SESSION</code>) tutulur. Tarayıcı kapanınca kayıtlar başlangıç durumuna döner.</span></li>
            </ul>
        </div>
    </div>
</section>

<?php require 'includes/footer.php'; ?>
