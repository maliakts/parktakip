<?php
/*
 * giris-cikis.php — Araç Giriş-Çıkış Yönetimi
 * Plakaya göre giriş (abone / ziyaretçi), içerideki araçlar, ücret hesaplama,
 * ödeme alarak çıkış ve giriş-çıkış geçmişi bu sayfadadır.
 */
require_once 'includes/functions.php';

// Plaka araması (GET): hem içerideki araçları hem geçmişi süzer
$aranan = isset($_GET['plaka']) && is_string($_GET['plaka']) ? trim($_GET['plaka']) : '';
$arananTemiz = strtoupper(str_replace(' ', '', $aranan));
$uyuyorMu = function ($h) use ($arananTemiz) {
    return $arananTemiz === '' || strpos(str_replace(' ', '', $h['plaka']), $arananTemiz) !== false;
};

$iceridekiler = [];
$gecmis       = [];
foreach ($_SESSION['hareketler'] as $h) {
    if (!$uyuyorMu($h)) {
        continue;
    }
    if ($h['cikis'] === null) {
        $iceridekiler[] = $h;
    } else {
        $gecmis[] = $h;
    }
}
usort($iceridekiler, function ($a, $b) { return $b['giris'] <=> $a['giris']; });
usort($gecmis, function ($a, $b) { return $b['cikis'] <=> $a['cikis']; });

$toplamIceride    = iceridekiToplam();
$ziyaretciIceride = ziyaretciIcerideSayisi();
$bosYer           = bosParkYeri();

// Bariyer simülasyonu: az önce yapılan başarılı giriş/çıkış (hareket.php yazar)
$bariyer = $_SESSION['bariyer'] ?? null;
unset($_SESSION['bariyer']);
$bugunGelir       = gelirToplami(date('Y-m-d'));
$ayGelir          = gelirToplami(date('Y-m'));

$zamanYaz = function ($zaman) {
    if ($zaman === null) {
        return '—';
    }
    return date('Y-m-d', $zaman) === date('Y-m-d') ? 'Bugün ' . date('H:i', $zaman) : date('d.m.Y H:i', $zaman);
};

$sayfaBasligi = 'Araç Giriş-Çıkış';
$aktifSayfa   = 'hareket';
require 'includes/header.php';
?>

<div class="sayfa-baslik">
    <div>
        <h1>Araç Giriş-Çıkış</h1>
        <p>Plakayı yazın: abone araçlar ücretsiz girer, kayıtlı olmayan araçlar ziyaretçi olarak alınır ve çıkışta ücret hesaplanır.</p>
    </div>
    <div class="sayfa-aksiyon">
        <a href="index.php" class="buton buton-ikincil"><?= ikon('geri', 16) ?>Genel Bakış</a>
    </div>
</div>

<?php if ($bariyer !== null): ?>
<div class="bariyer-kart" role="status">
    <svg class="bariyer-cizim" viewBox="0 0 120 60" aria-hidden="true">
        <rect x="8" y="22" width="12" height="34" rx="3" class="bariyer-direk"/>
        <g class="bariyer-kol"><rect x="14" y="24" width="96" height="7" rx="3.5" class="bariyer-cubuk"/><rect x="34" y="24" width="12" height="7" class="bariyer-serit"/><rect x="62" y="24" width="12" height="7" class="bariyer-serit"/><rect x="90" y="24" width="12" height="7" class="bariyer-serit"/></g>
        <circle cx="14" cy="27.5" r="5" class="bariyer-direk"/>
    </svg>
    <div><strong>Bariyer açıldı</strong><span><?= $bariyer === 'giris' ? 'Araç girişi onaylandı' : 'Araç çıkışı onaylandı' ?></span></div>
</div>
<?php endif; ?>

<section class="kpi-izgara" aria-label="Giriş-çıkış özeti">
    <div class="kart kpi">
        <div class="kpi-ust"><span>Otoparktaki Araç</span><span class="kpi-ikon"><?= ikon('arac', 17) ?></span></div>
        <div class="kpi-sayi"><?= $toplamIceride ?><small> / <?= TOPLAM_KAPASITE ?></small></div>
        <div class="kpi-alt"><?= $bosYer ?> boş park yeri</div>
    </div>
    <div class="kart kpi">
        <div class="kpi-ust"><span>Ziyaretçi Araç</span><span class="kpi-ikon turuncu"><?= ikon('kullanici', 17) ?></span></div>
        <div class="kpi-sayi"><?= $ziyaretciIceride ?></div>
        <div class="kpi-alt">Şu an içeride, ücretli</div>
    </div>
    <div class="kart kpi">
        <div class="kpi-ust"><span>Bugünkü Gelir</span><span class="kpi-ikon yesil"><?= ikon('para', 17) ?></span></div>
        <div class="kpi-sayi"><?= e(paraYaz($bugunGelir)) ?></div>
        <div class="kpi-alt">Bu ay: <?= e(paraYaz($ayGelir)) ?></div>
    </div>
    <div class="kart kpi">
        <div class="kpi-ust"><span>Ziyaretçi Tarifesi</span><span class="kpi-ikon gri"><?= ikon('saat', 17) ?></span></div>
        <div class="kpi-sayi"><?= e(number_format(ayar('saatlik'), 0, ',', '.')) ?> ₺<small> / saat</small></div>
        <div class="kpi-alt">Günlük tavan: <?= e(paraYaz(ayar('gunluk'))) ?></div>
    </div>
</section>

<section class="panel-izgara">
    <div class="kart kol-5">
        <div class="kart-ic">
            <div class="kart-ust">
                <div>
                    <h2>Araç Girişi</h2>
                    <p>Giriş saati otomatik kaydedilir</p>
                </div>
                <span class="rozet <?= $bosYer > 0 ? 'rozet-yesil' : 'rozet-kirmizi' ?>"><?= $bosYer > 0 ? $bosYer . ' boş yer' : 'Otopark dolu' ?></span>
            </div>
            <form action="hareket.php" method="post" class="giris-form">
                <input type="hidden" name="islem" value="giris">
                <div class="alan">
                    <label for="giris-plaka">Plaka</label>
                    <input type="text" id="giris-plaka" name="plaka" maxlength="12" placeholder="60 ABC 123" class="plaka-girdi" autocomplete="off" required autofocus>
                    <span class="ipucu">Boşluksuz yazabilirsiniz. Aynı araç içerideyken ikinci kez giriş yapamaz.</span>
                </div>
                <button type="submit" class="buton"><?= ikon('giris', 16) ?>Giriş yap</button>
            </form>
        </div>
    </div>

    <div class="kart kol-7">
        <div class="kart-ic">
            <div class="kart-ust">
                <div>
                    <h2>Kurallar</h2>
                    <p>Giriş ve çıkışta uygulanan kontroller</p>
                </div>
            </div>
            <ul class="kural-listesi">
                <li><?= ikon('onay', 16) ?><span>Plaka abone listesindeyse araç <strong>abone</strong> olarak girer ve ücret ödemez.</span></li>
                <li><?= ikon('onay', 16) ?><span>Aboneliği dolmuş araç ve otopark doluyken hiçbir araç içeri alınmaz.</span></li>
                <li><?= ikon('onay', 16) ?><span>Kayıtlı olmayan plaka <strong>ziyaretçi</strong> sayılır: başlanan her saat <?= e(paraYaz(ayar('saatlik'))) ?>, 24 saatte en fazla <?= e(paraYaz(ayar('gunluk'))) ?>.</span></li>
                <li><?= ikon('onay', 16) ?><span>Ziyaretçi araçta ödeme yöntemi seçilmeden çıkış onaylanmaz; her ödemeye işlem numarası verilir.</span></li>
            </ul>
        </div>
    </div>
</section>

<section class="panel-izgara" aria-label="Park alanı">
    <?php $parkKol = 'kol-12'; require 'includes/park-alani.php'; ?>
</section>

<!-- İÇERİDEKİ ARAÇLAR -->
<section class="kart tablo-kart" id="iceridekiler" aria-label="İçerideki araçlar" style="margin-bottom: 16px">
    <div class="tablo-ust">
        <h2>İçerideki Araçlar <span class="sayi-rozet"><?= count($iceridekiler) ?> araç</span></h2>
        <form class="filtre" method="get" action="giris-cikis.php">
            <label class="arama">
                <?= ikon('ara', 16) ?>
                <input type="search" name="plaka" value="<?= e($aranan) ?>" placeholder="Plaka ara…" autocomplete="off" aria-label="Plaka ara">
            </label>
            <button type="submit" class="buton buton-kucuk"><?= ikon('ara', 15) ?>Ara</button>
            <?php if ($aranan !== ''): ?>
                <a href="giris-cikis.php" class="buton buton-ikincil buton-kucuk">Temizle</a>
            <?php endif; ?>
        </form>
    </div>
<?php if (count($iceridekiler) === 0): ?>
    <div class="bos-durum">
        <span class="ikon-daire"><?= ikon('arac', 26) ?></span>
        <h3><?= $aranan !== '' ? 'Eşleşen araç yok' : 'Otopark boş' ?></h3>
        <p><?= $aranan !== '' ? 'Bu plakayla içeride araç bulunamadı.' : 'Şu an içeride araç yok.' ?></p>
    </div>
<?php else: ?>
    <div class="tablo-kaydir">
        <table>
            <thead>
                <tr><th>Plaka</th><th>Park yeri</th><th>Tür</th><th>Araç sahibi</th><th>Giriş</th><th>Süre</th><th>Anlık ücret</th><th class="islem-hucre">Çıkış</th></tr>
            </thead>
            <tbody>
            <?php foreach ($iceridekiler as $h):
                $dakika    = sureDakika($h['giris']);
                $ziyaretci = $h['tip'] === 'Ziyaretçi';
            ?>
                <tr>
                    <td><span class="plaka"><span><?= e($h['plaka']) ?></span></span></td>
                    <td><strong><?= e(yerAdi($h['yer'])) ?></strong></td>
                    <td><span class="etiket <?= $ziyaretci ? 'etiket-turuncu' : 'etiket-yesil' ?>"><?= e($h['tip']) ?></span></td>
                    <td><?= e($h['ad']) ?></td>
                    <td class="soluk"><?= e($zamanYaz($h['giris'])) ?></td>
                    <td><?= e(sureYazi($dakika)) ?></td>
                    <td><?= $ziyaretci ? '<strong>' . e(paraYaz(ucretHesapla($dakika))) . '</strong>' : '<span class="soluk">Abone</span>' ?></td>
                    <td class="islem-hucre">
                        <?php if ($ziyaretci): ?>
                            <div class="islemler">
                                <button type="button" class="kucuk-buton cikis cikis-ac" data-plaka="<?= e($h['plaka']) ?>" data-yer="<?= e(yerAdi($h['yer'])) ?>" data-sure="<?= e(sureYazi($dakika)) ?>" data-ucret="<?= e(paraYaz(ucretHesapla($dakika))) ?>"><?= ikon('cikis', 14) ?>Çıkış yap</button>
                            </div>
                        <?php else: ?>
                            <form action="hareket.php" method="post" class="islemler">
                                <input type="hidden" name="islem" value="cikis">
                                <input type="hidden" name="plaka" value="<?= e($h['plaka']) ?>">
                                <button type="submit" class="kucuk-buton cikis"><?= ikon('cikis', 14) ?>Çıkış yap</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</section>

<!-- GEÇMİŞ -->
<section class="kart tablo-kart" aria-label="Giriş-çıkış geçmişi">
    <div class="tablo-ust">
        <h2>Giriş-Çıkış Geçmişi <span class="sayi-rozet"><?= count($gecmis) ?> kayıt</span></h2>
    </div>
<?php if (count($gecmis) === 0): ?>
    <div class="bos-durum">
        <span class="ikon-daire"><?= ikon('saat', 26) ?></span>
        <h3>Geçmiş kayıt yok</h3>
        <p>Çıkışı yapılan araçlar burada listelenir.</p>
    </div>
<?php else: ?>
    <div class="tablo-kaydir">
        <table>
            <thead>
                <tr><th>Plaka</th><th>Park yeri</th><th>Tür</th><th>Giriş</th><th>Çıkış</th><th>Süre</th><th>Ücret</th><th>Ödeme</th><th>İşlem no</th></tr>
            </thead>
            <tbody>
            <?php foreach ($gecmis as $h): $ziyaretci = $h['tip'] === 'Ziyaretçi'; ?>
                <tr>
                    <td><span class="plaka"><span><?= e($h['plaka']) ?></span></span></td>
                    <td class="soluk"><?= e(yerAdi($h['yer'])) ?></td>
                    <td><span class="etiket <?= $ziyaretci ? 'etiket-turuncu' : 'etiket-yesil' ?>"><?= e($h['tip']) ?></span></td>
                    <td class="soluk"><?= e($zamanYaz($h['giris'])) ?></td>
                    <td class="soluk"><?= e($zamanYaz($h['cikis'])) ?></td>
                    <td><?= e(sureYazi(sureDakika($h['giris'], $h['cikis']))) ?></td>
                    <td><?= $h['ucret'] > 0 ? '<strong>' . e(paraYaz($h['ucret'])) . '</strong>' : '<span class="soluk">—</span>' ?></td>
                    <td><?= e($h['odeme'] ?? '—') ?></td>
                    <td class="soluk"><?= e($h['islem_no'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</section>

<!-- Ziyaretçi çıkışı: ücret özeti ve ödeme yöntemi (tek adımda seçilir) -->
<dialog class="odeme-pencere" id="odeme-pencere" aria-labelledby="odeme-baslik">
    <form action="hareket.php" method="post" id="odeme-form">
        <input type="hidden" name="islem" value="cikis">
        <input type="hidden" name="plaka" id="odeme-plaka" value="">
        <input type="hidden" name="odeme" id="odeme-deger" value="">
        <header>
            <div><h3 id="odeme-baslik">Çıkış ve ödeme</h3><p class="soluk" id="odeme-alt"></p></div>
            <button type="button" class="ikon-buton odeme-kapat" aria-label="Kapat"><?= ikon('kapat', 18) ?></button>
        </header>
        <div class="odeme-tutar"><span class="soluk">Tahsil edilecek tutar</span><strong id="odeme-tutar">—</strong></div>
        <fieldset class="odeme-secenekler">
            <legend>Ödeme yöntemi</legend>
            <?php foreach (ODEME_YONTEMLERI as $y): ?>
                <label class="odeme-sec"><input type="radio" name="odeme-sec" value="<?= e($y) ?>"><span><?php if ($y === 'Nakit'): ?><?= ikon('para', 18) ?><?php elseif ($y === 'Kart'): ?><svg class="ikon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2.5" y="5" width="19" height="14" rx="3"/><path d="M2.5 10h19M6.5 15h4"/></svg><?php else: ?><svg class="ikon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="6" cy="12" r="1.2"/><circle cx="12" cy="12" r="1.2"/><circle cx="18" cy="12" r="1.2"/></svg><?php endif; ?><b><?= e($y) ?></b></span></label>
            <?php endforeach; ?>
            <?php if (yoneticiMi()): ?>
                <label class="odeme-sec odeme-ucretsiz"><input type="radio" name="odeme-sec" value="Ücretsiz"><span><?= ikon('yasak', 18) ?><b>Ücretsiz</b><small>yönetici</small></span></label>
            <?php endif; ?>
        </fieldset>
        <div class="odeme-eylem">
            <button type="button" class="buton buton-ikincil odeme-kapat">Vazgeç</button>
            <button type="submit" class="buton" id="odeme-tamam" disabled><?= ikon('cikis', 16) ?>Ödemeyi al ve çıkışı yap</button>
        </div>
    </form>
</dialog>
<script>
// Ziyaretçi çıkış penceresi: yöntem seçilene kadar onay düğmesi kapalıdır (ödemesiz çıkış olmaz).
(function () {
    var pencere = document.getElementById('odeme-pencere'), form = document.getElementById('odeme-form');
    if (!pencere || !pencere.showModal) { return; }
    var tamam = document.getElementById('odeme-tamam'), deger = document.getElementById('odeme-deger');
    document.querySelectorAll('.cikis-ac').forEach(function (b) {
        b.addEventListener('click', function () {
            document.getElementById('odeme-plaka').value = b.getAttribute('data-plaka');
            document.getElementById('odeme-alt').textContent = b.getAttribute('data-plaka') + ' · ' + b.getAttribute('data-yer') + ' · ' + b.getAttribute('data-sure');
            document.getElementById('odeme-tutar').textContent = b.getAttribute('data-ucret');
            deger.value = ''; tamam.disabled = true;
            form.querySelectorAll('input[name="odeme-sec"]').forEach(function (r) { r.checked = false; });
            pencere.showModal();
        });
    });
    form.querySelectorAll('input[name="odeme-sec"]').forEach(function (r) {
        r.addEventListener('change', function () { deger.value = r.value; tamam.disabled = false; tamam.focus(); });
    });
    pencere.querySelectorAll('.odeme-kapat').forEach(function (b) { b.addEventListener('click', function () { pencere.close(); }); });
    pencere.addEventListener('click', function (e) { if (e.target === pencere) { pencere.close(); } });   // dışarı tıklayınca kapanır
    form.addEventListener('submit', function (e) { if (deger.value === '') { e.preventDefault(); } });
})();
</script>

<?php require 'includes/footer.php'; ?>
