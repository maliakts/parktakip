<?php
// Tüm sayfaların üst kısmı: <head>, yan menü ve üst şerit.
// $sayfaBasligi ve $aktifSayfa değişkenleri bu dosyayı çağıran sayfada tanımlanır.
$menuIcerideSayi = iceridekiToplam();
$menuDoluluk     = (int)round($menuIcerideSayi / TOPLAM_KAPASITE * 100);
$menuDurum       = dolulukDurumu($menuDoluluk);
$menuRezBekleyen = count(array_filter(rzVeri()['liste'], function ($r) { return $r['durum'] === 'Bekliyor'; }));
$cssSurum        = filemtime(__DIR__ . '/../assets/style.css');
$jsSurum         = filemtime(__DIR__ . '/../assets/app.js');
?>
<!DOCTYPE html>
<html lang="tr" data-tema="koyu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title><?= e($sayfaBasligi) ?> | <?= UYGULAMA_ADI ?></title>
    <!-- Tema ve menü durumu sayfa çizilmeden önce uygulanır (yanıp sönmeyi önler) -->
    <script>
    (function () {
        var h = document.documentElement;
        try {
            var t = localStorage.getItem('parktakip-tema');
            if (t !== 'acik' && t !== 'koyu') {
                t = window.matchMedia('(prefers-color-scheme: light)').matches ? 'acik' : 'koyu';
            }
            h.setAttribute('data-tema', t);
            if (localStorage.getItem('parktakip-yan') === 'dar') { h.classList.add('yan-dar'); }
        } catch (hata) { /* depolama kapalıysa varsayılan tema kalır */ }
    })();
    </script>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%233a78ea'/%3E%3Cpath d='M12 24V8h6a5 5 0 0 1 0 10h-6' fill='none' stroke='white' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E">
    <link rel="stylesheet" href="assets/style.css?v=<?= $cssSurum ?>">
    <script src="assets/app.js?v=<?= $jsSurum ?>" defer></script>
</head>
<body>
<a href="#icerik" class="atla-baglanti">İçeriğe geç</a>

<div class="uygulama">

    <!-- YAN MENÜ -->
    <aside class="yan-menu" id="yan-menu" aria-label="Ana menü">
        <div class="yan-ust">
            <a href="index.php" class="marka" title="<?= UYGULAMA_ADI ?>">
                <span class="marka-isaret"><?= ikon('otopark', 20) ?></span>
                <span class="marka-yazi"><?= UYGULAMA_ADI ?></span>
            </a>
            <button type="button" class="ikon-buton yan-daralt" id="yan-daralt" aria-label="Menüyü daralt" aria-pressed="false" title="Menüyü daralt">
                <?= ikon('daralt', 18) ?>
            </button>
        </div>

        <nav class="yan-gezinti">
            <div class="menu-grup">
                <span class="menu-baslik">Genel</span>
                <a href="index.php" class="menu-oge <?= $aktifSayfa === 'liste' ? 'aktif' : '' ?>" data-ipucu="Genel Bakış" <?= $aktifSayfa === 'liste' ? 'aria-current="page"' : '' ?>>
                    <?= ikon('panel') ?><span class="menu-metin">Genel Bakış</span>
                </a>
                <a href="giris-cikis.php" class="menu-oge <?= $aktifSayfa === 'hareket' ? 'aktif' : '' ?>" data-ipucu="Araç Giriş-Çıkış" <?= $aktifSayfa === 'hareket' ? 'aria-current="page"' : '' ?>>
                    <?= ikon('bariyer') ?><span class="menu-metin">Araç Giriş-Çıkış</span>
                    <span class="menu-sayi"><?= $menuIcerideSayi ?></span>
                </a>
                <a href="park.php" class="menu-oge <?= $aktifSayfa === 'park' ? 'aktif' : '' ?>" data-ipucu="Park Alanları" <?= $aktifSayfa === 'park' ? 'aria-current="page"' : '' ?>>
                    <?= ikon('izgara') ?><span class="menu-metin">Park Alanları</span>
                </a>
                <a href="rezervasyonlar.php" class="menu-oge <?= $aktifSayfa === 'rezervasyon' ? 'aktif' : '' ?>" data-ipucu="Rezervasyonlar" <?= $aktifSayfa === 'rezervasyon' ? 'aria-current="page"' : '' ?>>
                    <?= ikon('ajanda') ?><span class="menu-metin">Rezervasyonlar</span>
                    <?php if ($menuRezBekleyen > 0): ?><span class="menu-sayi"><?= $menuRezBekleyen ?></span><?php endif; ?>
                </a>
            </div>
            <div class="menu-grup">
                <span class="menu-baslik">Aboneler</span>
                <a href="index.php#aboneler" class="menu-oge" id="menu-aboneler" data-ipucu="Aboneler">
                    <?= ikon('kullanicilar') ?><span class="menu-metin">Aboneler</span>
                    <span class="menu-sayi"><?= count($_SESSION['araclar']) ?></span>
                </a>
                <a href="ekle.php" class="menu-oge <?= $aktifSayfa === 'ekle' ? 'aktif' : '' ?>" data-ipucu="Yeni Abone" <?= $aktifSayfa === 'ekle' ? 'aria-current="page"' : '' ?>>
                    <?= ikon('arti') ?><span class="menu-metin">Yeni Abone</span>
                </a>
                <a href="paketler.php" class="menu-oge <?= $aktifSayfa === 'paket' ? 'aktif' : '' ?>" data-ipucu="Abonelik Paketleri" <?= $aktifSayfa === 'paket' ? 'aria-current="page"' : '' ?>>
                    <?= ikon('paket') ?><span class="menu-metin">Abonelik Paketleri</span>
                </a>
            </div>
            <div class="menu-grup">
                <span class="menu-baslik">Finans ve rapor</span>
                <a href="odemeler.php" class="menu-oge <?= $aktifSayfa === 'odeme' ? 'aktif' : '' ?>" data-ipucu="Ödemeler" <?= $aktifSayfa === 'odeme' ? 'aria-current="page"' : '' ?>>
                    <?= ikon('para') ?><span class="menu-metin">Ödemeler</span>
                </a>
                <a href="raporlar.php" class="menu-oge <?= $aktifSayfa === 'rapor' ? 'aktif' : '' ?>" data-ipucu="Raporlar" <?= $aktifSayfa === 'rapor' ? 'aria-current="page"' : '' ?>>
                    <?= ikon('rapor') ?><span class="menu-metin">Raporlar</span>
                </a>
            </div>
            <div class="menu-grup">
                <span class="menu-baslik">Yönetim</span>
                <a href="kara-liste.php" class="menu-oge <?= $aktifSayfa === 'kara' ? 'aktif' : '' ?>" data-ipucu="Kara Liste" <?= $aktifSayfa === 'kara' ? 'aria-current="page"' : '' ?>>
                    <?= ikon('yasak') ?><span class="menu-metin">Kara Liste</span>
                    <?php if (count($_SESSION['kara_liste']) > 0): ?><span class="menu-sayi"><?= count($_SESSION['kara_liste']) ?></span><?php endif; ?>
                </a>
                <?php if (yoneticiMi()): ?>
                <a href="kullanicilar.php" class="menu-oge <?= $aktifSayfa === 'kullanici' ? 'aktif' : '' ?>" data-ipucu="Kullanıcı Yönetimi" <?= $aktifSayfa === 'kullanici' ? 'aria-current="page"' : '' ?>>
                    <?= ikon('kullanici') ?><span class="menu-metin">Kullanıcı Yönetimi</span>
                </a>
                <a href="ayarlar.php" class="menu-oge <?= $aktifSayfa === 'ayar' ? 'aktif' : '' ?>" data-ipucu="Ayarlar" <?= $aktifSayfa === 'ayar' ? 'aria-current="page"' : '' ?>>
                    <?= ikon('ayar') ?><span class="menu-metin">Ayarlar</span>
                </a>
                <a href="telegram.php" class="menu-oge <?= $aktifSayfa === 'telegram' ? 'aktif' : '' ?>" data-ipucu="Telegram Ayarları" <?= $aktifSayfa === 'telegram' ? 'aria-current="page"' : '' ?>>
                    <?= ikon('zil') ?><span class="menu-metin">Telegram Ayarları</span>
                </a>
                <?php endif; ?>
            </div>
        </nav>

        <div class="yan-alt">
            <div class="tesis-kart">
                <div class="tesis-baslik">
                    <span>Anlık doluluk</span>
                    <strong>%<?= $menuDoluluk ?></strong>
                </div>
                <div class="ilerleme" role="progressbar" aria-valuenow="<?= $menuDoluluk ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Otopark doluluğu">
                    <span style="width: <?= $menuDoluluk ?>%"></span>
                </div>
                <div class="tesis-alt"><?= $menuIcerideSayi ?> / <?= TOPLAM_KAPASITE ?> park yeri dolu</div>
            </div>
        </div>
    </aside>
    <div class="yan-perde" id="yan-perde" hidden></div>

    <!-- ANA ALAN -->
    <div class="ana-alan">
        <header class="ust-serit">
            <button type="button" class="ikon-buton menu-ac" id="menu-ac" aria-label="Menüyü aç" aria-controls="yan-menu" aria-expanded="false">
                <?= ikon('menu', 20) ?>
            </button>
            <nav class="ekmek" aria-label="Konum">
                <span class="ekmek-ana"><?= UYGULAMA_ADI ?></span>
                <span class="ekmek-ayrac">/</span>
                <span class="ekmek-sayfa"><?= e($sayfaBasligi) ?></span>
            </nav>
            <div class="ust-sag">
                <span class="ust-tarih"><?= ikon('takvim', 16) ?><?= date('d.m.Y') ?></span>
                <?php $uyariListesi = uyarilar(); ?>
                <details class="zil">
                    <summary class="ikon-buton" aria-label="Bildirimler (<?= count($uyariListesi) ?>)" title="Bildirimler">
                        <?= ikon('zil', 18) ?>
                        <?php if (count($uyariListesi) > 0): ?><span class="zil-sayi"><?= count($uyariListesi) ?></span><?php endif; ?>
                    </summary>
                    <div class="zil-panel">
                        <strong>Bildirimler</strong>
                        <?php if (count($uyariListesi) === 0): ?>
                            <p class="soluk">Yeni bildirim yok.</p>
                        <?php endif; ?>
                        <?php foreach ($uyariListesi as $u): ?>
                            <a href="<?= e($u['adres']) ?>"><i class="nokta" style="background: var(--<?= e($u['tur']) ?>)"></i><?= e($u['metin']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </details>
                <button type="button" class="ikon-buton" id="tema-degistir" aria-label="Temayı değiştir" title="Açık / koyu tema">
                    <span class="tema-ikon-koyu"><?= ikon('gunes', 18) ?></span>
                    <span class="tema-ikon-acik"><?= ikon('ay', 18) ?></span>
                </button>
                <span class="ust-kullanici" title="<?= e($_SESSION['kullanici']['rol']) ?>">
                    <span class="avatar"><?= e(bas_harfler($_SESSION['kullanici']['ad'])) ?></span>
                    <span class="ust-kullanici-yazi"><b><?= e($_SESSION['kullanici']['ad']) ?></b><small><?= e($_SESSION['kullanici']['rol']) ?></small></span>
                </span>
                <a href="cikis.php" class="ikon-buton" aria-label="Oturumu kapat" title="Oturumu kapat"><?= ikon('cikis', 18) ?></a>
            </div>
        </header>

        <main class="icerik" id="icerik">
<?php
// İşlem sonrası mesaj varsa göster
$mesaj = mesajAl();
if ($mesaj !== null):
?>
            <div class="bildirim bildirim-<?= e($mesaj['tur']) ?>" role="status">
                <?= ikon($mesaj['tur'] === 'basari' ? 'onay' : 'hata', 20) ?>
                <span class="bildirim-metin"><?= e($mesaj['metin']) ?></span>
                <button type="button" class="bildirim-kapat" aria-label="Mesajı kapat"><?= ikon('kapat', 16) ?></button>
            </div>
<?php endif; ?>
