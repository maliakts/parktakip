<?php
/*
 * paketler.php — Abonelik paketleri
 * Paketlerin fiyatı, açıklaması ve satışta olup olmadığı buradan yönetilir (yalnızca yönetici değiştirir).
 */
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    yoneticiGerek();
    $ad = formMetni('ad');
    foreach ($_SESSION['paketler'] as $sira => $paket) {
        if ($paket['ad'] !== $ad) {
            continue;
        }
        $fiyat    = (float)str_replace(',', '.', formMetni('fiyat'));
        $aciklama = formMetni('aciklama');
        if ($fiyat <= 0 || $fiyat > 1000000) {
            mesajKaydet('hata', 'Fiyat 0\'dan büyük bir sayı olmalıdır.');
        } elseif (mb_strlen($aciklama, 'UTF-8') > 80) {
            mesajKaydet('hata', 'Açıklama en fazla 80 karakter olabilir.');
        } else {
            $_SESSION['paketler'][$sira]['fiyat']    = round($fiyat, 2);
            $_SESSION['paketler'][$sira]['aciklama'] = $aciklama;
            $_SESSION['paketler'][$sira]['aktif']    = isset($_POST['aktif']);
            logKaydet($ad . ' paketi güncellendi');
            mesajKaydet('basari', $ad . ' paketi güncellendi.');
        }
        yonlendir('paketler.php');
    }
    mesajKaydet('hata', 'Paket bulunamadı.');
    yonlendir('paketler.php');
}

// Her pakette kaç abone var?
$aboneSayisi = [];
foreach ($_SESSION['araclar'] as $k) {
    $aboneSayisi[$k['abone_turu']] = ($aboneSayisi[$k['abone_turu']] ?? 0) + 1;
}

$sayfaBasligi = 'Abonelik Paketleri';
$aktifSayfa   = 'paket';
require 'includes/header.php';
?>

<div class="sayfa-baslik">
    <div>
        <h1>Abonelik Paketleri</h1>
        <p>Yeni abonelik ve yenilemelerde bu fiyatlar kullanılır. Satışta olmayan paket seçilemez.</p>
    </div>
</div>

<section class="paket-izgara">
<?php foreach ($_SESSION['paketler'] as $paket): ?>
    <form class="kart paket-kart <?= $paket['aktif'] ? '' : 'paket-pasif' ?>" method="post" action="paketler.php">
        <div class="kart-ic">
            <div class="kart-ust">
                <div><h2><?= e($paket['ad']) ?></h2><p><?= $paket['ay'] ?> ay · <?= $aboneSayisi[$paket['ad']] ?? 0 ?> abone</p></div>
                <span class="rozet <?= $paket['aktif'] ? 'rozet-yesil' : 'rozet-gri' ?>"><?= $paket['aktif'] ? 'Satışta' : 'Pasif' ?></span>
            </div>
            <div class="kpi-sayi"><?= e(paraYaz($paket['fiyat'])) ?></div>
            <p class="soluk" style="margin: 6px 0 16px">Aylık <?= e(paraYaz($paket['fiyat'] / $paket['ay'])) ?></p>
            <?php if (yoneticiMi()): ?>
                <input type="hidden" name="ad" value="<?= e($paket['ad']) ?>">
                <div class="alan"><label>Fiyat (₺)</label><input type="text" name="fiyat" inputmode="decimal" value="<?= e(number_format($paket['fiyat'], 2, ',', '')) ?>" required></div>
                <div class="alan"><label>Açıklama</label><input type="text" name="aciklama" maxlength="80" value="<?= e($paket['aciklama']) ?>"></div>
                <label class="onay-kutu"><input type="checkbox" name="aktif" <?= $paket['aktif'] ? 'checked' : '' ?>> Satışta</label>
                <div class="form-butonlar"><button type="submit" class="buton buton-kucuk"><?= ikon('kaydet', 15) ?>Kaydet</button></div>
            <?php else: ?>
                <p><?= e($paket['aciklama']) ?></p>
            <?php endif; ?>
        </div>
    </form>
<?php endforeach; ?>
</section>

<?php require 'includes/footer.php'; ?>
