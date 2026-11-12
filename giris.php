<?php
/*
 * giris.php — Kullanıcı girişi
 * Kullanıcı adı ve şifre kontrol edilir; şifreler password_hash ile saklanır,
 * password_verify ile doğrulanır. Hatalı denemeler işlem geçmişine yazılır.
 */
require_once 'includes/functions.php';

if (isset($_SESSION['kullanici'])) {
    yonlendir('index.php');
}

$hata = '';
$kadi = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kadi  = formMetni('kadi');
    $sifre = isset($_POST['sifre']) && is_string($_POST['sifre']) ? $_POST['sifre'] : '';

    if ($kadi === '' || $sifre === '') {
        $hata = 'Kullanıcı adı ve şifre boş bırakılamaz.';
    } else {
        foreach ($_SESSION['kullanicilar'] as $k) {
            if ($k['kadi'] === $kadi && $k['aktif'] && password_verify($sifre, $k['sifre'])) {
                session_regenerate_id(true);   // oturum sabitleme saldırısına karşı
                $_SESSION['kullanici'] = ['kadi' => $k['kadi'], 'ad' => $k['ad'], 'rol' => $k['rol']];
                logKaydet('Oturum açıldı');
                yonlendir('index.php');
            }
        }
        $hata = 'Kullanıcı adı veya şifre hatalı.';
        $_SESSION['loglar'][] = ['zaman' => time(), 'kullanici' => mb_substr($kadi, 0, 30, 'UTF-8'), 'islem' => 'Hatalı giriş denemesi'];
    }
}
?>
<!DOCTYPE html>
<html lang="tr" data-tema="koyu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş | <?= UYGULAMA_ADI ?></title>
    <script>
    (function () { try { var t = localStorage.getItem('parktakip-tema'); if (t !== 'acik' && t !== 'koyu') { t = window.matchMedia('(prefers-color-scheme: light)').matches ? 'acik' : 'koyu'; } document.documentElement.setAttribute('data-tema', t); } catch (h) {} })();
    </script>
    <link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/assets/style.css') ?>">
</head>
<body>
<main class="giris-sayfa">
    <form class="kart giris-kutu" method="post" action="giris.php" novalidate>
        <span class="marka-isaret"><?= ikon('otopark', 22) ?></span>
        <h1><?= UYGULAMA_ADI ?></h1>
        <p class="soluk">Otopark Abone ve Giriş-Çıkış Takip Sistemi</p>

        <?php if ($hata !== ''): ?>
            <div class="bildirim bildirim-hata" role="alert"><?= ikon('hata', 18) ?><span class="bildirim-metin"><?= e($hata) ?></span></div>
        <?php endif; ?>

        <div class="alan">
            <label for="kadi">Kullanıcı adı</label>
            <input type="text" id="kadi" name="kadi" value="<?= e($kadi) ?>" maxlength="30" autocomplete="username" autofocus>
        </div>
        <div class="alan">
            <label for="sifre">Şifre</label>
            <input type="password" id="sifre" name="sifre" maxlength="72" autocomplete="current-password">
        </div>
        <button type="submit" class="buton"><?= ikon('giris', 16) ?>Oturum aç</button>

        <div class="giris-not">
            <strong>Deneme hesapları</strong>
            <span>Yönetici: <code>admin</code> / <code>admin123</code></span>
            <span>Personel: <code>personel</code> / <code>personel123</code></span>
        </div>
    </form>
</main>
</body>
</html>
