<?php
/*
 * index.php — Ana sayfa
 * Doluluk özeti, sayaçlar, grafikler, filtreleme formu (GET) ve abone listesi bu sayfadadır.
 */
require_once 'includes/functions.php';

$tumKayitlar = $_SESSION['araclar'];

// Filtre değerlerini adres çubuğundan (GET) al. Gelmediyse boş = hepsi.
$secilenDurum = $_GET['durum'] ?? '';
$secilenTur   = $_GET['abone_turu'] ?? '';

// Listede olmayan bir değer gönderilirse filtreyi yok say (güvenlik için)
if (!in_array($secilenDurum, DURUMLAR, true)) {
    $secilenDurum = '';
}
if (!in_array($secilenTur, ABONE_TURLERI, true)) {
    $secilenTur = '';
}

$listelenecekler = kayitlariFiltrele($tumKayitlar, $secilenDurum, $secilenTur);

// Sayaçlar her zaman TÜM kayıtlar üzerinden hesaplanır
$toplamAbone  = count($tumKayitlar);
$aboneIceride = durumaGoreSay($tumKayitlar, 'İçeride');
$ziyaretciIceride = ziyaretciIcerideSayisi();
$icerideSayi  = $aboneIceride + $ziyaretciIceride;   // otoparktaki toplam araç
$bugunGelir   = gelirToplami(date('Y-m-d'));
$disaridaSayi = durumaGoreSay($tumKayitlar, 'Dışarıda');
$bosYer       = bosParkYeri();
$doluYerler   = doluYerler();
$suresiDolan  = suresiDolanSayisi($tumKayitlar);
$dolulukYuzde = (int)round($icerideSayi / TOPLAM_KAPASITE * 100);
$dolulukDurum = dolulukDurumu($dolulukYuzde);
$gostergeRenk = $dolulukYuzde >= 100 ? 'renk-kirmizi' : ($dolulukYuzde >= 85 ? 'renk-turuncu' : '');

// Bugünkü hareketler ve saatlik dağılım (giriş-çıkış geçmişinden)
$bugun    = bugunHareketleri();
$saatlik  = saatlikHareket();
$sonlar   = sonHareketler($tumKayitlar, 6);

// Grafikte gösterilecek saat aralığı: ilk ve son hareketin bir saat öncesi / sonrası
$grafikSaatleri = [];
$enYuksek = 1;
if (count($saatlik) > 0) {
    $ilk = max(0, min(array_keys($saatlik)) - 1);
    $son = min(23, max(array_keys($saatlik)) + 1);
    while ($son - $ilk < 5 && ($ilk > 0 || $son < 23)) {   // en az 6 sütun
        if ($son < 23) { $son++; }
        if ($son - $ilk < 5 && $ilk > 0) { $ilk--; }
    }
    for ($s = $ilk; $s <= $son; $s++) {
        $g = $saatlik[$s]['giris'] ?? 0;
        $c = $saatlik[$s]['cikis'] ?? 0;
        $grafikSaatleri[$s] = ['giris' => $g, 'cikis' => $c];
        $enYuksek = max($enYuksek, $g + $c);
    }
}

// Grafik özeti: toplam hareket ve en yoğun saat
$grafikToplam = 0; $zirveSaat = null; $zirveAdet = 0;
foreach ($grafikSaatleri as $s => $adet) {
    $t = $adet['giris'] + $adet['cikis'];
    $grafikToplam += $t;
    if ($t > $zirveAdet) { $zirveAdet = $t; $zirveSaat = $s; }
}

// Yaklaşan / geçen abonelikler (en acil olan üstte, en fazla 4 kayıt)
$yaklasanlar = array_values(array_filter($tumKayitlar, function ($k) {
    return kalanGun($k['son_tarih']) <= 7;
}));
usort($yaklasanlar, function ($a, $b) {
    return kalanGun($a['son_tarih']) <=> kalanGun($b['son_tarih']);
});
$yaklasanlar = array_slice($yaklasanlar, 0, 4);

// Abone türü dağılımı
$turDagilimi = [];
foreach (ABONE_TURLERI as $t) {
    $turDagilimi[$t] = 0;
}
foreach ($tumKayitlar as $k) {
    if (isset($turDagilimi[$k['abone_turu']])) {
        $turDagilimi[$k['abone_turu']]++;
    }
}
$turRenkleri = ['var(--kat-1)', 'var(--kat-2)', 'var(--kat-3)'];

$bugunYazi = date('d.m.Y');


$sayfaBasligi = 'Genel Bakış';
$aktifSayfa   = 'liste';
require 'includes/header.php';
?>

<div class="sayfa-baslik">
    <div>
        <span class="canli-etiket"><span class="canli-nokta"></span> Canlı durum &middot; <?= $bugunYazi ?></span>
        <h1>Otopark Abone ve Giriş-Çıkış Takibi</h1>
        <p>Abone araçları kaydedin, otoparka giriş ve çıkışlarını işleyin, anlık boş park yerini takip edin.</p>
    </div>
    <div class="sayfa-aksiyon">
        <a href="giris-cikis.php" class="buton buton-ikincil"><?= ikon('bariyer', 16) ?>Araç Giriş-Çıkış</a>
        <a href="ekle.php" class="buton"><?= ikon('arti', 16) ?>Yeni Abone</a>
    </div>
</div>

<!-- DOLULUK: gösterge + park alanı ızgarası -->
<section class="panel-izgara" aria-label="Otopark doluluğu">
    <?php $parkKol = 'kol-12'; require 'includes/park-alani.php'; ?>
</section>

<!-- ÖZET SAYAÇLAR -->
<section class="kpi-izgara" aria-label="Özet sayaçlar">
    <div class="kart kpi">
        <div class="kpi-ust"><span>Toplam Abone</span><span class="kpi-ikon"><?= ikon('kullanicilar', 17) ?></span></div>
        <div class="kpi-sayi"><?= $toplamAbone ?></div>
        <div class="kpi-alt">Kayıtlı abone araç</div>
    </div>
    <div class="kart kpi">
        <div class="kpi-ust"><span>Aktif Oturum</span><span class="kpi-ikon yesil"><?= ikon('arac', 17) ?></span></div>
        <div class="kpi-sayi"><?= $icerideSayi ?><small> / <?= TOPLAM_KAPASITE ?></small></div>
        <div class="kpi-alt"><?= $aboneIceride ?> abone &middot; <?= $ziyaretciIceride ?> ziyaretçi</div>
    </div>
    <div class="kart kpi">
        <div class="kpi-ust"><span>Bugünkü Gelir</span><span class="kpi-ikon yesil"><?= ikon('para', 17) ?></span></div>
        <div class="kpi-sayi"><?= e(paraYaz($bugunGelir)) ?></div>
        <div class="kpi-alt">Bu ay: <?= e(paraYaz(gelirToplami(date('Y-m')))) ?></div>
    </div>
    <div class="kart kpi">
        <div class="kpi-ust"><span>Bugünkü Hareket</span><span class="kpi-ikon"><?= ikon('etkinlik', 17) ?></span></div>
        <div class="kpi-cift">
            <div class="kpi-sayi"><?= $bugun['giris'] ?><span>giriş</span></div>
            <div class="kpi-sayi"><?= $bugun['cikis'] ?><span>çıkış</span></div>
        </div>
        <div class="kpi-alt">Bugünkü giriş ve çıkış sayısı</div>
    </div>
    <div class="kart kpi">
        <div class="kpi-ust"><span>Süresi Dolan</span><span class="kpi-ikon kirmizi"><?= ikon('uyari', 17) ?></span></div>
        <div class="kpi-sayi"><?= $suresiDolan ?></div>
        <div class="kpi-alt">Yenileme bekliyor</div>
    </div>
</section>

<!-- GRAFİK + SON HAREKETLER -->
<section class="panel-izgara">
    <div class="kart kol-4 kart-gosterge">
        <div class="kart-ic">
            <div class="kart-ust">
                <div>
                    <h2>Anlık Doluluk</h2>
                    <p>Toplam kapasiteye göre</p>
                </div>
                <span class="rozet <?= $dolulukDurum['sinif'] ?>"><?= e($dolulukDurum['metin']) ?></span>
            </div>
            <div class="gosterge-ic">
                <div class="gosterge <?= $gostergeRenk ?>" role="img" aria-label="Doluluk yüzde <?= $dolulukYuzde ?>">
                    <svg viewBox="0 0 100 100" aria-hidden="true">
                        <defs>
                            <linearGradient id="gosterge-gecis" gradientUnits="userSpaceOnUse" x1="50" y1="0" x2="50" y2="100">
                                <stop class="gecis-1" offset="0"/><stop class="gecis-2" offset="1"/>
                            </linearGradient>
                            <filter id="gosterge-isik" x="-30%" y="-30%" width="160%" height="160%"><feGaussianBlur stdDeviation="3.2"/></filter>
                        </defs>
                        <circle class="gosterge-centik" cx="50" cy="50" r="48.4" pathLength="120"/>
                        <circle class="gosterge-iz" cx="50" cy="50" r="39" pathLength="100"/>
                        <?php if ($dolulukYuzde > 0): ?>
                        <circle class="gosterge-dolgu gosterge-isik" cx="50" cy="50" r="39" pathLength="100" stroke-dasharray="<?= min(100, $dolulukYuzde) ?> 100"/>
                        <circle class="gosterge-dolgu" cx="50" cy="50" r="39" pathLength="100" stroke-dasharray="<?= min(100, $dolulukYuzde) ?> 100"/>
                        <?php endif; ?>
                    </svg>
                    <div class="gosterge-orta">
                        <span class="gosterge-yuzde"><?= $dolulukYuzde ?><small>%</small></span>
                        <span class="gosterge-etiket">doluluk</span>
                    </div>
                </div>
                <div class="mini-ozet">
                    <div><span><i class="nokta" style="background: var(--soluk)"></i>Kapasite</span><strong><?= TOPLAM_KAPASITE ?></strong></div>
                    <div><span><i class="nokta" style="background: var(--vurgu)"></i>Dolu</span><strong><?= $icerideSayi ?></strong></div>
                    <div><span><i class="nokta" style="background: var(--yesil)"></i>Boş</span><strong><?= $bosYer ?></strong></div>
                </div>
            </div>
        </div>
    </div>

    <div class="kart kol-8 kart-grafik">
        <div class="kart-ic">
            <div class="kart-ust">
                <div>
                    <h2>Bugünkü Saatlik Hareket</h2>
                    <p>Bugünkü giriş ve çıkış kayıtları</p>
                </div>
                <?php if (count($grafikSaatleri) > 0): ?>
                <div class="grafik-aciklama">
                    <span><i class="nokta" style="background: var(--vurgu)"></i>Giriş</span>
                    <span><i class="nokta" style="background: var(--kat-3)"></i>Çıkış</span>
                </div>
                <?php endif; ?>
            </div>
            <?php if (count($grafikSaatleri) === 0): ?>
                <div class="bos-panel">
                    <span class="ikon-daire"><?= ikon('etkinlik', 22) ?></span>
                    Bugün henüz giriş veya çıkış işlemi yapılmadı.
                </div>
            <?php else: ?>
                <div class="grafik-ozet">
                    <strong><?= $grafikToplam ?></strong>
                    <span>hareket<?php if ($zirveSaat !== null): ?> &middot; en yoğun saat <b><?= sprintf('%02d:00', $zirveSaat) ?></b><?php endif; ?></span>
                </div>
                <div class="grafik" role="img" aria-label="Bugünkü saatlik giriş ve çıkış sayıları">
                    <?php $sira = 0; foreach ($grafikSaatleri as $saat => $adet):
                        $toplamSaat = $adet['giris'] + $adet['cikis'];
                        $yukseklik  = $toplamSaat > 0 ? max(6, round($toplamSaat / $enYuksek * 84)) : 0;
                        $ipucu = sprintf('%02d:00 · %d giriş, %d çıkış', $saat, $adet['giris'], $adet['cikis']);
                    ?>
                        <div class="sutun <?= $saat === $zirveSaat ? 'sutun-zirve' : '' ?>" data-ipucu="<?= e($ipucu) ?>" style="--i: <?= $sira++ ?>">
                            <div class="sutun-alan">
                            <?php if ($toplamSaat > 0): ?>
                                <div class="sutun-yigin" style="height: <?= $yukseklik ?>%">
                                    <span class="sutun-deger"><?= $toplamSaat ?></span>
                                    <div class="sutun-bar">
                                        <?php if ($adet['giris'] > 0): ?><span class="sutun-parca parca-giris" style="flex: <?= $adet['giris'] ?> 1 0"></span><?php endif; ?>
                                        <?php if ($adet['cikis'] > 0): ?><span class="sutun-parca parca-cikis" style="flex: <?= $adet['cikis'] ?> 1 0"></span><?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="grafik-etiketler">
                    <?php foreach ($grafikSaatleri as $saat => $adet): ?>
                        <span class="<?= $saat === $zirveSaat ? 'zirve' : '' ?>"><?= sprintf('%02d', $saat) ?>:00</span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- YAKLAŞAN BİTİŞLER + ABONE TÜRÜ DAĞILIMI -->
<section class="panel-izgara">
    <div class="kart kol-4">
        <div class="kart-ic">
            <div class="kart-ust">
                <div>
                    <h2>Son Hareketler</h2>
                    <p>En son işlem yapılan araçlar</p>
                </div>
            </div>
            <?php if (count($sonlar) === 0): ?>
                <div class="bos-panel"><span class="ikon-daire"><?= ikon('kutu', 22) ?></span>Henüz kayıt yok.</div>
            <?php else: ?>
                <ul class="akis">
                <?php foreach ($sonlar as $h):
                    $girdi  = $h['durum'] === 'İçeride';
                    $tarihY = substr($h['son_islem'], 0, 10);
                ?>
                    <li>
                        <span class="akis-ikon <?= $girdi ? 'giris' : 'cikis' ?>"><?= ikon($girdi ? 'giris' : 'cikis', 16) ?></span>
                        <span class="akis-metin">
                            <strong><?= e($h['ad_soyad']) ?></strong>
                            <span class="plaka plaka-kucuk"><span><?= e($h['plaka']) ?></span></span>
                        </span>
                        <span class="akis-zaman">
                            <b><?= $girdi ? 'Giriş' : 'Çıkış' ?> &middot; <?= e(substr($h['son_islem'], 11, 5)) ?></b>
                            <?= $tarihY === $bugunYazi ? 'Bugün' : e($tarihY) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="kart kol-4">
        <div class="kart-ic">
            <div class="kart-ust">
                <div>
                    <h2>Yaklaşan Bitişler</h2>
                    <p>7 gün içinde sona eren veya süresi dolmuş abonelikler</p>
                </div>
            </div>
            <?php if (count($yaklasanlar) === 0): ?>
                <div class="bos-panel"><span class="ikon-daire"><?= ikon('onay', 22) ?></span>Yaklaşan abonelik bitişi yok.</div>
            <?php else: ?>
                <ul class="akis">
                <?php foreach ($yaklasanlar as $y): $et = abonelikEtiketi($y['son_tarih']); ?>
                    <li>
                        <span class="avatar"><?= e(bas_harfler($y['ad_soyad'])) ?></span>
                        <span class="akis-metin">
                            <strong><?= e($y['ad_soyad']) ?></strong>
                            <span class="plaka plaka-kucuk"><span><?= e($y['plaka']) ?></span></span>
                        </span>
                        <span class="etiket <?= $et['sinif'] ?>"><?= e($et['metin']) ?></span>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="kart kol-4">
        <div class="kart-ic">
            <div class="kart-ust">
                <div>
                    <h2>Abone Türü Dağılımı</h2>
                    <p><?= $toplamAbone ?> abone</p>
                </div>
            </div>
            <div class="yigin-cubuk" aria-hidden="true">
                <?php $r = 0; foreach ($turDagilimi as $tur => $adet): ?>
                    <?php if ($adet > 0): ?><span style="flex: <?= $adet ?> 1 0; background: <?= $turRenkleri[$r % 3] ?>"></span><?php endif; ?>
                <?php $r++; endforeach; ?>
            </div>
            <ul class="dagilim">
            <?php $r = 0; foreach ($turDagilimi as $tur => $adet):
                $yuzde = $toplamAbone > 0 ? round($adet / $toplamAbone * 100) : 0; ?>
                <li>
                    <span class="dagilim-ad"><i class="nokta" style="background: <?= $turRenkleri[$r % 3] ?>"></i><?= e($tur) ?></span>
                    <span class="dagilim-sayi"><?= $adet ?><small>%<?= $yuzde ?></small></span>
                    <span class="ilerleme"><span style="width: <?= $yuzde ?>%; background: <?= $turRenkleri[$r % 3] ?>"></span></span>
                </li>
            <?php $r++; endforeach; ?>
            </ul>
        </div>
    </div>
</section>

<!-- ABONE LİSTESİ -->
<section class="kart tablo-kart" id="aboneler" aria-label="Abone listesi">
    <div class="tablo-ust">
        <h2>Abone Listesi <span class="sayi-rozet"><span id="kayit-sayisi"><?= count($listelenecekler) ?></span> kayıt</span></h2>
        <label class="arama">
            <?= ikon('ara', 16) ?>
            <input type="search" id="hizli-ara" placeholder="Plaka veya ad ara…" autocomplete="off" aria-label="Plaka veya ad ara">
            <kbd>/</kbd>
        </label>
    </div>

    <!-- FİLTRELEME (GET yöntemi: seçim adres çubuğunda görünür) -->
    <form class="tablo-ust filtre" method="get" action="index.php#aboneler">
        <label class="filtre-secim">
            Durum
            <select name="durum">
                <option value="">Tümü</option>
                <?php foreach (DURUMLAR as $durum): ?>
                    <option value="<?= e($durum) ?>" <?= $secilenDurum === $durum ? 'selected' : '' ?>><?= e($durum) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="filtre-secim">
            Abone Türü
            <select name="abone_turu">
                <option value="">Tümü</option>
                <?php foreach (ABONE_TURLERI as $tur): ?>
                    <option value="<?= e($tur) ?>" <?= $secilenTur === $tur ? 'selected' : '' ?>><?= e($tur) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="buton buton-kucuk"><?= ikon('filtre', 15) ?>Filtrele</button>
        <?php if ($secilenDurum !== '' || $secilenTur !== ''): ?>
            <a href="index.php#aboneler" class="buton buton-ikincil buton-kucuk">Filtreyi Temizle</a>
        <?php endif; ?>
    </form>

<?php if ($toplamAbone === 0): ?>
    <div class="bos-durum">
        <span class="ikon-daire"><?= ikon('kullanicilar', 26) ?></span>
        <h3>Henüz abone yok</h3>
        <p>İlk abone aracı ekleyerek giriş-çıkış takibine başlayın.</p>
        <a href="ekle.php" class="buton"><?= ikon('arti', 16) ?>Yeni Abone</a>
    </div>
<?php elseif (count($listelenecekler) === 0): ?>
    <div class="bos-durum">
        <span class="ikon-daire"><?= ikon('kutu', 26) ?></span>
        <h3>Kayıt bulunamadı</h3>
        <p>Bu filtreye uyan abone yok. Filtreyi temizleyerek tüm kayıtları görebilirsiniz.</p>
        <a href="index.php#aboneler" class="buton buton-ikincil">Filtreyi Temizle</a>
    </div>
<?php else: ?>
    <div class="tablo-kaydir">
        <table id="abone-tablosu">
            <thead>
                <tr>
                    <th class="siralanabilir"><button type="button" class="sirala-buton">Plaka <?= ikon('sirala', 14) ?></button></th>
                    <th class="siralanabilir"><button type="button" class="sirala-buton">Ad Soyad <?= ikon('sirala', 14) ?></button></th>
                    <th class="siralanabilir"><button type="button" class="sirala-buton">Abone Türü <?= ikon('sirala', 14) ?></button></th>
                    <th class="siralanabilir" data-tip="sayi"><button type="button" class="sirala-buton">Son Tarih <?= ikon('sirala', 14) ?></button></th>
                    <th class="siralanabilir" data-tip="sayi"><button type="button" class="sirala-buton">Abonelik <?= ikon('sirala', 14) ?></button></th>
                    <th class="siralanabilir"><button type="button" class="sirala-buton">Durum <?= ikon('sirala', 14) ?></button></th>
                    <th class="siralanabilir" data-tip="sayi"><button type="button" class="sirala-buton">Son İşlem <?= ikon('sirala', 14) ?></button></th>
                    <th class="islem-hucre">İşlemler</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($listelenecekler as $kayit):
                $etiket = abonelikEtiketi($kayit['son_tarih']);
            ?>
                <tr>
                    <td data-sort="<?= e($kayit['plaka']) ?>"><span class="plaka"><span><?= e($kayit['plaka']) ?></span></span></td>
                    <td data-sort="<?= e($kayit['ad_soyad']) ?>"><a class="kisi" href="abone.php?id=<?= (int)$kayit['id'] ?>" title="Abone kartını aç"><span class="avatar"><?= e(bas_harfler($kayit['ad_soyad'])) ?></span><?= e($kayit['ad_soyad']) ?><?php if (!empty($kayit['pasif'])): ?> <span class="etiket etiket-kirmizi">Pasif</span><?php endif; ?></a></td>
                    <td><?= e($kayit['abone_turu']) ?></td>
                    <td data-sort="<?= (int)strtotime($kayit['son_tarih']) ?>"><?= e(date('d.m.Y', strtotime($kayit['son_tarih']))) ?></td>
                    <td data-sort="<?= kalanGun($kayit['son_tarih']) ?>"><span class="etiket <?= $etiket['sinif'] ?>"><?= e($etiket['metin']) ?></span></td>
                    <td data-sort="<?= e($kayit['durum']) ?>">
                        <?php if ($kayit['durum'] === 'İçeride'): ?>
                            <span class="durum durum-iceride">İçeride</span>
                        <?php else: ?>
                            <span class="durum durum-disarida">Dışarıda</span>
                        <?php endif; ?>
                    </td>
                    <td class="soluk" data-sort="<?= islemZamani($kayit['son_islem']) ?>"><?= e($kayit['son_islem']) ?></td>
                    <td class="islem-hucre">
                        <div class="islemler">
                            <!-- Giriş / Çıkış: durumu tersine çevirir (POST) -->
                            <form action="durum.php" method="post">
                                <input type="hidden" name="id" value="<?= (int)$kayit['id'] ?>">
                                <?php if ($kayit['durum'] === 'İçeride'): ?>
                                    <button type="submit" class="kucuk-buton cikis"><?= ikon('cikis', 14) ?>Çıkış Yap</button>
                                <?php else: ?>
                                    <button type="submit" class="kucuk-buton giris-btn"><?= ikon('giris', 14) ?>Giriş Yap</button>
                                <?php endif; ?>
                            </form>
                            <?php if (yoneticiMi()): ?>
                            <!-- Silme (POST) -->
                            <form action="sil.php" method="post" data-onay="<?= e($kayit['plaka']) ?> plakalı abone silinsin mi? Bu işlem geri alınamaz." data-onay-baslik="Aboneyi sil" data-onay-buton="Evet, sil">
                                <input type="hidden" name="id" value="<?= (int)$kayit['id'] ?>">
                                <button type="submit" class="kucuk-buton sil" aria-label="<?= e($kayit['plaka']) ?> kaydını sil" title="Sil"><?= ikon('cop', 15) ?></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="bos-durum" id="arama-bos" hidden>
        <span class="ikon-daire"><?= ikon('ara', 24) ?></span>
        <h3>Eşleşen kayıt yok</h3>
        <p>“<span id="arama-bos-sorgu"></span>” için sonuç bulunamadı. Plakayı veya adı farklı yazmayı deneyin.</p>
        <button type="button" class="buton buton-ikincil" id="arama-temizle">Aramayı temizle</button>
    </div>

    <div class="tablo-alt" id="tablo-alt">
        <span id="sayfa-bilgi"><?= count($listelenecekler) ?> kayıt</span>
        <div class="sayfalama" id="sayfalama" hidden></div>
    </div>
<?php endif; ?>
</section>

<?php require 'includes/footer.php'; ?>
