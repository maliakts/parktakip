<?php
/*
 * park-alani.php — Kuşbakışı otopark görünümü (Genel Bakış ve Araç Giriş-Çıkış sayfalarında kullanılır)
 * Kapasite kadar numaralı park yeri çizilir. Dolu yerde araç durur, sıradaki boş yer yeşil yanar.
 * Az önce giriş yapan araç kapıdan girip yerine sürülür (animasyon aşağıdaki küçük betikte).
 * Çağıran sayfa isterse $parkKol ile kart genişliğini verir (varsayılan kol-12).
 */
require_once __DIR__ . '/arac-svg.php';
$parkKol     = $parkKol ?? 'kol-12';
$parkDolu    = doluYerler();
$parkBos     = bosParkYeri();
$parkSayi    = count($parkDolu);
$parkYuzde   = (int)round($parkSayi / TOPLAM_KAPASITE * 100);
$parkRez      = rzSimdiRezerve();   // online rezervasyonla ayrılmış yerler
$parkSiradaki = yerAta();   // bir sonraki aracın alacağı yer

// Az önce giriş yapan aracın yeri (90 saniye içinde bir kez gösterilir)
$parkYeni = null;
if (isset($_SESSION['yeni_yer'])) {
    if (time() - $_SESSION['yeni_yer']['zaman'] <= 90 && isset($parkDolu[$_SESSION['yeni_yer']['no']])) {
        $parkYeni = $_SESSION['yeni_yer']['no'];
    }
    unset($_SESSION['yeni_yer']);
}
?>
    <div class="kart park-kart <?= e($parkKol) ?>" id="park-kart">
        <div class="kart-ic">
            <div class="kart-ust">
                <div>
                    <h2>Park Alanı</h2>
                    <div class="park-ozet"><strong><?= $parkSayi ?></strong><span>araç içeride &middot; <?= $parkBos ?> boş yer</span></div>
                </div>
                <div class="park-butonlar">
                    <a href="giris-cikis.php#iceridekiler" class="buton buton-ikincil buton-kucuk"><?= ikon('arac', 15) ?>İçerideki araçlar</a>
                    <button type="button" class="buton buton-ikincil buton-kucuk" id="park-ac"><?= ikon('izgara', 15) ?><span>Tam ekran</span></button>
                </div>
            </div>
            <div class="park-cubuk" role="img" aria-label="Doluluk yüzde <?= $parkYuzde ?>"><span style="width: <?= min(100, $parkYuzde) ?>%"></span></div>

            <svg class="arac-tanim" width="0" height="0" aria-hidden="true"><defs>
                <linearGradient id="agl" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#0C447C"/><stop offset=".5" stop-color="#1c1c1b"/><stop offset="1" stop-color="#5F5E5A"/></linearGradient>
                <linearGradient id="agp" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#042C53"/><stop offset=".55" stop-color="#2C2C2A"/><stop offset="1" stop-color="#185FA5"/></linearGradient>
                <filter id="afar" x="-60%" y="-80%" width="220%" height="260%"><feGaussianBlur stdDeviation="2.6"/></filter>
                <linearGradient id="ash" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".75"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></linearGradient>
                <?php foreach ($aracRenkleri as $ri => $p): ?>
                <linearGradient id="ag<?= $ri ?>" x1="0" x2="1"><stop offset="0" stop-color="<?= $p[0] ?>"/><stop offset=".3" stop-color="<?= $p[1] ?>"/><stop offset=".58" stop-color="<?= $p[2] ?>"/><stop offset="1" stop-color="<?= $p[0] ?>"/></linearGradient>
                <?php endforeach; ?>
            </defs></svg>

            <div class="otopark" id="otopark" role="img" aria-label="<?= TOPLAM_KAPASITE ?> park yerinin <?= $parkSayi ?> tanesi dolu" title="Tüm park yerlerini görmek için tıklayın">
                <div class="otopark-kapi" aria-hidden="true">
                    <svg viewBox="0 0 44 50">
                        <rect x="3" y="10" width="24" height="30" rx="3" fill="#dfe4ea"/><rect x="6" y="15" width="18" height="11" rx="2" fill="#bcd7ff"/>
                        <path d="M0 11 L15 3 L30 11 Z" fill="#2f6fe0"/>
                        <rect x="29" y="26" width="5" height="14" rx="2" fill="#e24b4a"/>
                        <g class="kapi-kol"><rect x="31" y="28" width="34" height="4" rx="2" fill="#fff" stroke="#c9ced6" stroke-width=".6"/><rect x="38" y="28" width="6" height="4" fill="#e24b4a"/><rect x="51" y="28" width="6" height="4" fill="#e24b4a"/></g>
                    </svg>
                </div>
                <div class="otopark-yerler" style="--sutun: <?= (int)ceil(TOPLAM_KAPASITE / 2) ?>">
                <?php $parkSira = 0; foreach ($_SESSION['yerler'] as $no => $yer):
                    $parkSira++;
                    $dolu  = isset($parkDolu[$no]);
                    $rezli = !$dolu && isset($parkRez[$no]);
                    $sinif = $dolu ? 'peron-dolu' : (($yer['durum'] === 'rezerve' || $rezli) ? 'peron-rezerve' : ($yer['durum'] === 'kapali' ? 'peron-kapali' : ($no === $parkSiradaki ? 'peron-siradaki' : '')));
                    if ($no === $parkYeni) { $sinif .= ' peron-yeni'; }
                    $ipucu = yerAdi($no) . ($dolu ? ' · ' . $parkDolu[$no]['plaka'] . ' · ' . $parkDolu[$no]['tip'] : ($rezli ? ' · Rezervasyon ' . $parkRez[$no]['no'] . ' · ' . $parkRez[$no]['plaka'] . ' · ' . date('H:i', $parkRez[$no]['giris']) : ($yer['durum'] === 'rezerve' ? ' · Rezerve' : ($yer['durum'] === 'kapali' ? ' · Kullanım dışı' : ($no === $parkSiradaki ? ' · Sıradaki boş yer' : ' · Boş')))));
                    $renk  = $aracRenkSirasi[($no - 1) % 12];
                    $tip   = $aracTipSirasi[(($no - 1) * 5) % 12];
                ?>
                    <div class="peron <?= $sinif ?>" title="<?= e($ipucu) ?>">
                        <?php if ($dolu): ?>
                            <?= aracOnSvg($renk, $tip, $aracRenkleri) ?>
                            <?php if ($no === $parkYeni): ?><?= aracSvg($renk, $tip, $aracRenkleri) ?><?php endif; ?>
                        <?php elseif ($no === $parkSiradaki && $yer['durum'] === 'normal'): ?>
                            <span class="peron-p">P</span>
                        <?php elseif ($yer['durum'] === 'rezerve' || $rezli): ?>
                            <span class="peron-yazi">R</span>
                        <?php elseif ($yer['engelli'] && $yer['durum'] === 'normal'): ?>
                            <span class="peron-engelli"><?= ikon('engelli', 18) ?></span>
                        <?php endif; ?>
                        <span class="peron-no"><?= $no ?></span>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>

            <div class="yer-aciklama">
                <span><i class="yer-isaret dolu"></i>Dolu <b><?= $parkSayi ?></b></span>
                <span><i class="yer-isaret"></i>Boş <b><?= $parkBos ?></b></span>
                <span><i class="yer-isaret siradaki"></i>Sıradaki yer<?= $parkSiradaki !== null ? ' <b>' . e(yerAdi($parkSiradaki)) . '</b>' : '' ?></span>
                <span><i class="yer-isaret rezerve"></i>Rezerve</span>
                <span><i class="yer-isaret kapali"></i>Kullanım dışı</span>
                <a class="yer-not" href="park.php">Park alanlarını yönet</a>
            </div>
        </div>
    </div>
    <script>
    // Park alanı: tıklayınca tam ekran açılır (tüm yerler). Yeni giren araç kapıdan girip yerine sürülür.
    (function () {
        var kart = document.getElementById('park-kart'), alan = document.getElementById('otopark');
        var dugme = document.getElementById('park-ac'), dugme2 = document.getElementById('park-ac-2');
        if (!kart || !alan) { return; }
        var oynadi = false;

        function sur() {
            var peron = alan.querySelector('.peron-yeni');
            if (!peron || oynadi || peron.offsetParent === null) { return; }   // yer görünmüyorsa tam ekranda oynar
            oynadi = true;
            var ust = peron.querySelector('.arac3d'), on = peron.querySelector('.arac-on');
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !ust || !ust.animate) {
                peron.classList.add('peron-geldi', 'peron-vurgu');
                return;
            }
            function konum(el) { var x = 0, y = 0; while (el && el !== alan) { x += el.offsetLeft; y += el.offsetTop; el = el.offsetParent; } return { x: x, y: y }; }
            var p  = konum(peron), w = peron.offsetWidth, h = peron.offsetHeight;
            var cx = p.x + w / 2, cy = p.y + h / 2;
            var gx = 30 - cx, gy = 34 - cy;       // kapı
            var yol = h / 2 + 38;                 // sıranın önündeki yol
            var d1 = Math.abs(yol - gy), d2 = Math.abs(gx), d3 = yol, don = 50;
            var toplam = d1 + d2 + d3 + 2 * don, t = 0;
            function k(m) { t += m; return Math.min(0.999, t / toplam); }
            var kareler = [
                { offset: 0,      transform: 'translate(' + gx + 'px,' + gy + 'px) rotate(180deg)', opacity: 0 },
                { offset: 0.05,   transform: 'translate(' + gx + 'px,' + gy + 'px) rotate(180deg)', opacity: 1 },
                { offset: k(d1),  transform: 'translate(' + gx + 'px,' + yol + 'px) rotate(180deg)' },
                { offset: k(don), transform: 'translate(' + gx + 'px,' + yol + 'px) rotate(90deg)' },
                { offset: k(d2),  transform: 'translate(0px,' + yol + 'px) rotate(90deg)' },
                { offset: k(don), transform: 'translate(0px,' + yol + 'px) rotate(180deg)' },
                { offset: 1,      transform: 'translate(0px,0px) rotate(180deg)', opacity: 1 }
            ];
            peron.classList.add('peron-suruyor');
            alan.classList.add('kapi-acik');
            var sure = Math.max(2400, Math.min(6000, toplam * 5));
            ust.animate(kareler, { duration: sure, easing: 'cubic-bezier(.45,.05,.3,1)', fill: 'both' }).onfinish = function () {
                peron.classList.remove('peron-suruyor');
                peron.classList.add('peron-geldi', 'peron-vurgu');
                alan.classList.remove('kapi-acik');
                if (on.animate) { on.animate([{ opacity: 0 }, { opacity: 1 }], { duration: 320, easing: 'ease-out' }); }
            };
        }

        function ayarla(acik) {
            var icerik = document.getElementById('icerik');
            if (icerik) { icerik.style.animation = 'none'; }   // giriş animasyonu sabit konumu sayfaya bağlamasın
            kart.classList.toggle('park-tam', acik);
            document.documentElement.style.overflow = acik ? 'hidden' : '';
            if (dugme) { dugme.querySelector('span').textContent = acik ? 'Kapat' : 'Tam ekran'; }
            if (acik) { setTimeout(sur, 350); }
        }
        if (dugme)  { dugme.addEventListener('click', function () { ayarla(!kart.classList.contains('park-tam')); }); }
        if (dugme2) { dugme2.addEventListener('click', function () { ayarla(true); }); }
        alan.addEventListener('click', function () { if (!kart.classList.contains('park-tam')) { ayarla(true); } });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && kart.classList.contains('park-tam')) { ayarla(false); } });

        if (document.readyState === 'complete') { setTimeout(sur, 400); } else { window.addEventListener('load', function () { setTimeout(sur, 400); }); }
    })();
    </script>
