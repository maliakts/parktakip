<?php
/*
 * abone.php — Abone kartı
 * Abone bilgilerini düzenleme, abonelik yenileme, pasife alma ve
 * abonelik / ödeme / giriş-çıkış geçmişi bu sayfadadır.
 */
require_once 'includes/functions.php';

$id   = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$sira = aboneSirasi($id);
if ($sira === null) {
    mesajKaydet('hata', 'Abone bulunamadı.');
    yonlendir('index.php#aboneler');
}

$hatalar = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $islem = formMetni('islem');

    if ($islem === 'guncelle') {
        $veri = ['ad_soyad' => formMetni('ad_soyad'), 'telefon' => formMetni('telefon'), 'marka' => formMetni('marka'), 'model' => formMetni('model'), 'renk' => formMetni('renk')];
        if (mb_strlen($veri['ad_soyad'], 'UTF-8') < 3 || mb_strlen($veri['ad_soyad'], 'UTF-8') > 60 || preg_match("/^\p{L}[\p{L} .'-]*$/u", $veri['ad_soyad']) !== 1) {
            $hatalar['ad_soyad'] = 'Ad soyad 3-60 karakter olmalı ve yalnızca harflerden oluşmalıdır.';
        }
        $hatalar += aracBilgisiDogrula($veri);
        if (count($hatalar) === 0) {
            foreach ($veri as $alan => $deger) {
                $_SESSION['araclar'][$sira][$alan] = $deger;
            }
            logKaydet($_SESSION['araclar'][$sira]['plaka'] . ' abone bilgileri güncellendi');
            mesajKaydet('basari', 'Abone bilgileri güncellendi.');
            yonlendir('abone.php?id=' . $id);
        }
    } elseif ($islem === 'yenile') {
        $sonuc = abonelikYenile($sira, formMetni('paket'), formMetni('odeme'), (float)str_replace(',', '.', formMetni('indirim')));
        mesajKaydet($sonuc['tamam'] ? 'basari' : 'hata', $sonuc['mesaj']);
        yonlendir('abone.php?id=' . $id);
    } elseif ($islem === 'pasif') {
        if ($_SESSION['araclar'][$sira]['durum'] === 'İçeride' && empty($_SESSION['araclar'][$sira]['pasif'])) {
            mesajKaydet('hata', 'Araç içerideyken abone pasife alınamaz. Önce çıkışını yapınız.');
        } else {
            $_SESSION['araclar'][$sira]['pasif'] = empty($_SESSION['araclar'][$sira]['pasif']);
            $yeni = $_SESSION['araclar'][$sira]['pasif'] ? 'pasife alındı' : 'yeniden aktif edildi';
            logKaydet($_SESSION['araclar'][$sira]['plaka'] . ' ' . $yeni);
            mesajKaydet('basari', 'Abone ' . $yeni . '.');
        }
        yonlendir('abone.php?id=' . $id);
    }
}

$abone  = $_SESSION['araclar'][$sira];
$etiket = abonelikEtiketi($abone['son_tarih']);
$pasif  = !empty($abone['pasif']);

$abonelikler = array_reverse(array_values(array_filter($_SESSION['abonelik_gecmisi'], function ($a) use ($id) { return $a['abone_id'] === $id; })));
$odemeler    = array_reverse(array_values(array_filter($_SESSION['odemeler'], function ($o) use ($abone) { return $o['plaka'] === $abone['plaka']; })));
$hareketler  = array_reverse(array_values(array_filter($_SESSION['hareketler'], function ($h) use ($abone) { return $h['plaka'] === $abone['plaka']; })));
$acik        = acikHareket($abone['plaka']);

$sayfaBasligi = 'Abone Kartı';
$aktifSayfa   = 'abone';
require 'includes/header.php';
?>

<div class="sayfa-baslik">
    <div>
        <h1><?= e($abone['ad_soyad']) ?></h1>
        <p><span class="plaka"><span><?= e($abone['plaka']) ?></span></span></p>
    </div>
    <div class="sayfa-aksiyon">
        <a href="index.php#aboneler" class="buton buton-ikincil"><?= ikon('geri', 16) ?>Abone listesi</a>
    </div>
</div>

<section class="kpi-izgara">
    <div class="kart kpi">
        <div class="kpi-ust"><span>Abonelik</span><span class="kpi-ikon"><?= ikon('paket', 17) ?></span></div>
        <div class="kpi-sayi" style="font-size: 26px"><?= e($abone['abone_turu']) ?></div>
        <div class="kpi-alt"><span class="etiket <?= $pasif ? 'etiket-kirmizi' : $etiket['sinif'] ?>"><?= $pasif ? 'Pasif' : e($etiket['metin']) ?></span></div>
    </div>
    <div class="kart kpi">
        <div class="kpi-ust"><span>Bitiş Tarihi</span><span class="kpi-ikon gri"><?= ikon('takvim', 17) ?></span></div>
        <div class="kpi-sayi" style="font-size: 26px"><?= e(date('d.m.Y', strtotime($abone['son_tarih']))) ?></div>
        <div class="kpi-alt"><?= kalanGun($abone['son_tarih']) >= 0 ? kalanGun($abone['son_tarih']) . ' gün kaldı' : abs(kalanGun($abone['son_tarih'])) . ' gün önce doldu' ?></div>
    </div>
    <div class="kart kpi">
        <div class="kpi-ust"><span>Durum</span><span class="kpi-ikon yesil"><?= ikon('arac', 17) ?></span></div>
        <div class="kpi-sayi" style="font-size: 26px"><?= e($abone['durum']) ?></div>
        <div class="kpi-alt"><?= $acik !== null ? 'Park yeri ' . e(yerAdi($acik['yer'])) . ' · ' . e(zamanYaz($acik['giris'])) : 'Son işlem: ' . e($abone['son_islem']) ?></div>
    </div>
    <div class="kart kpi">
        <div class="kpi-ust"><span>Toplam Ödeme</span><span class="kpi-ikon yesil"><?= ikon('para', 17) ?></span></div>
        <div class="kpi-sayi" style="font-size: 26px"><?= e(paraYaz(array_sum(array_column($odemeler, 'tutar')))) ?></div>
        <div class="kpi-alt"><?= count($odemeler) ?> ödeme</div>
    </div>
</section>

<section class="panel-izgara">
    <form class="kart kol-7" method="post" action="abone.php" novalidate>
        <div class="kart-ic">
            <div class="kart-ust"><div><h2>Abone Bilgileri</h2><p>Kayıt tarihi: <?= $abone['kayit_tarihi'] ? e(date('d.m.Y', strtotime($abone['kayit_tarihi']))) : 'örnek kayıt' ?></p></div></div>
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="islem" value="guncelle">
            <div class="alan-ikili">
                <div class="alan <?= isset($hatalar['ad_soyad']) ? 'alan-hatali' : '' ?>">
                    <label for="ad_soyad">Ad soyad *</label>
                    <input type="text" id="ad_soyad" name="ad_soyad" maxlength="60" value="<?= e($_POST['ad_soyad'] ?? $abone['ad_soyad']) ?>">
                    <?php if (isset($hatalar['ad_soyad'])): ?><span class="hata-metni"><?= e($hatalar['ad_soyad']) ?></span><?php endif; ?>
                </div>
                <div class="alan <?= isset($hatalar['telefon']) ? 'alan-hatali' : '' ?>">
                    <label for="telefon">Telefon</label>
                    <input type="text" id="telefon" name="telefon" maxlength="16" placeholder="05xx xxx xx xx" value="<?= e($_POST['telefon'] ?? $abone['telefon']) ?>">
                    <?php if (isset($hatalar['telefon'])): ?><span class="hata-metni"><?= e($hatalar['telefon']) ?></span><?php endif; ?>
                </div>
            </div>
            <div class="alan-ikili">
                <div class="alan"><label for="marka">Araç markası</label><input type="text" id="marka" name="marka" maxlength="30" value="<?= e($_POST['marka'] ?? $abone['marka']) ?>"></div>
                <div class="alan"><label for="model">Araç modeli</label><input type="text" id="model" name="model" maxlength="30" value="<?= e($_POST['model'] ?? $abone['model']) ?>"></div>
            </div>
            <div class="alan"><label for="renk">Araç rengi</label><input type="text" id="renk" name="renk" maxlength="20" value="<?= e($_POST['renk'] ?? $abone['renk']) ?>"></div>
            <div class="form-butonlar">
                <button type="submit" class="buton"><?= ikon('kaydet', 16) ?>Bilgileri kaydet</button>
            </div>
        </div>
    </form>

    <div class="kart kol-5">
        <div class="kart-ic">
            <div class="kart-ust"><div><h2>Aboneliği Yenile</h2><p>Süre, mevcut bitiş tarihinin üstüne eklenir</p></div></div>
            <form method="post" action="abone.php">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="islem" value="yenile">
                <div class="alan">
                    <label for="paket">Paket</label>
                    <select id="paket" name="paket" required>
                        <?php foreach ($_SESSION['paketler'] as $paket): if (!$paket['aktif']) { continue; } ?>
                            <option value="<?= e($paket['ad']) ?>" <?= $paket['ad'] === $abone['abone_turu'] ? 'selected' : '' ?>><?= e($paket['ad']) ?> — <?= e(paraYaz($paket['fiyat'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="alan-ikili">
                    <div class="alan">
                        <label for="odeme">Ödeme yöntemi</label>
                        <select id="odeme" name="odeme" required>
                            <?php foreach (ODEME_YONTEMLERI as $y): ?><option value="<?= e($y) ?>"><?= e($y) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="alan">
                        <label for="indirim">İndirim (%)</label>
                        <input type="text" id="indirim" name="indirim" value="0" maxlength="5" inputmode="decimal">
                    </div>
                </div>
                <div class="form-butonlar">
                    <button type="submit" class="buton"><?= ikon('yenile', 16) ?>Ödeme al ve yenile</button>
                </div>
            </form>
            <form method="post" action="abone.php" style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--kenar)">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="islem" value="pasif">
                <button type="submit" class="buton buton-ikincil buton-kucuk"><?= ikon($pasif ? 'onay' : 'yasak', 15) ?><?= $pasif ? 'Aboneyi aktif et' : 'Aboneyi pasife al' ?></button>
                <span class="ipucu" style="display: block; margin-top: 6px; font-size: 12px; color: var(--soluk)">Pasif abone giriş yapamaz, kaydı ve geçmişi korunur.</span>
            </form>
        </div>
    </div>
</section>

<section class="kart tablo-kart" style="margin-bottom: 16px">
    <div class="tablo-ust"><h2>Abonelik ve Ödeme Geçmişi <span class="sayi-rozet"><?= count($odemeler) ?> kayıt</span></h2></div>
    <?php if (count($odemeler) === 0): ?>
        <div class="bos-durum"><span class="ikon-daire"><?= ikon('para', 26) ?></span><h3>Ödeme kaydı yok</h3><p>Bu oturumda bu aboneden ödeme alınmadı.</p></div>
    <?php else: ?>
        <div class="tablo-kaydir"><table>
            <thead><tr><th>İşlem no</th><th>Tarih</th><th>Açıklama</th><th>Yöntem</th><th>İndirim</th><th>Tutar</th></tr></thead>
            <tbody>
            <?php foreach ($odemeler as $o): ?>
                <tr><td class="soluk"><?= e($o['islem_no']) ?></td><td class="soluk"><?= e(zamanYaz($o['zaman'])) ?></td><td><?= e($o['aciklama']) ?></td><td><?= e($o['yontem']) ?></td><td><?= $o['indirim'] > 0 ? e(paraYaz($o['indirim'])) : '—' ?></td><td><strong><?= e(paraYaz($o['tutar'])) ?></strong></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>

<section class="kart tablo-kart">
    <div class="tablo-ust"><h2>Giriş-Çıkış Geçmişi <span class="sayi-rozet"><?= count($hareketler) ?> kayıt</span></h2></div>
    <?php if (count($hareketler) === 0): ?>
        <div class="bos-durum"><span class="ikon-daire"><?= ikon('saat', 26) ?></span><h3>Hareket yok</h3><p>Bu aracın giriş-çıkış kaydı bulunmuyor.</p></div>
    <?php else: ?>
        <div class="tablo-kaydir"><table>
            <thead><tr><th>Park yeri</th><th>Giriş</th><th>Çıkış</th><th>Süre</th></tr></thead>
            <tbody>
            <?php foreach ($hareketler as $h): ?>
                <tr><td><?= e(yerAdi($h['yer'])) ?></td><td class="soluk"><?= e(zamanYaz($h['giris'])) ?></td><td class="soluk"><?= $h['cikis'] === null ? '<span class="durum durum-iceride">İçeride</span>' : e(zamanYaz($h['cikis'])) ?></td><td><?= e(sureYazi(sureDakika($h['giris'], $h['cikis']))) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>

<?php require 'includes/footer.php'; ?>
