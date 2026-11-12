<?php
/*
 * rezervasyon.php — Müşteri rezervasyon sayfası (giriş gerektirmez)
 * Müşteri tarih-saat seçer, uygun park yerlerini görür, bir yer seçip rezervasyonunu tamamlar.
 * Bu sayfada oturum açılmaz; yönetim panelinin verilerine ve işlemlerine erişilemez.
 * Park yerleri veri/park-durum.php dosyasından (yönetim panelinin yazdığı gerçek durum) okunur.
 */
define('MUSTERI_SAYFASI', true);
require_once 'includes/functions.php';
require_once 'includes/arac-svg.php';   // 3 boyutlu araç çizimleri (yönetim paneliyle aynı)

$park = rzParkDurum();

// ---- Rezervasyon görüntüleme (QR kod bu adrese götürür) ----
$kod    = isset($_GET['kod']) && is_string($_GET['kod']) && preg_match('/^[a-f0-9]{24}$/', $_GET['kod']) ? $_GET['kod'] : '';
$kayit  = $kod !== '' ? rzBul('kod', $kod) : null;
$yeniMi = isset($_GET['yeni']);

// ---- Form değerleri ----
$al = function ($ad, $varsayilan = '') {
    $kaynak = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    return isset($kaynak[$ad]) && is_string($kaynak[$ad]) ? trim($kaynak[$ad]) : $varsayilan;
};
$ilk = (int)(ceil((time() + 600) / 1800) * 1800);   // en yakın yarım saat
$v = [
    'giris_tarih' => $al('giris_tarih', date('Y-m-d', $ilk)), 'giris_saat' => $al('giris_saat', date('H:i', $ilk)),
    'cikis_tarih' => $al('cikis_tarih', date('Y-m-d', $ilk + 7200)), 'cikis_saat' => $al('cikis_saat', date('H:i', $ilk + 7200)),
    'yer' => $al('yer'), 'ad' => $al('ad'), 'telefon' => $al('telefon'), 'plaka' => plakaDuzenle($al('plaka')),
];

$hatalar = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $kayit === null) {
    $sonuc = rzOlustur($v);
    if (isset($sonuc['kayit'])) {
        // Yenilemede form tekrar gönderilmesin diye rezervasyon sayfasına yönlendir
        header('Location: rezervasyon.php?kod=' . $sonuc['kayit']['kod'] . '&yeni=1');
        exit;
    }
    $hatalar = $sonuc['hatalar'];
}

// ---- Seçilen aralıkta yerlerin durumu ----
$bas = rzZaman($v['giris_tarih'], $v['giris_saat']);
$bit = rzZaman($v['cikis_tarih'], $v['cikis_saat']);
$aralikGecerli = $bas !== null && $bit !== null && $bit > $bas && $bas >= time() - 300;
$durumlar = [];
$bosSayi  = 0;
if ($park !== null) {
    $durumlar = rzYerDurumlari($park, rzVeri()['liste'], $aralikGecerli ? $bas : time(), $aralikGecerli ? $bit : time() + 7200);
    $bosSayi  = count(array_filter($durumlar, function ($d) { return $d === 'bos'; }));
}
$seciliYer = isset($durumlar[(int)$v['yer']]) && $durumlar[(int)$v['yer']] === 'bos' ? (int)$v['yer'] : 0;
$sutun     = (int)ceil(max(1, count($durumlar)) / 2);

$tamAdres = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . strtok($_SERVER['REQUEST_URI'] ?? '/rezervasyon.php', '?');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Park Yeri Rezervasyonu | <?= UYGULAMA_ADI ?></title>
    <link rel="stylesheet" href="assets/rezervasyon.css?v=<?= filemtime(__DIR__ . '/assets/rezervasyon.css') ?>">
</head>
<body>
<header class="rz-ust">
    <a class="rz-marka" href="rezervasyon.php"><span class="rz-logo">P</span><?= UYGULAMA_ADI ?></a>
    <span class="rz-ust-not">Online park yeri rezervasyonu</span>
</header>

<main class="rz-govde">
<?php if ($kod !== ''): /* ================= REZERVASYON EKRANI ================= */ ?>
    <?php if ($kayit === null): ?>
        <section class="rz-kart rz-orta">
            <h1>Rezervasyon bulunamadı</h1>
            <p class="rz-soluk">Bağlantı hatalı olabilir. Yeni bir rezervasyon oluşturabilirsiniz.</p>
            <a class="rz-buton" href="rezervasyon.php">Yeni rezervasyon</a>
        </section>
    <?php else:
        $durumRenk = ['Bekliyor' => 'sari', 'Onaylandı' => 'yesil', 'Aktif' => 'yesil', 'Tamamlandı' => 'gri', 'İptal' => 'kirmizi', 'Süresi doldu' => 'gri'][$kayit['durum']] ?? 'gri';
    ?>
        <section class="rz-kart rz-basari">
            <?php if ($yeniMi): ?>
                <div class="rz-onay-isaret" aria-hidden="true"><svg viewBox="0 0 52 52"><circle cx="26" cy="26" r="24"/><path d="M15 27l7 7 15-16"/></svg></div>
                <h1>Rezervasyonunuz alındı</h1>
                <p class="rz-soluk">Otopark yönetimi onayladığında durum "Onaylandı" olur. Bu sayfayı ya da QR kodu saklayın.</p>
            <?php else: ?>
                <h1>Rezervasyon bilgileri</h1>
            <?php endif; ?>

            <div class="rz-bilet">
                <div class="rz-bilet-sol">
                    <span class="rz-etiket rz-<?= $durumRenk ?>"><?= e($kayit['durum']) ?></span>
                    <div class="rz-no"><?= e($kayit['no']) ?></div>
                    <dl>
                        <div><dt>Ad soyad</dt><dd><?= e($kayit['ad']) ?></dd></div>
                        <div><dt>Araç plakası</dt><dd><?= e($kayit['plaka']) ?></dd></div>
                        <div><dt>Park yeri</dt><dd class="rz-buyuk"><?= e(yerAdi($kayit['yer'])) ?></dd></div>
                        <div><dt>Giriş</dt><dd><?= e(date('d.m.Y H:i', $kayit['giris'])) ?></dd></div>
                        <div><dt>Çıkış</dt><dd><?= e(date('d.m.Y H:i', $kayit['cikis'])) ?></dd></div>
                    </dl>
                </div>
                <div class="rz-bilet-sag">
                    <div class="rz-qr" id="rz-qr" data-metin="<?= e($tamAdres . '?kod=' . $kayit['kod']) ?>"></div>
                    <span class="rz-soluk">Girişte bu kodu gösterin</span>
                </div>
            </div>
            <div class="rz-eylem">
                <button type="button" class="rz-buton rz-ikincil" onclick="window.print()">Yazdır</button>
                <a class="rz-buton" href="rezervasyon.php">Yeni rezervasyon</a>
            </div>
        </section>
        <script src="assets/qrcode.js"></script>
        <script>
        (function () {
            var kutu = document.getElementById('rz-qr');
            try {
                var qr = qrcode(0, 'M');
                qr.addData(kutu.getAttribute('data-metin'));
                qr.make();
                kutu.innerHTML = qr.createSvgTag({ cellSize: 5, margin: 2, scalable: true });
            } catch (hata) { kutu.textContent = 'QR kod oluşturulamadı'; }
        })();
        </script>
    <?php endif; ?>

<?php elseif ($park === null): /* ================= SİSTEM KAPALI ================= */ ?>
    <section class="rz-kart rz-orta">
        <h1>Rezervasyon şu an alınamıyor</h1>
        <p class="rz-soluk">Otopark sistemi çevrimdışı görünüyor. Lütfen daha sonra tekrar deneyiniz.</p>
    </section>

<?php else: /* ================= REZERVASYON ADIMLARI ================= */ ?>
    <section class="rz-kahraman">
        <h1>Park yerinizi şimdiden ayırın</h1>
        <p>Saatinizi seçin, haritadan boş bir yere dokunun, bilgilerinizi girin. Yeriniz sizi beklesin.</p>
    </section>

    <?php if (count($hatalar) > 0): ?>
        <div class="rz-uyari" role="alert">
            <strong>Rezervasyon tamamlanamadı</strong>
            <ul><?php foreach ($hatalar as $h): ?><li><?= e($h) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <!-- 1. ADIM: Zaman -->
    <?php
    $gunAdlari = ['Paz', 'Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt'];
    $hizliGunler = [];
    for ($i = 0; $i < 4; $i++) {
        $t = strtotime("+$i day");
        $hizliGunler[] = ['d' => date('Y-m-d', $t), 'ust' => $i === 0 ? 'Bugün' : ($i === 1 ? 'Yarın' : $gunAdlari[(int)date('w', $t)]), 'alt' => date('d.m', $t)];
    }
    $zamanAlanlari = ['giris' => ['Giriş', 'rz-zg'], 'cikis' => ['Çıkış', 'rz-zc']];
    ?>
    <form class="rz-kart" method="get" action="rezervasyon.php" id="rz-zaman">
        <div class="rz-adim"><span>1</span><div><h2>Ne zaman park edeceksiniz?</h2><p class="rz-soluk">Gün ve saati seçin; uygun yerler harita üzerinde otomatik güncellenir.</p></div></div>
        <div class="rz-zaman-kutular">
        <?php foreach ($zamanAlanlari as $on => [$baslik, $sinif]):
     ?>
            <section class="rz-zaman-kutu <?= $sinif ?>" data-on="<?= $on ?>">
                <header><i class="rz-zaman-ikon"><?= $on === 'giris' ? '→' : '←' ?></i><strong><?= $baslik ?></strong><output class="rz-zaman-ozet" aria-live="polite"></output></header>
                <div class="rz-gunler" role="group" aria-label="<?= $baslik ?> günü">
                    <?php foreach ($hizliGunler as $g): ?><button type="button" class="rz-gun" data-tarih="<?= $g['d'] ?>"><small><?= $g['ust'] ?></small><b><?= $g['alt'] ?></b></button><?php endforeach; ?>
                    <label class="rz-gun rz-gun-takvim" title="Takvimden seç"><small>Takvim</small><input type="date" name="<?= $on ?>_tarih" value="<?= e($v[$on . '_tarih']) ?>" min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+' . RZ_ILERI_GUN . ' days')) ?>" required></label>
                </div>
                <div class="rz-saat" role="group" aria-label="<?= $baslik ?> saati">
                    <button type="button" class="rz-adim-buton" data-yon="-30" aria-label="30 dakika geri">−</button>
                    <input type="text" class="rz-saat-girdi" inputmode="numeric" maxlength="5" autocomplete="off" value="<?= e($v[$on . '_saat']) ?>" aria-label="<?= $baslik ?> saati (24 saat, örn. 14:30)">
                    <button type="button" class="rz-adim-buton" data-yon="30" aria-label="30 dakika ileri">+</button>
                    <input type="hidden" name="<?= $on ?>_saat" value="<?= e($v[$on . '_saat']) ?>">
                </div>
            </section>
        <?php endforeach; ?>
        </div>
        <div class="rz-sureler">
            <span class="rz-soluk">Süre</span>
            <?php foreach ([['1 saat', 60], ['2 saat', 120], ['4 saat', 240], ['1 gün', 1440]] as [$ad, $dkSure]): ?>
                <button type="button" class="rz-sure" data-dk="<?= $dkSure ?>"><?= $ad ?></button>
            <?php endforeach; ?>
            <strong class="rz-sure-ozet" id="rz-sure-ozet"></strong>
        </div>
        <noscript><button type="submit" class="rz-buton rz-ikincil" style="margin-top:12px">Uygun yerleri göster</button></noscript>
        <?php if (!$aralikGecerli): ?>
            <p class="rz-hata-satir">Lütfen ileri bir giriş zamanı ve ondan sonraki bir çıkış zamanı seçin.</p>
        <?php endif; ?>
    </form>
    <script>
    // Zaman seçici: gün düğmeleri, 24 saat biçiminde saat/dakika, süre kısayolları. Her değişiklikte harita yenilenir.
    (function () {
        var form = document.getElementById('rz-zaman'), kutu = {}, zamanlayici;
        function iki(n) { return (n < 10 ? '0' : '') + n; }
        function oku(on) {
            var k = kutu[on], t = k.tarih.value.split('-'), s = k.saat.value.split(':');
            if (t.length !== 3) { return null; }
            return new Date(+t[0], +t[1] - 1, +t[2], +s[0] || 0, +s[1] || 0, 0, 0);
        }
        function yaz(on, d) {
            var k = kutu[on];
            k.tarih.value = d.getFullYear() + '-' + iki(d.getMonth() + 1) + '-' + iki(d.getDate());
            k.saat.value = iki(d.getHours()) + ':' + iki(d.getMinutes() - d.getMinutes() % 15);
            k.girdi.value = k.saat.value;
            boya(on);
        }
        var gunAd = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];
        function boya(on) {
            var k = kutu[on], d = oku(on);
            k.kok.querySelectorAll('.rz-gun[data-tarih]').forEach(function (b) { b.classList.toggle('rz-aktif', b.getAttribute('data-tarih') === k.tarih.value); });
            var ozel = !k.kok.querySelector('.rz-gun.rz-aktif');
            k.kok.querySelector('.rz-gun-takvim').classList.toggle('rz-aktif', ozel);
            k.ozet.textContent = d ? gunAd[d.getDay()] + ' ' + iki(d.getDate()) + '.' + iki(d.getMonth() + 1) + ' · ' + k.saat.value : '';
            sure();
        }
        function sure() {
            var g = oku('giris'), c = oku('cikis'), ozet = document.getElementById('rz-sure-ozet');
            var dk = g && c ? Math.round((c - g) / 60000) : 0;
            document.querySelectorAll('.rz-sure').forEach(function (b) { b.classList.toggle('rz-aktif', +b.getAttribute('data-dk') === dk); });
            if (dk <= 0) { ozet.textContent = ''; return; }
            var gun = Math.floor(dk / 1440), sa = Math.floor(dk % 1440 / 60), m = dk % 60;
            ozet.textContent = [gun ? gun + ' gün' : '', sa ? sa + ' saat' : '', m ? m + ' dk' : ''].filter(Boolean).join(' ');
        }
        function degisti(on, sureyiKoru) {
            var g = oku('giris'), c = oku('cikis'), eski = g && c ? c - g : 7200000;
            if (on === 'giris' && sureyiKoru && g) { yaz('cikis', new Date(g.getTime() + (eski > 0 ? eski : 7200000))); }
            boya('giris'); boya('cikis');
            clearTimeout(zamanlayici);
            zamanlayici = setTimeout(function () {
                try { sessionStorage.setItem('rz-kaydir', String(window.scrollY)); } catch (h) {}
                if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); }
            }, 650);
        }
        form.querySelectorAll('.rz-zaman-kutu').forEach(function (kok) {
            var on = kok.getAttribute('data-on');
            kutu[on] = { kok: kok, tarih: kok.querySelector('input[type=date]'), saat: kok.querySelector('input[type=hidden]'),
                         girdi: kok.querySelector('.rz-saat-girdi'), ozet: kok.querySelector('.rz-zaman-ozet') };
            var k = kutu[on];
            kok.querySelectorAll('.rz-gun[data-tarih]').forEach(function (b) {
                b.addEventListener('click', function () { k.tarih.value = b.getAttribute('data-tarih'); degisti(on, true); });
            });
            k.tarih.addEventListener('change', function () { degisti(on, true); });
            // Saat kutusu: "1430", "14.30", "14:30" yazılabilir; geçersizse eski değere döner
            k.girdi.addEventListener('change', function () {
                var m = k.girdi.value.replace(/[^0-9]/g, ''), sa, dk;
                if (m.length === 3 || m.length === 4) { sa = +m.slice(0, m.length - 2); dk = +m.slice(-2); } else if (m.length >= 1 && m.length <= 2) { sa = +m; dk = 0; }
                if (sa === undefined || sa > 23 || dk > 59) { k.girdi.value = k.saat.value; return; }
                k.saat.value = iki(sa) + ':' + iki(dk); k.girdi.value = k.saat.value; degisti(on, true);
            });
            k.girdi.addEventListener('focus', function () { k.girdi.select(); });
            kok.querySelectorAll('.rz-adim-buton').forEach(function (b) {
                b.addEventListener('click', function () {
                    var d = oku(on); if (!d) { return; }
                    d.setMinutes(d.getMinutes() + parseInt(b.getAttribute('data-yon'), 10));
                    yaz(on, d); degisti(on, true);
                });
            });
        });
        document.querySelectorAll('.rz-sure').forEach(function (b) {
            b.addEventListener('click', function () {
                var g = oku('giris'); if (!g) { return; }
                yaz('cikis', new Date(g.getTime() + parseInt(b.getAttribute('data-dk'), 10) * 60000)); degisti('cikis', false);
            });
        });
        boya('giris'); boya('cikis');
        try { var y = sessionStorage.getItem('rz-kaydir'); if (y !== null) { sessionStorage.removeItem('rz-kaydir'); window.scrollTo(0, parseInt(y, 10)); } } catch (h) {}
    })();
    </script>

    <!-- 2. ADIM: Yer seçimi -->
    <section class="rz-kart">
        <div class="rz-adim"><span>2</span><div><h2>Park yerinizi seçin</h2><p class="rz-soluk"><?= $aralikGecerli ? e(date('d.m.Y H:i', $bas)) . ' – ' . e(date('d.m.Y H:i', $bit)) . ' için ' . $bosSayi . ' uygun yer' : 'Önce geçerli bir zaman aralığı seçin' ?></p></div></div>
        <?= aracTanimlari($aracRenkleri) ?>
        <div class="rz-harita-kaydir">
            <div class="rz-harita" style="--sutun: <?= $sutun ?>" role="group" aria-label="Park yerleri">
            <?php foreach ($durumlar as $no => $d):
                $ad    = ['bos' => 'Boş', 'dolu' => 'Dolu', 'rezerve' => 'Rezerve', 'kapali' => 'Kullanım dışı'][$d];
                $renk  = $aracRenkSirasi[($no - 1) % 12];
                $tip   = $aracTipSirasi[(($no - 1) * 5) % 12];
            ?>
                <button type="button" class="rz-yer rz-<?= $d ?><?= $no === $seciliYer ? ' rz-secili' : '' ?>" data-no="<?= $no ?>" <?= ($d !== 'bos' || !$aralikGecerli) ? 'disabled' : '' ?> aria-label="<?= $no ?> numaralı yer: <?= $ad ?>" aria-pressed="<?= $no === $seciliYer ? 'true' : 'false' ?>">
                    <?php if ($d === 'dolu'): ?><?= aracOnSvg($renk, $tip, $aracRenkleri) ?>
                    <?php elseif ($d === 'bos'): ?><?= aracOnSvg($renk, $tip, $aracRenkleri) ?><?php if (!empty($park['yerler'][$no]['engelli'])): ?><small class="rz-engelli">♿</small><?php endif; ?>
                    <?php elseif ($d === 'rezerve'): ?><span class="rz-r">R</span>
                    <?php endif; ?>
                    <b class="rz-no-etiket"><?= $no ?></b>
                </button>
            <?php endforeach; ?>
            </div>
        </div>
        <div class="rz-renkler">
            <span><i class="rz-bos"></i>Boş</span><span><i class="rz-dolu"></i>Dolu</span><span><i class="rz-rezerve"></i>Rezerve</span><span><i class="rz-secili"></i>Seçiminiz</span>
        </div>
    </section>

    <!-- 3. ADIM: Bilgiler -->
    <form class="rz-kart" method="post" action="rezervasyon.php" id="rz-form" novalidate>
        <div class="rz-adim"><span>3</span><div><h2>Bilgileriniz</h2><p class="rz-soluk" id="rz-secim-yazi"><?= $seciliYer ? 'Seçilen park yeri: ' . e(yerAdi($seciliYer)) : 'Henüz yer seçmediniz' ?></p></div></div>
        <?php foreach (['giris_tarih', 'giris_saat', 'cikis_tarih', 'cikis_saat'] as $alan): ?>
            <input type="hidden" name="<?= $alan ?>" value="<?= e($v[$alan]) ?>">
        <?php endforeach; ?>
        <input type="hidden" name="yer" id="rz-yer" value="<?= $seciliYer ?: '' ?>">
        <div class="rz-form-izgara">
            <label class="<?= isset($hatalar['ad']) ? 'rz-hatali' : '' ?>">Ad soyad<input type="text" name="ad" maxlength="60" autocomplete="name" value="<?= e($v['ad']) ?>" required></label>
            <label class="<?= isset($hatalar['telefon']) ? 'rz-hatali' : '' ?>">Telefon<input type="tel" name="telefon" maxlength="16" autocomplete="tel" placeholder="05xx xxx xx xx" value="<?= e($v['telefon']) ?>" required></label>
            <label class="<?= isset($hatalar['plaka']) ? 'rz-hatali' : '' ?>">Araç plakası<input type="text" name="plaka" maxlength="12" placeholder="60 ABC 123" autocomplete="off" style="text-transform: uppercase" value="<?= e($v['plaka']) ?>" required></label>
        </div>
        <div class="rz-ozet">
            <div><span>Park yeri</span><strong id="rz-ozet-yer"><?= $seciliYer ? e(yerAdi($seciliYer)) : '—' ?></strong></div>
            <div><span>Giriş</span><strong><?= $aralikGecerli ? e(date('d.m.Y H:i', $bas)) : '—' ?></strong></div>
            <div><span>Çıkış</span><strong><?= $aralikGecerli ? e(date('d.m.Y H:i', $bit)) : '—' ?></strong></div>
            <?php if (!empty($park['saatlik'])): ?><div><span>Ücret</span><strong><?= e(number_format($park['saatlik'], 0, ',', '.')) ?> ₺ / saat, çıkışta ödenir</strong></div><?php endif; ?>
        </div>
        <button type="submit" class="rz-buton rz-genis" id="rz-gonder" <?= $seciliYer && $aralikGecerli ? '' : 'disabled' ?>>Rezervasyonu onayla</button>
    </form>

    <script>
    // Yer seçimi: boş yere dokununca sarıya döner, form o yerle doldurulur.
    (function () {
        var yerler = document.querySelectorAll('.rz-yer:not([disabled])');
        var girdi = document.getElementById('rz-yer'), yazi = document.getElementById('rz-secim-yazi');
        var ozet = document.getElementById('rz-ozet-yer'), gonder = document.getElementById('rz-gonder');
        function ad(no) { return 'P' + (no < 10 ? '0' : '') + no; }
        yerler.forEach(function (yer) {
            yer.addEventListener('click', function () {
                document.querySelectorAll('.rz-secili').forEach(function (e) { if (e.tagName === 'BUTTON') { e.classList.remove('rz-secili'); e.setAttribute('aria-pressed', 'false'); } });
                yer.classList.add('rz-secili'); yer.setAttribute('aria-pressed', 'true');
                var no = parseInt(yer.getAttribute('data-no'), 10);
                girdi.value = no; yazi.textContent = 'Seçilen park yeri: ' + ad(no); ozet.textContent = ad(no); gonder.disabled = false;
                document.getElementById('rz-form').scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        });
        // Zaman değişince seçili yeri de taşıyarak uygunluğu yeniden sor
        document.getElementById('rz-zaman').addEventListener('submit', function () {
            if (girdi.value) { var g = document.createElement('input'); g.type = 'hidden'; g.name = 'yer'; g.value = girdi.value; this.appendChild(g); }
        });
    })();
    </script>
<?php endif; ?>
</main>

<footer class="rz-alt"><?= UYGULAMA_ADI ?> &middot; Otopark rezervasyon sistemi</footer>
</body>
</html>
