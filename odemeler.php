<?php
/*
 * odemeler.php — Ödeme ve gelir takibi
 * Alınan tüm ödemeler (abonelik ve ziyaretçi park ücreti), tarih aralığı ve türe göre süzülür.
 */
require_once 'includes/functions.php';

$tarihAl = function ($ad, $varsayilan) {
    $deger = isset($_GET[$ad]) && is_string($_GET[$ad]) ? $_GET[$ad] : '';
    $tarih = DateTime::createFromFormat('Y-m-d', $deger);
    return ($tarih && $tarih->format('Y-m-d') === $deger) ? $deger : $varsayilan;
};
$bas = $tarihAl('bas', date('Y-m-01'));
$bit = $tarihAl('bit', date('Y-m-d'));
if ($bas > $bit) { $gecici = $bas; $bas = $bit; $bit = $gecici; }
$tur = isset($_GET['tur']) && in_array($_GET['tur'], ['Abonelik', 'Ziyaretçi'], true) ? $_GET['tur'] : '';

$liste = []; $toplam = 0; $yonteme = [];
foreach (array_reverse($_SESSION['odemeler']) as $o) {
    $gun = date('Y-m-d', $o['zaman']);
    if ($gun < $bas || $gun > $bit || ($tur !== '' && $o['tur'] !== $tur)) {
        continue;
    }
    $liste[] = $o;
    $toplam += $o['tutar'];
    $yonteme[$o['yontem']] = ($yonteme[$o['yontem']] ?? 0) + $o['tutar'];
}

$sayfaBasligi = 'Ödemeler';
$aktifSayfa   = 'odeme';
require 'includes/header.php';
?>

<div class="sayfa-baslik">
    <div>
        <h1>Ödemeler</h1>
        <p>Abonelik ödemeleri ve ziyaretçi park ücretleri. Her ödemenin bir işlem numarası vardır.</p>
    </div>
</div>

<section class="kpi-izgara">
    <div class="kart kpi"><div class="kpi-ust"><span>Bugünkü Gelir</span><span class="kpi-ikon yesil"><?= ikon('para', 17) ?></span></div><div class="kpi-sayi"><?= e(paraYaz(gelirToplami(date('Y-m-d')))) ?></div><div class="kpi-alt"><?= date('d.m.Y') ?></div></div>
    <div class="kart kpi"><div class="kpi-ust"><span>Bu Ayki Gelir</span><span class="kpi-ikon"><?= ikon('takvim', 17) ?></span></div><div class="kpi-sayi"><?= e(paraYaz(gelirToplami(date('Y-m')))) ?></div><div class="kpi-alt"><?= date('m.Y') ?></div></div>
    <div class="kart kpi"><div class="kpi-ust"><span>Seçili Aralık</span><span class="kpi-ikon gri"><?= ikon('filtre', 17) ?></span></div><div class="kpi-sayi"><?= e(paraYaz($toplam)) ?></div><div class="kpi-alt"><?= count($liste) ?> ödeme</div></div>
    <div class="kart kpi"><div class="kpi-ust"><span>Yönteme Göre</span><span class="kpi-ikon gri"><?= ikon('kutu', 17) ?></span></div>
        <div class="kpi-alt" style="font-size: 13.5px; line-height: 1.8">
            <?php if (count($yonteme) === 0): ?>Kayıt yok<?php endif; ?>
            <?php foreach ($yonteme as $y => $t): ?><div><?= e($y) ?>: <strong><?= e(paraYaz($t)) ?></strong></div><?php endforeach; ?>
        </div>
    </div>
</section>

<section class="kart tablo-kart">
    <div class="tablo-ust">
        <h2>Ödeme Geçmişi <span class="sayi-rozet"><?= count($liste) ?> kayıt</span></h2>
    </div>
    <form class="tablo-ust filtre" method="get" action="odemeler.php">
        <label class="filtre-secim">Başlangıç <input type="date" name="bas" value="<?= e($bas) ?>"></label>
        <label class="filtre-secim">Bitiş <input type="date" name="bit" value="<?= e($bit) ?>"></label>
        <label class="filtre-secim">Tür
            <select name="tur">
                <option value="">Tümü</option>
                <option value="Abonelik" <?= $tur === 'Abonelik' ? 'selected' : '' ?>>Abonelik</option>
                <option value="Ziyaretçi" <?= $tur === 'Ziyaretçi' ? 'selected' : '' ?>>Ziyaretçi</option>
            </select>
        </label>
        <button type="submit" class="buton buton-kucuk"><?= ikon('filtre', 15) ?>Filtrele</button>
    </form>
    <?php if (count($liste) === 0): ?>
        <div class="bos-durum"><span class="ikon-daire"><?= ikon('para', 26) ?></span><h3>Ödeme yok</h3><p>Seçilen aralıkta ödeme kaydı bulunmuyor.</p></div>
    <?php else: ?>
        <div class="tablo-kaydir"><table>
            <thead><tr><th>İşlem no</th><th>Tarih</th><th>Plaka</th><th>Kişi</th><th>Tür</th><th>Açıklama</th><th>Yöntem</th><th>İndirim</th><th>Tutar</th><th>Alan</th></tr></thead>
            <tbody>
            <?php foreach ($liste as $o): ?>
                <tr>
                    <td class="soluk"><?= e($o['islem_no']) ?></td>
                    <td class="soluk"><?= e(zamanYaz($o['zaman'])) ?></td>
                    <td><span class="plaka plaka-kucuk"><span><?= e($o['plaka']) ?></span></span></td>
                    <td><?= e($o['ad']) ?></td>
                    <td><span class="etiket <?= $o['tur'] === 'Abonelik' ? 'etiket-yesil' : 'etiket-turuncu' ?>"><?= e($o['tur']) ?></span></td>
                    <td><?= e($o['aciklama']) ?></td>
                    <td><?= e($o['yontem']) ?></td>
                    <td><?= $o['indirim'] > 0 ? e(paraYaz($o['indirim'])) : '—' ?></td>
                    <td><strong><?= e(paraYaz($o['tutar'])) ?></strong></td>
                    <td class="soluk"><?= e($o['kullanici']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>

<?php require 'includes/footer.php'; ?>
