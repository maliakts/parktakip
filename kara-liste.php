<?php
/*
 * kara-liste.php — Yasaklı plakalar
 * Kara listedeki araç giriş yapmak istediğinde uyarı verilir ve giriş engellenir.
 * Kaydı yalnızca yönetici kaldırabilir.
 */
require_once 'includes/functions.php';

$hata = ''; $veri = ['plaka' => '', 'sebep' => '', 'aciklama' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $islem = formMetni('islem');
    if ($islem === 'kaldir') {
        yoneticiGerek();
        $plaka = formMetni('plaka');
        foreach ($_SESSION['kara_liste'] as $sira => $k) {
            if ($k['plaka'] === $plaka) {
                unset($_SESSION['kara_liste'][$sira]);
                $_SESSION['kara_liste'] = array_values($_SESSION['kara_liste']);
                logKaydet($plaka . ' kara listeden kaldırıldı');
                mesajKaydet('basari', $plaka . ' kara listeden kaldırıldı.');
            }
        }
        yonlendir('kara-liste.php');
    }
    $veri = ['plaka' => plakaDuzenle(formMetni('plaka')), 'sebep' => formMetni('sebep'), 'aciklama' => formMetni('aciklama')];
    if (!plakaGecerliMi($veri['plaka'])) {
        $hata = 'Plaka geçersiz. Örnek biçim: 60 ABC 123.';
    } elseif (karaListede($veri['plaka']) !== null) {
        $hata = 'Bu plaka zaten kara listede.';
    } elseif (acikHareket($veri['plaka']) !== null) {
        $hata = 'Bu araç şu an otoparkta. Önce çıkışını yapınız.';
    } elseif (mb_strlen($veri['sebep'], 'UTF-8') < 3 || mb_strlen($veri['sebep'], 'UTF-8') > 60) {
        $hata = 'Yasaklama sebebi 3-60 karakter olmalıdır.';
    } elseif (mb_strlen($veri['aciklama'], 'UTF-8') > 200) {
        $hata = 'Açıklama en fazla 200 karakter olabilir.';
    } else {
        $_SESSION['kara_liste'][] = $veri + ['zaman' => time(), 'ekleyen' => $_SESSION['kullanici']['kadi']];
        logKaydet($veri['plaka'] . ' kara listeye eklendi (' . $veri['sebep'] . ')');
        mesajKaydet('basari', $veri['plaka'] . ' kara listeye eklendi.');
        yonlendir('kara-liste.php');
    }
}

$sayfaBasligi = 'Kara Liste';
$aktifSayfa   = 'kara';
require 'includes/header.php';
?>

<div class="sayfa-baslik">
    <div>
        <h1>Kara Liste</h1>
        <p>Yasaklı plakalar otoparka giremez; giriş denemesi işlem geçmişine yazılır.</p>
    </div>
</div>

<?php if ($hata !== ''): ?>
    <div class="bildirim bildirim-hata" role="alert"><?= ikon('hata', 20) ?><span class="bildirim-metin"><?= e($hata) ?></span></div>
<?php endif; ?>

<section class="panel-izgara">
    <form class="kart kol-4" method="post" action="kara-liste.php" novalidate>
        <div class="kart-ic">
            <div class="kart-ust"><div><h2>Plaka Yasakla</h2><p>Tüm alanlar kaydedilir</p></div></div>
            <input type="hidden" name="islem" value="ekle">
            <div class="alan"><label for="plaka">Plaka *</label><input type="text" id="plaka" name="plaka" maxlength="12" class="plaka-girdi" placeholder="60 ABC 123" value="<?= e($veri['plaka']) ?>"></div>
            <div class="alan"><label for="sebep">Yasaklama sebebi *</label><input type="text" id="sebep" name="sebep" maxlength="60" placeholder="Örn. Ödenmemiş borç" value="<?= e($veri['sebep']) ?>"></div>
            <div class="alan"><label for="aciklama">Açıklama</label><input type="text" id="aciklama" name="aciklama" maxlength="200" value="<?= e($veri['aciklama']) ?>"></div>
            <div class="form-butonlar"><button type="submit" class="buton buton-tehlike"><?= ikon('yasak', 16) ?>Kara listeye ekle</button></div>
        </div>
    </form>

    <div class="kart tablo-kart kol-8">
        <div class="tablo-ust"><h2>Yasaklı Plakalar <span class="sayi-rozet"><?= count($_SESSION['kara_liste']) ?> kayıt</span></h2></div>
        <?php if (count($_SESSION['kara_liste']) === 0): ?>
            <div class="bos-durum"><span class="ikon-daire"><?= ikon('onay', 26) ?></span><h3>Kara liste boş</h3><p>Yasaklı plaka bulunmuyor.</p></div>
        <?php else: ?>
            <div class="tablo-kaydir"><table style="min-width: 620px">
                <thead><tr><th>Plaka</th><th>Sebep</th><th>Açıklama</th><th>Tarih</th><th>Ekleyen</th><?php if (yoneticiMi()): ?><th class="islem-hucre">İşlem</th><?php endif; ?></tr></thead>
                <tbody>
                <?php foreach (array_reverse($_SESSION['kara_liste']) as $k): ?>
                    <tr>
                        <td><span class="plaka"><span><?= e($k['plaka']) ?></span></span></td>
                        <td><?= e($k['sebep']) ?></td>
                        <td class="soluk"><?= e($k['aciklama'] !== '' ? $k['aciklama'] : '—') ?></td>
                        <td class="soluk"><?= e(zamanYaz($k['zaman'])) ?></td>
                        <td class="soluk"><?= e($k['ekleyen']) ?></td>
                        <?php if (yoneticiMi()): ?>
                        <td class="islem-hucre">
                            <form method="post" action="kara-liste.php" class="islemler" data-onay="<?= e($k['plaka']) ?> kara listeden kaldırılsın mı?" data-onay-baslik="Yasağı kaldır" data-onay-buton="Evet, kaldır">
                                <input type="hidden" name="islem" value="kaldir"><input type="hidden" name="plaka" value="<?= e($k['plaka']) ?>">
                                <button type="submit" class="kucuk-buton">Yasağı kaldır</button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </div>
</section>

<?php require 'includes/footer.php'; ?>
