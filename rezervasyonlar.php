<?php
/*
 * rezervasyonlar.php — Yönetim paneli: online rezervasyonlar
 * Müşteri sayfasından (rezervasyon.php) gelen rezervasyonlar burada listelenir, onaylanır ya da iptal edilir.
 * İptal edilen rezervasyonun yeri o saatler için yeniden uygun olur.
 */
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id    = (int)($_POST['id'] ?? 0);
    $islem = formMetni('islem');
    $kayit = rzBul('id', $id);
    if ($kayit === null) {
        mesajKaydet('hata', 'Rezervasyon bulunamadı.');
    } elseif ($islem === 'onayla' && $kayit['durum'] === 'Bekliyor') {
        rzDurumDegistir($id, 'Onaylandı');
        logKaydet($kayit['no'] . ' rezervasyonu onaylandı');
        tgBildir('rezervasyon', '✅ <b>Rezervasyon onaylandı</b>' . "\n" . $kayit['no'] . ' · ' . tgK($kayit['plaka']) . ' · ' . yerAdi($kayit['yer']));
        mesajKaydet('basari', $kayit['no'] . ' onaylandı.');
    } elseif ($islem === 'iptal' && in_array($kayit['durum'], ['Bekliyor', 'Onaylandı'], true)) {
        rzDurumDegistir($id, 'İptal');
        logKaydet($kayit['no'] . ' rezervasyonu iptal edildi');
        tgBildir('rezervasyon', '❌ <b>Rezervasyon iptal edildi</b>' . "\n" . $kayit['no'] . ' · ' . tgK($kayit['plaka']) . ' · ' . yerAdi($kayit['yer']));
        mesajKaydet('basari', $kayit['no'] . ' iptal edildi. ' . yerAdi($kayit['yer']) . ' o saatler için yeniden uygun.');
    } else {
        mesajKaydet('hata', 'Bu rezervasyon için bu işlem yapılamaz (durum: ' . $kayit['durum'] . ').');
    }
    yonlendir('rezervasyonlar.php' . (isset($_POST['filtre']) && is_string($_POST['filtre']) ? '?filtre=' . urlencode($_POST['filtre']) : ''));
}

$tumu  = array_reverse(rzVeri()['liste']);
$simdi = time();
$bugun = date('Y-m-d');
$filtreler = [
    'tumu'     => ['Tümü',        function ($r) { return true; }],
    'bugun'    => ['Bugün',       function ($r) use ($bugun) { return date('Y-m-d', $r['giris']) <= $bugun && $bugun <= date('Y-m-d', $r['cikis']); }],
    'bekleyen' => ['Bekleyen',    function ($r) { return $r['durum'] === 'Bekliyor'; }],
    'onayli'   => ['Onaylanan',   function ($r) { return $r['durum'] === 'Onaylandı'; }],
    'aktif'    => ['Aktif',       function ($r) use ($simdi) { return $r['durum'] === 'Aktif' || ($r['durum'] === 'Onaylandı' && $r['giris'] <= $simdi && $simdi < $r['cikis']); }],
    'iptal'    => ['İptal',       function ($r) { return $r['durum'] === 'İptal'; }],
];
$filtre = isset($_GET['filtre']) && is_string($_GET['filtre']) && isset($filtreler[$_GET['filtre']]) ? $_GET['filtre'] : 'tumu';
$sayilar = [];
foreach ($filtreler as $anahtar => $f) {
    $sayilar[$anahtar] = count(array_filter($tumu, $f[1]));
}
$liste = array_values(array_filter($tumu, $filtreler[$filtre][1]));

$musteriAdresi = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\') . '/rezervasyon.php';

$sayfaBasligi = 'Rezervasyonlar';
$aktifSayfa   = 'rezervasyon';
require 'includes/header.php';
?>

<div class="sayfa-baslik">
    <div>
        <h1>Rezervasyonlar</h1>
        <p>Müşterilerin online yaptığı rezervasyonlar. Rezervasyonlu yer, başlamasına <?= RZ_TUTMA_DK ?> dakika kala başka araca verilmez.</p>
    </div>
    <div class="sayfa-aksiyon">
        <a href="rezervasyon.php" target="_blank" rel="noopener" class="buton buton-ikincil"><?= ikon('giris', 16) ?>Müşteri sayfasını aç</a>
    </div>
</div>

<section class="kpi-izgara">
    <div class="kart kpi"><div class="kpi-ust"><span>Bekleyen</span><span class="kpi-ikon turuncu"><?= ikon('saat', 17) ?></span></div><div class="kpi-sayi"><?= $sayilar['bekleyen'] ?></div><div class="kpi-alt">Onay bekliyor</div></div>
    <div class="kart kpi"><div class="kpi-ust"><span>Onaylanan</span><span class="kpi-ikon yesil"><?= ikon('onay', 17) ?></span></div><div class="kpi-sayi"><?= $sayilar['onayli'] ?></div><div class="kpi-alt">Gelecek ve süren</div></div>
    <div class="kart kpi"><div class="kpi-ust"><span>Aktif</span><span class="kpi-ikon"><?= ikon('arac', 17) ?></span></div><div class="kpi-sayi"><?= $sayilar['aktif'] ?></div><div class="kpi-alt">Şu an geçerli</div></div>
    <div class="kart kpi"><div class="kpi-ust"><span>Bugün</span><span class="kpi-ikon gri"><?= ikon('takvim', 17) ?></span></div><div class="kpi-sayi"><?= $sayilar['bugun'] ?></div><div class="kpi-alt">Bugüne denk gelen</div></div>
</section>

<section class="kart rapor-filtre">
    <span class="soluk">Müşteri bağlantısı:</span>
    <code id="musteri-adres"><?= e($musteriAdresi) ?></code>
    <button type="button" class="buton buton-ikincil buton-kucuk" onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById('musteri-adres').textContent).then(() => { this.lastChild.textContent = 'Kopyalandı'; })"><?= ikon('kaydet', 15) ?><span>Kopyala</span></button>
</section>

<section class="kart tablo-kart">
    <div class="tablo-ust">
        <h2>Rezervasyon Listesi <span class="sayi-rozet"><?= count($liste) ?> kayıt</span></h2>
        <div class="rapor-hazir">
            <?php foreach ($filtreler as $anahtar => $f): ?>
                <a class="buton buton-kucuk <?= $filtre === $anahtar ? '' : 'buton-ikincil' ?>" href="rezervasyonlar.php?filtre=<?= e($anahtar) ?>"><?= e($f[0]) ?> (<?= $sayilar[$anahtar] ?>)</a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php if (count($liste) === 0): ?>
        <div class="bos-durum"><span class="ikon-daire"><?= ikon('ajanda', 26) ?></span><h3>Rezervasyon yok</h3><p>Bu filtreye uyan rezervasyon bulunmuyor. Müşteri bağlantısını paylaşarak rezervasyon alabilirsiniz.</p></div>
    <?php else: ?>
        <div class="tablo-kaydir"><table>
            <thead><tr><th>Rezervasyon no</th><th>Müşteri</th><th>Telefon</th><th>Plaka</th><th>Park yeri</th><th>Giriş</th><th>Çıkış</th><th>Durum</th><th class="islem-hucre">İşlem</th></tr></thead>
            <tbody>
            <?php foreach ($liste as $r): ?>
                <tr>
                    <td><strong><?= e($r['no']) ?></strong><div class="soluk" style="font-size: 12px"><?= e(zamanYaz($r['olusturma'])) ?></div></td>
                    <td><span class="kisi"><span class="avatar"><?= e(bas_harfler($r['ad'])) ?></span><?= e($r['ad']) ?></span></td>
                    <td class="soluk"><?= e($r['telefon']) ?></td>
                    <td><span class="plaka plaka-kucuk"><span><?= e($r['plaka']) ?></span></span></td>
                    <td><strong><?= e(yerAdi($r['yer'])) ?></strong></td>
                    <td class="soluk"><?= e(zamanYaz($r['giris'])) ?></td>
                    <td class="soluk"><?= e(zamanYaz($r['cikis'])) ?></td>
                    <td><span class="etiket <?= rzDurumSinifi($r['durum']) ?>"><?= e($r['durum']) ?></span></td>
                    <td class="islem-hucre">
                        <div class="islemler">
                        <?php if ($r['durum'] === 'Bekliyor'): ?>
                            <form method="post" action="rezervasyonlar.php"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="islem" value="onayla"><input type="hidden" name="filtre" value="<?= e($filtre) ?>"><button type="submit" class="kucuk-buton giris-btn"><?= ikon('onay', 14) ?>Onayla</button></form>
                        <?php endif; ?>
                        <?php if (in_array($r['durum'], ['Bekliyor', 'Onaylandı'], true)): ?>
                            <form method="post" action="rezervasyonlar.php" data-onay="<?= e($r['no']) ?> rezervasyonu iptal edilsin mi? <?= e(yerAdi($r['yer'])) ?> o saatler için yeniden uygun olur." data-onay-baslik="Rezervasyonu iptal et" data-onay-buton="Evet, iptal et"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="islem" value="iptal"><input type="hidden" name="filtre" value="<?= e($filtre) ?>"><button type="submit" class="kucuk-buton cikis">İptal et</button></form>
                        <?php endif; ?>
                        <?php if (!in_array($r['durum'], ['Bekliyor', 'Onaylandı'], true)): ?><span class="soluk">—</span><?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>

<?php require 'includes/footer.php'; ?>
