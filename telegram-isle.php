<?php
/*
 * telegram-isle.php — Arka plan işleyici
 * Açık olan sayfa bu adresi birkaç saniyede bir çağırır (bkz. includes/footer.php).
 * 1) Kuyruktaki bildirimleri gönderir  2) Gelen komutları cevaplar
 * 3) Günlük abonelik uyarısını ve 23.00 gün sonu raporunu bir kez yollar
 */
require_once 'includes/functions.php';
header('Content-Type: application/json; charset=UTF-8');

if (tgAyar() === null) {
    exit('{"ok":false,"neden":"ayar-yok"}');
}

// Aynı anda yalnızca bir işleyici çalışsın (birden fazla sekme açıksa diğerleri atlar)
$kilit = @fopen(__DIR__ . '/veri/telegram.kilit', 'c');
if ($kilit === false || !flock($kilit, LOCK_EX | LOCK_NB)) {
    exit('{"ok":true,"neden":"mesgul"}');
}

$durum = tgDurum();

// Günlük abonelik uyarısı (sabah 09.00'dan sonra, günde bir kez) ve gün sonu raporu (23.00'ten sonra, günde bir kez)
$bugun = date('Y-m-d');
// "Bugün gönderildi mi?" kontrolü ve işaretleme aynı dosya kilidi içinde yapılır; iki sekme aynı anda
// çalışsa bile mesaj günde yalnızca bir kez kuyruğa girer.
$bugunIlkMi = function ($alan) use ($bugun) {
    $ilk = false;
    tgDurum(function (&$d) use ($alan, $bugun, &$ilk) {
        if ($d['acik'] && $d[$alan] !== $bugun) {
            $d[$alan] = $bugun;
            $ilk = true;
        }
    });
    return $ilk;
};
if ((int)date('G') >= 9 && $durum['son_uyari'] !== $bugun && $bugunIlkMi('son_uyari')) {
    $uyari = tgAbonelikUyariMetni();
    if ($uyari !== null) { tgBildir('uyari', $uyari); }
}
if ((int)date('G') >= 23 && $durum['son_rapor'] !== $bugun && $bugunIlkMi('son_rapor')) {
    tgBildir('rapor', tgGunSonuMetni());
}

// Komut cevapları oturumdaki kayıtlardan üretilir; ağ işlemleri sırasında oturum kilidini bırakıyoruz
// ki diğer sayfalar beklemesin. Kayıt (log) satırları sonda yeniden açılıp yazılır.
$kullanici = $_SESSION['kullanici']['kadi'] ?? '-';
$veri      = $_SESSION;
session_write_close();
$_SESSION  = $veri;
$loglar    = [];

// 1) Kuyruk: en fazla 5 mesaj gönder; başarısız olan 3 denemeye kadar kuyrukta kalır
$gonderilecek = [];
tgDurum(function (&$d) use (&$gonderilecek) {
    $gonderilecek = array_splice($d['kuyruk'], 0, 5);
});
$kalanlar = [];
foreach ($gonderilecek as $is) {
    $sonuc = tgMesajGonder($is['metin'], $is['tur']);
    if (!$sonuc['tamam'] && ++$is['deneme'] < 3) {
        $kalanlar[] = $is;
    }
}
if (count($kalanlar) > 0) {
    tgDurum(function (&$d) use ($kalanlar) { $d['kuyruk'] = array_merge($kalanlar, $d['kuyruk']); });
}

// 2) Gelen komutlar
$ayar  = tgAyar();
$yanit = tgIstek('getUpdates', ['offset' => $durum['offset'], 'timeout' => 0, 'limit' => 10, 'allowed_updates' => '["message"]'], 5);
if (!empty($yanit['ok'])) {
    $sonOffset = $durum['offset'];
    foreach ($yanit['result'] as $guncelleme) {
        $sonOffset = max($sonOffset, $guncelleme['update_id'] + 1);
        $mesaj     = $guncelleme['message'] ?? null;
        if ($mesaj === null || !isset($mesaj['text']) || !is_string($mesaj['text'])) {
            continue;
        }
        $gonderen = (string)$mesaj['chat']['id'];
        if ($gonderen !== (string)$ayar['chat_id']) {
            // Yetkisiz kişi: bilgi verilmez, deneme kaydedilir
            tgMesajGonder('Bu botu kullanma yetkiniz yok.', 'yetkisiz', $gonderen);
            $loglar[] = 'Telegram: yetkisiz erişim denemesi (chat ' . $gonderen . ')';
            tgBildir('durum', '⛔ <b>Yetkisiz erişim denemesi</b>' . "\nChat ID: " . tgK($gonderen) . "\nKomut: " . tgK(mb_substr($mesaj['text'], 0, 40, 'UTF-8')));
            continue;
        }
        $komut = mb_substr($mesaj['text'], 0, 60, 'UTF-8');
        tgMesajGonder(tgKomutCevapla($komut), 'komut');
        $loglar[] = 'Telegram komutu: ' . $komut;
    }
    tgDurum(function (&$d) use ($sonOffset) { $d['offset'] = $sonOffset; $d['son_hata'] = ''; });
} else {
    $hata = (string)($yanit['description'] ?? 'Bilinmeyen hata');
    tgDurum(function (&$d) use ($hata) { $d['son_hata'] = $hata; });
}

// Kayıtları oturuma yaz
if (count($loglar) > 0) {
    session_start();
    foreach ($loglar as $satir) {
        $_SESSION['loglar'][] = ['zaman' => time(), 'kullanici' => 'telegram', 'islem' => $satir];
    }
    session_write_close();
}

flock($kilit, LOCK_UN);
echo '{"ok":true}';
