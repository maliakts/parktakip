<?php
/*
 * kullanicilar.php — Kullanıcı yönetimi ve işlem geçmişi (yalnızca yönetici)
 */
require_once 'includes/functions.php';
yoneticiGerek();

$hata = ''; $veri = ['kadi' => '', 'ad' => '', 'rol' => 'Personel'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $islem = formMetni('islem');
    if ($islem === 'durum') {
        $kadi = formMetni('kadi');
        foreach ($_SESSION['kullanicilar'] as $sira => $k) {
            if ($k['kadi'] !== $kadi) {
                continue;
            }
            if ($kadi === $_SESSION['kullanici']['kadi']) {
                mesajKaydet('hata', 'Kendi hesabınızı pasife alamazsınız.');
            } else {
                $_SESSION['kullanicilar'][$sira]['aktif'] = !$k['aktif'];
                logKaydet($kadi . ' kullanıcısı ' . ($k['aktif'] ? 'pasife alındı' : 'aktif edildi'));
                mesajKaydet('basari', $kadi . ' güncellendi.');
            }
        }
        yonlendir('kullanicilar.php');
    }
    $veri  = ['kadi' => strtolower(formMetni('kadi')), 'ad' => formMetni('ad'), 'rol' => formMetni('rol')];
    $sifre = isset($_POST['sifre']) && is_string($_POST['sifre']) ? $_POST['sifre'] : '';
    if (preg_match('/^[a-z0-9_.]{3,20}$/', $veri['kadi']) !== 1) {
        $hata = 'Kullanıcı adı 3-20 karakter olmalı; küçük harf, rakam, nokta ve alt çizgi içerebilir.';
    } elseif (in_array($veri['kadi'], array_column($_SESSION['kullanicilar'], 'kadi'), true)) {
        $hata = 'Bu kullanıcı adı zaten var.';
    } elseif (mb_strlen($veri['ad'], 'UTF-8') < 3 || mb_strlen($veri['ad'], 'UTF-8') > 60) {
        $hata = 'Ad soyad 3-60 karakter olmalıdır.';
    } elseif (!in_array($veri['rol'], ROLLER, true)) {
        $hata = 'Lütfen bir rol seçiniz.';
    } elseif (strlen($sifre) < 8 || strlen($sifre) > 72) {
        $hata = 'Şifre en az 8 karakter olmalıdır.';
    } else {
        $_SESSION['kullanicilar'][] = $veri + ['sifre' => password_hash($sifre, PASSWORD_DEFAULT), 'aktif' => true];
        logKaydet($veri['kadi'] . ' kullanıcısı eklendi (' . $veri['rol'] . ')');
        mesajKaydet('basari', $veri['kadi'] . ' kullanıcısı eklendi.');
        yonlendir('kullanicilar.php');
    }
}
$loglar = array_slice(array_reverse($_SESSION['loglar']), 0, 100);

$sayfaBasligi = 'Kullanıcı Yönetimi';
$aktifSayfa   = 'kullanici';
require 'includes/header.php';
?>

<div class="sayfa-baslik">
    <div>
        <h1>Kullanıcı Yönetimi</h1>
        <p>Yönetici tüm modüllere erişir. Personel paket, park yeri, kullanıcı ve ayar değiştiremez; abone silemez.</p>
    </div>
</div>

<?php if ($hata !== ''): ?>
    <div class="bildirim bildirim-hata" role="alert"><?= ikon('hata', 20) ?><span class="bildirim-metin"><?= e($hata) ?></span></div>
<?php endif; ?>

<section class="panel-izgara">
    <form class="kart kol-4" method="post" action="kullanicilar.php" novalidate autocomplete="off">
        <div class="kart-ic">
            <div class="kart-ust"><div><h2>Yeni Kullanıcı</h2><p>Şifre hash'lenerek saklanır</p></div></div>
            <input type="hidden" name="islem" value="ekle">
            <div class="alan"><label for="kadi">Kullanıcı adı *</label><input type="text" id="kadi" name="kadi" maxlength="20" value="<?= e($veri['kadi']) ?>"></div>
            <div class="alan"><label for="ad">Ad soyad *</label><input type="text" id="ad" name="ad" maxlength="60" value="<?= e($veri['ad']) ?>"></div>
            <div class="alan"><label for="rol">Rol *</label>
                <select id="rol" name="rol"><?php foreach (ROLLER as $r): ?><option value="<?= e($r) ?>" <?= $veri['rol'] === $r ? 'selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?></select>
            </div>
            <div class="alan"><label for="sifre">Şifre *</label><input type="password" id="sifre" name="sifre" maxlength="72" autocomplete="new-password"><span class="ipucu">En az 8 karakter.</span></div>
            <div class="form-butonlar"><button type="submit" class="buton"><?= ikon('arti', 16) ?>Kullanıcı ekle</button></div>
        </div>
    </form>

    <div class="kart tablo-kart kol-8">
        <div class="tablo-ust"><h2>Kullanıcılar <span class="sayi-rozet"><?= count($_SESSION['kullanicilar']) ?> kayıt</span></h2></div>
        <div class="tablo-kaydir"><table style="min-width: 560px">
            <thead><tr><th>Kullanıcı adı</th><th>Ad soyad</th><th>Rol</th><th>Durum</th><th class="islem-hucre">İşlem</th></tr></thead>
            <tbody>
            <?php foreach ($_SESSION['kullanicilar'] as $k): ?>
                <tr>
                    <td><strong><?= e($k['kadi']) ?></strong></td>
                    <td><span class="kisi"><span class="avatar"><?= e(bas_harfler($k['ad'])) ?></span><?= e($k['ad']) ?></span></td>
                    <td><span class="etiket <?= $k['rol'] === 'Yönetici' ? 'etiket-turuncu' : 'etiket-yesil' ?>"><?= e($k['rol']) ?></span></td>
                    <td><span class="durum <?= $k['aktif'] ? 'durum-iceride' : 'durum-disarida' ?>"><?= $k['aktif'] ? 'Aktif' : 'Pasif' ?></span></td>
                    <td class="islem-hucre">
                        <?php if ($k['kadi'] !== $_SESSION['kullanici']['kadi']): ?>
                        <form method="post" action="kullanicilar.php" class="islemler">
                            <input type="hidden" name="islem" value="durum"><input type="hidden" name="kadi" value="<?= e($k['kadi']) ?>">
                            <button type="submit" class="kucuk-buton"><?= $k['aktif'] ? 'Pasife al' : 'Aktif et' ?></button>
                        </form>
                        <?php else: ?><span class="soluk">Siz</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
</section>

<section class="kart tablo-kart">
    <div class="tablo-ust"><h2>İşlem Geçmişi <span class="sayi-rozet">son <?= count($loglar) ?> kayıt</span></h2></div>
    <?php if (count($loglar) === 0): ?>
        <div class="bos-durum"><span class="ikon-daire"><?= ikon('saat', 26) ?></span><h3>Kayıt yok</h3><p>Yapılan işlemler burada listelenir.</p></div>
    <?php else: ?>
        <div class="tablo-kaydir"><table style="min-width: 560px">
            <thead><tr><th>Zaman</th><th>Kullanıcı</th><th>İşlem</th></tr></thead>
            <tbody>
            <?php foreach ($loglar as $l): ?>
                <tr><td class="soluk"><?= e(date('d.m.Y H:i:s', $l['zaman'])) ?></td><td><strong><?= e($l['kullanici']) ?></strong></td><td><?= e($l['islem']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>

<?php require 'includes/footer.php'; ?>
