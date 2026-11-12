<?php
/*
 * telegram.php — Telegram bot entegrasyonu
 *
 * Nasıl çalışır?
 *  - Token ve yönetici Chat ID kaynak kodunda değil, includes/gizli-ayar.php dosyasında tutulur.
 *  - Bildirimler doğrudan gönderilmez, veri/telegram.json içindeki kuyruğa yazılır. Böylece
 *    giriş/çıkış işlemi Telegram'ı beklemez, arayüz donmaz.
 *  - Açık olan sayfa birkaç saniyede bir telegram-isle.php adresini çağırır. O dosya kuyruğu gönderir,
 *    gelen komutları (/durum, /gelir ...) cevaplar ve saat 23.00'te gün sonu raporunu yollar.
 *  - Uygulama tarayıcıda açık değilken bot çalışmaz (kayıtlar oturumda tutulduğu için).
 */

const TG_AYAR_DOSYASI  = __DIR__ . '/gizli-ayar.php';
const TG_DURUM_DOSYASI = __DIR__ . '/../veri/telegram.json';
const TG_TURLER = [
    'giris'  => 'Araç girişi',
    'cikis'  => 'Araç çıkışı',
    'abone'  => 'Yeni abone ve yenileme',
    'odeme'  => 'Ödeme',
    'uyari'  => 'Abonelik uyarısı (günde bir kez)',
    'durum'  => 'Otopark durumu ve olağan dışı işlemler',
    'rezervasyon' => 'Online rezervasyon',
    'rapor'  => 'Gün sonu raporu (23.00)',
];

// ---------------- Yapılandırma ----------------
function tgAyar()
{
    static $ayar = false;
    if ($ayar === false) {
        $ayar = is_file(TG_AYAR_DOSYASI) ? (include TG_AYAR_DOSYASI) : null;
        if (!is_array($ayar) || empty($ayar['token']) || empty($ayar['chat_id'])) {
            $ayar = null;
        }
    }
    return $ayar;
}

function tgAyarKaydet($token, $chatId)
{
    $icerik = "<?php\n// Telegram bağlantı bilgileri. Bu dosyayı kimseyle paylaşmayın, ödev tesliminde göndermeyin.\nreturn " . var_export(['token' => $token, 'chat_id' => (string)$chatId], true) . ";\n";
    return file_put_contents(TG_AYAR_DOSYASI, $icerik, LOCK_EX) !== false;
}

// Token ekranda yalnızca maskeli gösterilir
function tgTokenMaskeli()
{
    $ayar = tgAyar();
    if ($ayar === null) {
        return null;
    }
    $parca = explode(':', $ayar['token'], 2);
    return $parca[0] . ':••••••••' . substr($ayar['token'], -4);
}

// ---------------- Durum dosyası (kuyruk, tercihler, son mesajlar) ----------------
function tgVarsayilanDurum()
{
    return ['acik' => true, 'turler' => array_fill_keys(array_keys(TG_TURLER), true), 'offset' => 0,
            'son_rapor' => '', 'son_uyari' => '', 'kuyruk' => [], 'mesajlar' => [], 'bot' => '', 'son_hata' => ''];
}

// tgDurum: dosyayı kilitleyerek okur; $degistir verilirse değişikliği aynı kilit içinde yazar.
function tgDurum($degistir = null)
{
    $dosya = @fopen(TG_DURUM_DOSYASI, 'c+');
    if ($dosya === false) {
        return tgVarsayilanDurum();
    }
    flock($dosya, LOCK_EX);
    $ham   = stream_get_contents($dosya);
    $durum = json_decode($ham === false ? '' : $ham, true);
    $durum = is_array($durum) ? $durum + tgVarsayilanDurum() : tgVarsayilanDurum();
    $durum['turler'] += array_fill_keys(array_keys(TG_TURLER), true);
    if ($degistir !== null) {
        $degistir($durum);
        ftruncate($dosya, 0);
        rewind($dosya);
        fwrite($dosya, json_encode($durum, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
    flock($dosya, LOCK_UN);
    fclose($dosya);
    return $durum;
}

// ---------------- Telegram API ----------------
// tgIstek: Bot API çağrısı. Token hiçbir hata metnine ya da kayda yazılmaz.
function tgIstek($metod, $parametreler = [], $zamanAsimi = 6)
{
    $ayar = tgAyar();
    if ($ayar === null) {
        return ['ok' => false, 'description' => 'Token ya da Chat ID tanımlı değil.'];
    }
    $adres = 'https://api.telegram.org/bot' . $ayar['token'] . '/' . $metod;
    $govde = http_build_query($parametreler);

    if (function_exists('curl_init')) {
        $c = curl_init($adres);
        curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $govde, CURLOPT_RETURNTRANSFER => true,
                               CURLOPT_TIMEOUT => $zamanAsimi, CURLOPT_CONNECTTIMEOUT => 4]);
        $yanit = curl_exec($c);
        $hata  = curl_error($c);
        curl_close($c);
        if ($yanit === false) {
            return ['ok' => false, 'description' => 'Bağlantı kurulamadı: ' . str_replace($ayar['token'], '***', $hata)];
        }
    } else {
        $baglam = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                                                    'content' => $govde, 'timeout' => $zamanAsimi, 'ignore_errors' => true]]);
        $yanit = @file_get_contents($adres, false, $baglam);
        if ($yanit === false) {
            return ['ok' => false, 'description' => 'Bağlantı kurulamadı (PHP curl ya da openssl eklentisi kapalı olabilir).'];
        }
    }
    $sonuc = json_decode($yanit, true);
    return is_array($sonuc) ? $sonuc : ['ok' => false, 'description' => 'Telegram anlaşılmayan bir yanıt verdi.'];
}

// tgMesajGonder: hemen gönderir (test mesajı ve kuyruk işleyici kullanır). Sonucu son mesajlara yazar.
function tgMesajGonder($metin, $tur = 'test', $chatId = null)
{
    $ayar  = tgAyar();
    $sonuc = tgIstek('sendMessage', ['chat_id' => $chatId ?? ($ayar['chat_id'] ?? ''), 'text' => $metin, 'parse_mode' => 'HTML']);
    $tamam = !empty($sonuc['ok']);
    $hata  = $tamam ? '' : (string)($sonuc['description'] ?? 'Bilinmeyen hata');
    if ($chatId === null) {
        tgDurum(function (&$d) use ($metin, $tur, $tamam, $hata) {
            array_unshift($d['mesajlar'], ['zaman' => time(), 'tur' => $tur, 'metin' => mb_substr(strip_tags($metin), 0, 160, 'UTF-8'), 'tamam' => $tamam, 'hata' => $hata]);
            $d['mesajlar'] = array_slice($d['mesajlar'], 0, 25);
            $d['son_hata'] = $hata;
        });
    }
    return ['tamam' => $tamam, 'hata' => $hata];
}

// tgBildir: bildirimi kuyruğa yazar (beklemez). Bildirimler ya da o tür kapalıysa hiçbir şey yapmaz.
function tgBildir($tur, $metin)
{
    if (tgAyar() === null) {
        return;
    }
    tgDurum(function (&$d) use ($tur, $metin) {
        if (!$d['acik'] || empty($d['turler'][$tur])) {
            return;
        }
        $d['kuyruk'][] = ['tur' => $tur, 'metin' => $metin, 'deneme' => 0];
        $d['kuyruk']   = array_slice($d['kuyruk'], -50);   // kuyruk sınırsız büyümesin
    });
}

function tgK($metin)   // HTML kipinde güvenli metin
{
    return htmlspecialchars((string)$metin, ENT_NOQUOTES, 'UTF-8');
}

// ---------------- Hazır metinler ----------------
function tgDurumMetni()
{
    $dolu = count(doluYerler());
    $bos  = bosParkYeri();
    return "🅿️ <b>Otopark durumu</b>\nKapasite: " . TOPLAM_KAPASITE . "\nDolu: $dolu (" . durumaGoreSay($_SESSION['araclar'], 'İçeride') . ' abone, ' . ziyaretciIcerideSayisi() . " ziyaretçi)\nBoş: $bos\nRezerve / kapalı: " . (TOPLAM_KAPASITE - $dolu - $bos) . "\nDoluluk: %" . (int)round($dolu / TOPLAM_KAPASITE * 100);
}

function tgAbonelikUyariMetni()
{
    $yakin = []; $bugun = []; $gecmis = [];
    foreach ($_SESSION['araclar'] as $k) {
        $gun   = kalanGun($k['son_tarih']);
        $satir = '• ' . tgK($k['plaka']) . ' — ' . tgK($k['ad_soyad']);
        if ($gun < 0)       { $gecmis[] = $satir . ' (' . abs($gun) . ' gün önce)'; }
        elseif ($gun === 0) { $bugun[]  = $satir; }
        elseif ($gun <= 7)  { $yakin[]  = $satir . ' (' . $gun . ' gün)'; }
    }
    if (count($yakin) + count($bugun) + count($gecmis) === 0) {
        return null;
    }
    $metin = "⚠️ <b>Abonelik uyarısı</b>";
    if ($bugun)  { $metin .= "\n\n<b>Bugün doluyor</b>\n" . implode("\n", $bugun); }
    if ($yakin)  { $metin .= "\n\n<b>7 gün içinde bitecek</b>\n" . implode("\n", $yakin); }
    if ($gecmis) { $metin .= "\n\n<b>Süresi geçmiş</b>\n" . implode("\n", $gecmis); }
    return $metin;
}

function tgGunSonuMetni()
{
    $bugun = date('Y-m-d');
    $h     = bugunHareketleri();
    $yeni  = 0; $aktif = 0;
    foreach ($_SESSION['araclar'] as $k) {
        if (($k['kayit_tarihi'] ?? null) === $bugun) { $yeni++; }
        if (kalanGun($k['son_tarih']) >= 0 && empty($k['pasif'])) { $aktif++; }
    }
    $odemeSayisi = 0;
    foreach ($_SESSION['odemeler'] as $o) {
        if (date('Y-m-d', $o['zaman']) === $bugun) { $odemeSayisi++; }
    }
    $dolu = count(doluYerler());
    return "📊 <b>Gün sonu raporu — " . date('d.m.Y') . "</b>\nAraç girişi: {$h['giris']}\nAraç çıkışı: {$h['cikis']}\nToplam gelir: " . paraYaz(gelirToplami($bugun)) . "\nÖdeme sayısı: $odemeSayisi\nYeni abone: $yeni\nAktif abone: $aktif\nGün sonu doluluk: $dolu / " . TOPLAM_KAPASITE . ' (%' . (int)round($dolu / TOPLAM_KAPASITE * 100) . ')';
}

// ---------------- Komutlar ----------------
// tgKomutCevapla: yetkili yöneticiden gelen komutun cevabını oturumdaki gerçek kayıtlardan üretir.
function tgKomutCevapla($metin)
{
    $parcalar = preg_split('/\s+/', trim($metin), 2);
    $komut    = strtolower(explode('@', $parcalar[0])[0]);
    $plaka    = isset($parcalar[1]) ? plakaDuzenle($parcalar[1]) : '';
    $yardim   = "<b>Komutlar</b>\n/durum — Otoparkın anlık durumu\n/iceride — İçerideki araçlar\n/arac PLAKA — Araç bilgisi\n/aboneler — Aktif abone sayısı\n/abonelik PLAKA — Abonelik durumu\n/gelir — Bugünkü gelir\n/aylikgelir — Bu ayki gelir\n/bosyer — Boş park yeri\n/yardim — Bu liste";

    switch ($komut) {
        case '/start':
            return "👋 <b>" . UYGULAMA_ADI . "</b> botuna hoş geldiniz.\nOtopark açıkken giriş, çıkış, ödeme ve uyarı bildirimleri buraya gelir.\n\n" . $yardim;
        case '/yardim':
        case '/help':
            return $yardim;
        case '/durum':
            return tgDurumMetni();
        case '/bosyer':
            return '🅿️ Boş park yeri: <b>' . bosParkYeri() . '</b> / ' . TOPLAM_KAPASITE;
        case '/gelir':
            return '💰 Bugünkü gelir (' . date('d.m.Y') . '): <b>' . paraYaz(gelirToplami(date('Y-m-d'))) . '</b>';
        case '/aylikgelir':
            return '💰 Bu ayki gelir (' . date('m.Y') . '): <b>' . paraYaz(gelirToplami(date('Y-m'))) . '</b>';
        case '/aboneler':
            $aktif = 0; $dolan = 0; $pasif = 0;
            foreach ($_SESSION['araclar'] as $k) {
                if (!empty($k['pasif'])) { $pasif++; } elseif (kalanGun($k['son_tarih']) < 0) { $dolan++; } else { $aktif++; }
            }
            return "👤 Aktif abone: <b>$aktif</b>\nSüresi dolmuş: $dolan\nPasif: $pasif\nToplam kayıt: " . count($_SESSION['araclar']);
        case '/iceride':
            $satirlar = [];
            foreach (doluYerler() as $no => $h) {
                $satirlar[$no] = yerAdi($no) . ' · ' . tgK($h['plaka']) . ' · ' . tgK($h['tip']) . ' · ' . date('H:i', $h['giris']);
            }
            ksort($satirlar);
            if (count($satirlar) === 0) {
                return '🅿️ Otoparkta araç yok.';
            }
            $fazla = count($satirlar) > 40 ? "\n… ve " . (count($satirlar) - 40) . ' araç daha' : '';
            return '🚗 <b>İçerideki araçlar (' . count($satirlar) . ")</b>\n" . implode("\n", array_slice($satirlar, 0, 40)) . $fazla;
        case '/arac':
        case '/abonelik':
            if ($plaka === '' || !plakaGecerliMi($plaka)) {
                return 'Plaka yazınız. Örnek: <code>' . $komut . ' 60 ABC 123</code>';
            }
            $sira = aboneBul($plaka);
            if ($komut === '/abonelik') {
                if ($sira === null) {
                    return tgK($plaka) . ' abone listesinde yok.';
                }
                $k = $_SESSION['araclar'][$sira]; $gun = kalanGun($k['son_tarih']);
                $durum = !empty($k['pasif']) ? 'Pasif' : ($gun < 0 ? 'Süresi doldu (' . abs($gun) . ' gün önce)' : 'Aktif, ' . $gun . ' gün kaldı');
                return '👤 <b>' . tgK($k['ad_soyad']) . "</b>\nPlaka: " . tgK($plaka) . "\nPaket: " . tgK($k['abone_turu']) . "\nBitiş: " . date('d.m.Y', strtotime($k['son_tarih'])) . "\nDurum: $durum";
            }
            $acik  = acikHareket($plaka);
            $yasak = karaListede($plaka);
            $metin = '🚗 <b>' . tgK($plaka) . "</b>\n";
            $metin .= $sira !== null ? 'Abone: ' . tgK($_SESSION['araclar'][$sira]['ad_soyad']) . ' (' . tgK($_SESSION['araclar'][$sira]['abone_turu']) . ")\n" : "Abone değil (ziyaretçi)\n";
            $metin .= $acik !== null ? 'İçeride · ' . yerAdi($acik['yer']) . ' · giriş ' . date('d.m.Y H:i', $acik['giris']) . ' · ' . sureYazi(sureDakika($acik['giris'])) : 'Şu an otoparkta değil';
            if ($yasak !== null) { $metin .= "\n⛔ Kara listede: " . tgK($yasak['sebep']); }
            return $metin;
    }
    return "Bu komutu tanımıyorum.\n\n" . $yardim;
}
