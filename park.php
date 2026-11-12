<?php
/*
 * park.php — Park alanı yönetimi
 * Numaralı park yerlerinin anlık durumu. Yeşil: boş, kırmızı: dolu, sarı: rezerve, gri: kullanım dışı.
 * Yer durumunu yalnızca yönetici değiştirebilir.
 */
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    yoneticiGerek();
    $no    = (int)($_POST['no'] ?? 0);
    $durum = formMetni('durum');
    $dolu  = doluYerler();
    if (!isset($_SESSION['yerler'][$no]) || !in_array($durum, ['normal', 'rezerve', 'kapali'], true)) {
        mesajKaydet('hata', 'Geçersiz park yeri ya da durum.');
    } elseif (isset($dolu[$no]) && $durum !== 'normal') {
        mesajKaydet('hata', yerAdi($no) . ' şu an dolu (' . $dolu[$no]['plaka'] . '). Araç çıkmadan rezerve ya da kullanım dışı yapılamaz.');
    } else {
        $_SESSION['yerler'][$no]['durum']   = $durum;
        $_SESSION['yerler'][$no]['engelli'] = isset($_POST['engelli']);
        logKaydet(yerAdi($no) . ' park yeri güncellendi (' . $durum . ')');
        mesajKaydet('basari', yerAdi($no) . ' güncellendi.');
    }
    yonlendir('park.php');
}

$dolu  = doluYerler();
$rezli = rzSimdiRezerve();   // online rezervasyonla ayrılmış yerler
$sayac = ['bos' => 0, 'dolu' => 0, 'rezerve' => 0, 'kapali' => 0];
foreach ($_SESSION['yerler'] as $no => $yer) {
    if (isset($dolu[$no]))               { $sayac['dolu']++; }
    elseif ($yer['durum'] === 'rezerve' || isset($rezli[$no])) { $sayac['rezerve']++; }
    elseif ($yer['durum'] === 'kapali')  { $sayac['kapali']++; }
    else                                 { $sayac['bos']++; }
}

$sayfaBasligi = 'Park Alanları';
$aktifSayfa   = 'park';
require 'includes/header.php';
?>

<div class="sayfa-baslik">
    <div>
        <h1>Park Alanları</h1>
        <p>Araç girişinde uygun ilk boş yer otomatik atanır, çıkışta yer kendiliğinden boşalır.</p>
    </div>
</div>

<section class="kpi-izgara">
    <div class="kart kpi"><div class="kpi-ust"><span>Boş</span><i class="nokta" style="background: var(--yesil)"></i></div><div class="kpi-sayi"><?= $sayac['bos'] ?></div><div class="kpi-alt">Araç alınabilir</div></div>
    <div class="kart kpi"><div class="kpi-ust"><span>Dolu</span><i class="nokta" style="background: var(--kirmizi)"></i></div><div class="kpi-sayi"><?= $sayac['dolu'] ?></div><div class="kpi-alt">Araç var</div></div>
    <div class="kart kpi"><div class="kpi-ust"><span>Rezerve</span><i class="nokta" style="background: var(--turuncu)"></i></div><div class="kpi-sayi"><?= $sayac['rezerve'] ?></div><div class="kpi-alt">Ayrılmış, atama yapılmaz</div></div>
    <div class="kart kpi"><div class="kpi-ust"><span>Kullanım dışı</span><i class="nokta" style="background: var(--soluk)"></i></div><div class="kpi-sayi"><?= $sayac['kapali'] ?></div><div class="kpi-alt">Kapalı yerler</div></div>
</section>

<section class="panel-izgara">
    <div class="kart <?= yoneticiMi() ? 'kol-8' : 'kol-12' ?>">
        <div class="kart-ic">
            <div class="kart-ust"><div><h2>Yer Haritası</h2><p><?= TOPLAM_KAPASITE ?> park yeri</p></div></div>
            <div class="harita">
            <?php foreach ($_SESSION['yerler'] as $no => $yer):
                if (isset($dolu[$no]))               { $sinif = 'h-dolu';    $alt = $dolu[$no]['plaka']; }
                elseif (isset($rezli[$no]))          { $sinif = 'h-rezerve'; $alt = $rezli[$no]['plaka'] . ' ' . date('H:i', $rezli[$no]['giris']); }
                elseif ($yer['durum'] === 'rezerve') { $sinif = 'h-rezerve'; $alt = 'Rezerve'; }
                elseif ($yer['durum'] === 'kapali')  { $sinif = 'h-kapali';  $alt = 'Kapalı'; }
                else                                 { $sinif = 'h-bos';     $alt = 'Boş'; }
            ?>
                <div class="h-yer <?= $sinif ?>" title="<?= e(yerAdi($no) . ' · ' . $alt . (isset($dolu[$no]) ? ' · ' . $dolu[$no]['tip'] : '')) ?>">
                    <b><?= e(yerAdi($no)) ?><?php if ($yer['engelli']): ?><?= ikon('engelli', 13) ?><?php endif; ?></b>
                    <span><?= e($alt) ?></span>
                </div>
            <?php endforeach; ?>
            </div>
            <div class="yer-aciklama">
                <span><i class="nokta" style="background: var(--yesil)"></i>Boş</span>
                <span><i class="nokta" style="background: var(--kirmizi)"></i>Dolu</span>
                <span><i class="nokta" style="background: var(--turuncu)"></i>Rezerve</span>
                <span><i class="nokta" style="background: var(--soluk)"></i>Kullanım dışı</span>
                <span><?= ikon('engelli', 14) ?>Engelli park yeri</span>
            </div>
        </div>
    </div>

    <?php if (yoneticiMi()): ?>
    <form class="kart kol-4" method="post" action="park.php">
        <div class="kart-ic">
            <div class="kart-ust"><div><h2>Yer Durumunu Değiştir</h2><p>Yalnızca yönetici</p></div></div>
            <div class="alan">
                <label for="no">Park yeri</label>
                <select id="no" name="no" required>
                    <?php foreach ($_SESSION['yerler'] as $no => $yer): ?>
                        <option value="<?= $no ?>"><?= e(yerAdi($no)) ?><?= isset($dolu[$no]) ? ' (dolu)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="alan">
                <label for="durum">Durum</label>
                <select id="durum" name="durum" required>
                    <option value="normal">Kullanımda (boş / dolu)</option>
                    <option value="rezerve">Rezerve</option>
                    <option value="kapali">Kullanım dışı</option>
                </select>
            </div>
            <label class="onay-kutu"><input type="checkbox" name="engelli"> Engelli park yeri</label>
            <div class="form-butonlar"><button type="submit" class="buton"><?= ikon('kaydet', 16) ?>Kaydet</button></div>
        </div>
    </form>
    <?php endif; ?>
</section>

<?php require 'includes/footer.php'; ?>
