<?php
/*
 * raporlar.php — Raporlama
 * Tarih aralığına göre günlük giriş-çıkış ve gelir, en sık giren araçlar, süresi dolan aboneler,
 * abone listesi ve doluluk. Her rapor Excel için CSV olarak indirilebilir; sayfa PDF olarak yazdırılabilir.
 */
require_once 'includes/functions.php';

$tarihAl = function ($ad, $varsayilan) {
    $deger = isset($_GET[$ad]) && is_string($_GET[$ad]) ? $_GET[$ad] : '';
    $tarih = DateTime::createFromFormat('Y-m-d', $deger);
    return ($tarih && $tarih->format('Y-m-d') === $deger) ? $deger : $varsayilan;
};
$bas = $tarihAl('bas', date('Y-m-d', strtotime('-6 days')));
$bit = $tarihAl('bit', date('Y-m-d'));
if ($bas > $bit) { $gecici = $bas; $bas = $bit; $bit = $gecici; }
if ((strtotime($bit) - strtotime($bas)) / 86400 > 366) {
    $bas = date('Y-m-d', strtotime($bit . ' -366 days'));   // en fazla bir yıl
}

// 1) Günlük özet: [gün => giriş, çıkış, gelir]
$gunler = [];
for ($g = strtotime($bas); $g <= strtotime($bit); $g += 86400) {
    $gunler[date('Y-m-d', $g)] = ['giris' => 0, 'cikis' => 0, 'gelir' => 0];
}
$aracSayaci = [];
foreach ($_SESSION['hareketler'] as $h) {
    if ($h['giris'] !== null && isset($gunler[date('Y-m-d', $h['giris'])])) {
        $gunler[date('Y-m-d', $h['giris'])]['giris']++;
        if (!isset($aracSayaci[$h['plaka']])) {
            $aracSayaci[$h['plaka']] = ['plaka' => $h['plaka'], 'ad' => $h['ad'], 'tip' => $h['tip'], 'giris' => 0];
        }
        $aracSayaci[$h['plaka']]['giris']++;
    }
    if ($h['cikis'] !== null && isset($gunler[date('Y-m-d', $h['cikis'])])) {
        $gunler[date('Y-m-d', $h['cikis'])]['cikis']++;
    }
}
foreach ($_SESSION['odemeler'] as $o) {
    if (isset($gunler[date('Y-m-d', $o['zaman'])])) {
        $gunler[date('Y-m-d', $o['zaman'])]['gelir'] += $o['tutar'];
    }
}
$toplam = ['giris' => array_sum(array_column($gunler, 'giris')), 'cikis' => array_sum(array_column($gunler, 'cikis')), 'gelir' => array_sum(array_column($gunler, 'gelir'))];
$enGelir = max(1, max(array_column($gunler, 'gelir')));

// 2) En sık giriş yapan araçlar
usort($aracSayaci, function ($a, $b) { return $b['giris'] <=> $a['giris'] ?: strcmp($a['plaka'], $b['plaka']); });
$enSik = array_slice($aracSayaci, 0, 10);

// 3) Süresi dolan ve 7 gün içinde bitecek aboneler
$bitenler = array_values(array_filter($_SESSION['araclar'], function ($k) { return kalanGun($k['son_tarih']) <= 7; }));
usort($bitenler, function ($a, $b) { return strcmp($a['son_tarih'], $b['son_tarih']); });

// 4) Doluluk
$dolu = count(doluYerler());

// CSV indirme (Excel'de açılır). Noktalı virgül ayırıcı ve BOM, Türkçe Excel içindir.
$indir = isset($_GET['indir']) && is_string($_GET['indir']) ? $_GET['indir'] : '';
if ($indir !== '') {
    $satirlar = [];
    if ($indir === 'gunluk') {
        $satirlar[] = ['Tarih', 'Giriş', 'Çıkış', 'Gelir (TL)'];
        foreach ($gunler as $gun => $d) { $satirlar[] = [date('d.m.Y', strtotime($gun)), $d['giris'], $d['cikis'], number_format($d['gelir'], 2, ',', '')]; }
    } elseif ($indir === 'araclar') {
        $satirlar[] = ['Plaka', 'Ad', 'Tür', 'Giriş sayısı'];
        foreach ($aracSayaci as $a) { $satirlar[] = [$a['plaka'], $a['ad'], $a['tip'], $a['giris']]; }
    } elseif ($indir === 'bitenler') {
        $satirlar[] = ['Plaka', 'Ad Soyad', 'Telefon', 'Abone türü', 'Bitiş tarihi', 'Kalan gün'];
        foreach ($bitenler as $k) { $satirlar[] = [$k['plaka'], $k['ad_soyad'], $k['telefon'], $k['abone_turu'], date('d.m.Y', strtotime($k['son_tarih'])), kalanGun($k['son_tarih'])]; }
    } elseif ($indir === 'aboneler') {
        $satirlar[] = ['Plaka', 'Ad Soyad', 'Telefon', 'Marka', 'Model', 'Renk', 'Abone türü', 'Bitiş tarihi', 'Durum'];
        foreach ($_SESSION['araclar'] as $k) { $satirlar[] = [$k['plaka'], $k['ad_soyad'], $k['telefon'], $k['marka'], $k['model'], $k['renk'], $k['abone_turu'], date('d.m.Y', strtotime($k['son_tarih'])), $k['durum']]; }
    } elseif ($indir === 'odemeler') {
        $satirlar[] = ['İşlem no', 'Tarih', 'Plaka', 'Kişi', 'Tür', 'Açıklama', 'Yöntem', 'İndirim (TL)', 'Tutar (TL)'];
        foreach ($_SESSION['odemeler'] as $o) {
            if (isset($gunler[date('Y-m-d', $o['zaman'])])) {
                $satirlar[] = [$o['islem_no'], date('d.m.Y H:i', $o['zaman']), $o['plaka'], $o['ad'], $o['tur'], $o['aciklama'], $o['yontem'], number_format($o['indirim'], 2, ',', ''), number_format($o['tutar'], 2, ',', '')];
            }
        }
    }
    if (count($satirlar) > 0) {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="parktakip-' . $indir . '-' . date('Ymd') . '.csv"');
        $cikti = fopen('php://output', 'w');
        fwrite($cikti, "\xEF\xBB\xBF");
        foreach ($satirlar as $satir) {
            // Excel'in = + - @ ile başlayan hücreyi formül sanmaması için başına tek tırnak konur
            $satir = array_map(function ($hucre) { return preg_match('/^[=+\-@]/', (string)$hucre) ? "'" . $hucre : $hucre; }, $satir);
            fputcsv($cikti, $satir, ';', '"', '\\');
        }
        logKaydet('Rapor indirildi: ' . $indir);
        exit;
    }
}

$aralik = 'bas=' . urlencode($bas) . '&bit=' . urlencode($bit);
$hazir  = [
    'Bugün'    => [date('Y-m-d'), date('Y-m-d')],
    'Son 7 gün' => [date('Y-m-d', strtotime('-6 days')), date('Y-m-d')],
    'Bu ay'    => [date('Y-m-01'), date('Y-m-d')],
    'Son 30 gün' => [date('Y-m-d', strtotime('-29 days')), date('Y-m-d')],
];

$sayfaBasligi = 'Raporlar';
$aktifSayfa   = 'rapor';
require 'includes/header.php';
?>

<div class="sayfa-baslik">
    <div>
        <h1>Raporlar</h1>
        <p><?= e(date('d.m.Y', strtotime($bas))) ?> – <?= e(date('d.m.Y', strtotime($bit))) ?> arası. Raporlar oturumdaki gerçek kayıtlardan üretilir.</p>
    </div>
    <div class="sayfa-aksiyon yazdirma-gizle">
        <button type="button" class="buton buton-ikincil" onclick="window.print()"><?= ikon('yazdir', 16) ?>Yazdır / PDF</button>
    </div>
</div>

<form class="kart rapor-filtre yazdirma-gizle" method="get" action="raporlar.php">
    <label class="filtre-secim">Başlangıç <input type="date" name="bas" value="<?= e($bas) ?>"></label>
    <label class="filtre-secim">Bitiş <input type="date" name="bit" value="<?= e($bit) ?>"></label>
    <button type="submit" class="buton buton-kucuk"><?= ikon('filtre', 15) ?>Uygula</button>
    <span class="rapor-hazir">
        <?php foreach ($hazir as $ad => $t): ?>
            <a class="buton buton-ikincil buton-kucuk" href="raporlar.php?bas=<?= e($t[0]) ?>&amp;bit=<?= e($t[1]) ?>"><?= e($ad) ?></a>
        <?php endforeach; ?>
    </span>
</form>

<section class="kpi-izgara">
    <div class="kart kpi"><div class="kpi-ust"><span>Giriş</span><span class="kpi-ikon yesil"><?= ikon('giris', 17) ?></span></div><div class="kpi-sayi"><?= $toplam['giris'] ?></div><div class="kpi-alt">Seçili aralıkta</div></div>
    <div class="kart kpi"><div class="kpi-ust"><span>Çıkış</span><span class="kpi-ikon gri"><?= ikon('cikis', 17) ?></span></div><div class="kpi-sayi"><?= $toplam['cikis'] ?></div><div class="kpi-alt">Seçili aralıkta</div></div>
    <div class="kart kpi"><div class="kpi-ust"><span>Gelir</span><span class="kpi-ikon yesil"><?= ikon('para', 17) ?></span></div><div class="kpi-sayi"><?= e(paraYaz($toplam['gelir'])) ?></div><div class="kpi-alt">Bu ay: <?= e(paraYaz(gelirToplami(date('Y-m')))) ?></div></div>
    <div class="kart kpi"><div class="kpi-ust"><span>Anlık Doluluk</span><span class="kpi-ikon"><?= ikon('izgara', 17) ?></span></div><div class="kpi-sayi">%<?= (int)round($dolu / TOPLAM_KAPASITE * 100) ?></div><div class="kpi-alt"><?= $dolu ?> dolu · <?= bosParkYeri() ?> boş · <?= TOPLAM_KAPASITE - $dolu - bosParkYeri() ?> rezerve/kapalı</div></div>
</section>

<section class="kart tablo-kart" style="margin-bottom: 16px">
    <div class="tablo-ust">
        <h2>Günlük Giriş-Çıkış ve Gelir <span class="sayi-rozet"><?= count($gunler) ?> gün</span></h2>
        <a class="buton buton-ikincil buton-kucuk yazdirma-gizle" href="raporlar.php?<?= e($aralik) ?>&amp;indir=gunluk"><?= ikon('indir', 15) ?>Excel (CSV)</a>
        <a class="buton buton-ikincil buton-kucuk yazdirma-gizle" href="raporlar.php?<?= e($aralik) ?>&amp;indir=odemeler"><?= ikon('indir', 15) ?>Ödemeler (CSV)</a>
    </div>
    <div class="tablo-kaydir"><table style="min-width: 560px">
        <thead><tr><th>Tarih</th><th>Giriş</th><th>Çıkış</th><th>Gelir</th><th style="width: 40%">Gelir grafiği</th></tr></thead>
        <tbody>
        <?php foreach (array_reverse($gunler, true) as $gun => $d): ?>
            <tr>
                <td><?= e(date('d.m.Y', strtotime($gun))) ?></td>
                <td><?= $d['giris'] ?></td>
                <td><?= $d['cikis'] ?></td>
                <td><strong><?= e(paraYaz($d['gelir'])) ?></strong></td>
                <td><span class="ilerleme"><span style="width: <?= (int)round($d['gelir'] / $enGelir * 100) ?>%; background: var(--yesil)"></span></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>

<section class="panel-izgara">
    <div class="kart tablo-kart kol-6">
        <div class="tablo-ust">
            <h2>En Sık Giriş Yapan Araçlar</h2>
            <a class="buton buton-ikincil buton-kucuk yazdirma-gizle" href="raporlar.php?<?= e($aralik) ?>&amp;indir=araclar"><?= ikon('indir', 15) ?>CSV</a>
        </div>
        <?php if (count($enSik) === 0): ?>
            <div class="bos-durum"><span class="ikon-daire"><?= ikon('arac', 26) ?></span><h3>Giriş yok</h3><p>Seçilen aralıkta giriş kaydı bulunmuyor.</p></div>
        <?php else: ?>
            <div class="tablo-kaydir"><table style="min-width: 420px">
                <thead><tr><th>#</th><th>Plaka</th><th>Ad</th><th>Tür</th><th>Giriş</th></tr></thead>
                <tbody>
                <?php foreach ($enSik as $i => $a): ?>
                    <tr><td class="soluk"><?= $i + 1 ?></td><td><span class="plaka plaka-kucuk"><span><?= e($a['plaka']) ?></span></span></td><td><?= e($a['ad']) ?></td><td class="soluk"><?= e($a['tip']) ?></td><td><strong><?= $a['giris'] ?></strong></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </div>

    <div class="kart tablo-kart kol-6" id="suresi-dolan">
        <div class="tablo-ust">
            <h2>Süresi Dolan ve Bitmek Üzere Olanlar <span class="sayi-rozet"><?= count($bitenler) ?></span></h2>
            <a class="buton buton-ikincil buton-kucuk yazdirma-gizle" href="raporlar.php?indir=bitenler"><?= ikon('indir', 15) ?>CSV</a>
        </div>
        <?php if (count($bitenler) === 0): ?>
            <div class="bos-durum"><span class="ikon-daire"><?= ikon('onay', 26) ?></span><h3>Hepsi güncel</h3><p>7 gün içinde biten ya da süresi dolmuş abonelik yok.</p></div>
        <?php else: ?>
            <div class="tablo-kaydir"><table style="min-width: 420px">
                <thead><tr><th>Plaka</th><th>Ad soyad</th><th>Bitiş</th><th>Durum</th></tr></thead>
                <tbody>
                <?php foreach ($bitenler as $k): $et = abonelikEtiketi($k['son_tarih']); ?>
                    <tr><td><span class="plaka plaka-kucuk"><span><?= e($k['plaka']) ?></span></span></td><td><a href="abone.php?id=<?= (int)$k['id'] ?>"><?= e($k['ad_soyad']) ?></a></td><td class="soluk"><?= e(date('d.m.Y', strtotime($k['son_tarih']))) ?></td><td><span class="etiket <?= $et['sinif'] ?>"><?= e($et['metin']) ?></span></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </div>
</section>

<section class="kart yazdirma-gizle">
    <div class="kart-ic rapor-indir">
        <div><h2 style="font-size: 15px">Abone listesi</h2><p class="soluk"><?= count($_SESSION['araclar']) ?> abonenin tüm bilgileri</p></div>
        <a class="buton buton-ikincil" href="raporlar.php?indir=aboneler"><?= ikon('indir', 16) ?>Excel (CSV) indir</a>
    </div>
</section>

<?php require 'includes/footer.php'; ?>
