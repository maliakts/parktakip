<?php
/*
 * rezervasyon.php — Online rezervasyon altyapısı
 *
 * Yönetim panelinin kayıtları tarayıcı oturumunda ($_SESSION) durur; müşteri ise başka bir cihazdan gelir
 * ve o oturumu göremez. Bu yüzden iki ortak dosya kullanılır (ikisi de veri/ klasöründe, tarayıcıdan açılamaz):
 *   - veri/park-durum.php      : park yerlerinin anlık durumu. Yönetim paneli her istekte günceller.
 *   - veri/rezervasyonlar.php  : tüm rezervasyonlar. Müşteri sayfası da yönetim paneli de buradan okur.
 * Dosya kilidi (flock) sayesinde aynı anda gelen iki rezervasyon isteği sırayla işlenir; uygunluk
 * kontrolü ve kayıt aynı kilit içinde yapıldığı için aynı yer aynı saatlere iki kez verilemez.
 */

const RZ_DOSYA       = __DIR__ . '/../veri/rezervasyonlar.php';
const RZ_PARK_DOSYA  = __DIR__ . '/../veri/park-durum.php';
// Veri dosyaları .php uzantılıdır ve bu satırla başlar: tarayıcıdan açılırsa içerik gösterilmeden biter.
const RZ_KORUMA      = "<?php exit; ?>\n";
const RZ_TUTMA_DK    = 120;                                  // rezervasyon başlamadan kaç dakika önce yer ayrılır
const RZ_EN_UZUN_GUN = 30;                                   // bir rezervasyon en fazla kaç gün sürebilir
const RZ_ILERI_GUN   = 60;                                   // en fazla kaç gün sonrası için rezervasyon yapılabilir
const RZ_ACIK        = ['Bekliyor', 'Onaylandı', 'Aktif'];   // yeri meşgul eden durumlar
const RZ_DURUMLAR    = ['Bekliyor', 'Onaylandı', 'Aktif', 'Tamamlandı', 'İptal', 'Süresi doldu'];

// rzVeri: rezervasyon dosyasını kilitleyerek okur. $degistir verilirse değişiklik aynı kilit içinde yazılır
// (işlem bütünlüğü). Süresi geçen rezervasyonlar her okumada otomatik güncellenir.
function rzVeri($degistir = null)
{
    $bos   = ['sonraki' => 1, 'liste' => []];
    $dosya = @fopen(RZ_DOSYA, 'c+');
    if ($dosya === false) {
        return $bos;
    }
    flock($dosya, LOCK_EX);
    $ham  = stream_get_contents($dosya);
    $veri = json_decode(substr($ham === false ? '' : $ham, strlen(RZ_KORUMA)), true);
    $veri = is_array($veri) && isset($veri['liste']) ? $veri : $bos;

    $degisti = false;
    $simdi   = time();
    foreach ($veri['liste'] as $i => $r) {
        // Bitiş saati geçti ve araç hiç gelmediyse: süresi doldu
        if (in_array($r['durum'], ['Bekliyor', 'Onaylandı'], true) && $r['cikis'] <= $simdi) {
            $veri['liste'][$i]['durum'] = 'Süresi doldu';
            $degisti = true;
        }
    }
    if ($degistir !== null) {
        $degistir($veri);
        $degisti = true;
    }
    if ($degisti) {
        ftruncate($dosya, 0);
        rewind($dosya);
        fwrite($dosya, RZ_KORUMA . json_encode($veri, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
    flock($dosya, LOCK_UN);
    fclose($dosya);
    return $veri;
}

// rzCakisan: verilen yerde, verilen zaman aralığıyla çakışan açık rezervasyonu döndürür (yoksa null).
// İki aralık, biri diğeri bitmeden başlıyorsa çakışır.
function rzCakisan($liste, $yer, $bas, $bit)
{
    foreach ($liste as $r) {
        if ($r['yer'] === $yer && in_array($r['durum'], RZ_ACIK, true) && $r['giris'] < $bit && $bas < $r['cikis']) {
            return $r;
        }
    }
    return null;
}

// rzSimdiRezerve: şu an (ya da RZ_TUTMA_DK içinde) başlayacak rezervasyonu olan yerler → [yer no => rezervasyon]
// Yönetim paneli bu yerlere başka araç atamaz.
function rzSimdiRezerve($yenile = false)
{
    static $onbellek = null;
    if ($onbellek === null || $yenile) {
        $onbellek = [];
        $simdi    = time();
        foreach (rzVeri()['liste'] as $r) {
            if (in_array($r['durum'], ['Bekliyor', 'Onaylandı'], true) && $r['giris'] - RZ_TUTMA_DK * 60 <= $simdi && $simdi < $r['cikis']) {
                $onbellek[$r['yer']] = $r;
            }
        }
    }
    return $onbellek;
}

// rzPlakaIcin: bu plakanın şu an kullanılabilecek (bekleyen/onaylı, zamanı gelmiş) rezervasyonu
function rzPlakaIcin($plaka)
{
    foreach (rzSimdiRezerve() as $r) {
        if ($r['plaka'] === $plaka) {
            return $r;
        }
    }
    return null;
}

function rzBul($anahtar, $deger)
{
    foreach (rzVeri()['liste'] as $r) {
        if ($r[$anahtar] === $deger) {
            return $r;
        }
    }
    return null;
}

// rzDurumDegistir: rezervasyonun durumunu değiştirir (onay, iptal, aktif, tamamlandı). Güncel kaydı döndürür.
function rzDurumDegistir($id, $yeniDurum)
{
    $sonuc = null;
    rzVeri(function (&$veri) use ($id, $yeniDurum, &$sonuc) {
        foreach ($veri['liste'] as $i => $r) {
            if ($r['id'] === $id) {
                $veri['liste'][$i]['durum'] = $yeniDurum;
                $sonuc = $veri['liste'][$i];
            }
        }
    });
    rzSimdiRezerve(true);
    return $sonuc;
}

// ---------------- Park durumu (yönetim panelinden müşteri sayfasına) ----------------
// rzParkDurumYaz: yönetim panelindeki gerçek park yerlerini ortak dosyaya yazar. Plaka gibi kişisel bilgi yazılmaz;
// yalnızca yerin durumu, dolu olup olmadığı ve kara listedeki plakalar (rezervasyonu reddetmek için) bulunur.
function rzParkDurumYaz()
{
    if (!isset($_SESSION['yerler'], $_SESSION['hareketler'])) {
        return;
    }
    $dolu   = doluYerler();
    $yerler = [];
    foreach ($_SESSION['yerler'] as $no => $yer) {
        $yerler[$no] = ['durum' => $yer['durum'], 'engelli' => $yer['engelli'], 'dolu' => isset($dolu[$no])];
    }
    $veri = ['zaman' => time(), 'yerler' => $yerler, 'kara' => array_column($_SESSION['kara_liste'] ?? [], 'plaka'),
             'saatlik' => $_SESSION['ayarlar']['saatlik'] ?? null, 'gunluk' => $_SESSION['ayarlar']['gunluk'] ?? null];
    @file_put_contents(RZ_PARK_DOSYA, RZ_KORUMA . json_encode($veri, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function rzParkDurum()
{
    $ham  = @file_get_contents(RZ_PARK_DOSYA);
    $veri = json_decode(substr($ham === false ? '' : $ham, strlen(RZ_KORUMA)), true);
    return is_array($veri) && !empty($veri['yerler']) ? $veri : null;
}

// rzYerDurumlari: müşteri sayfası için, istenen zaman aralığında her yerin durumu
//   bos (yeşil) · dolu (kırmızı) · rezerve (mavi) · kapali (gri)
// Fiziksel doluluk yalnızca bugün başlayan rezervasyonları engeller; ileri tarihli rezervasyonda
// yalnızca o saatlerdeki diğer rezervasyonlara bakılır.
function rzYerDurumlari($park, $liste, $bas, $bit)
{
    $sonuc    = [];
    $bugunMu  = date('Y-m-d', $bas) === date('Y-m-d');
    foreach ($park['yerler'] as $no => $yer) {
        $no = (int)$no;
        if ($yer['durum'] === 'kapali')                       { $sonuc[$no] = 'kapali'; }
        elseif ($yer['durum'] === 'rezerve')                  { $sonuc[$no] = 'rezerve'; }
        elseif (rzCakisan($liste, $no, $bas, $bit) !== null)  { $sonuc[$no] = 'rezerve'; }
        elseif ($yer['dolu'] && $bugunMu)                     { $sonuc[$no] = 'dolu'; }
        else                                                  { $sonuc[$no] = 'bos'; }
    }
    return $sonuc;
}

// rzOlustur: müşterinin rezervasyon isteğini doğrular ve kaydeder.
// Uygunluk kontrolü sunucuda, dosya kilidi içinde yeniden yapılır.
// Dönen dizi: ['hatalar' => [...]] ya da ['kayit' => rezervasyon]
function rzOlustur($v)
{
    $hatalar = [];
    if (mb_strlen($v['ad'], 'UTF-8') < 3 || mb_strlen($v['ad'], 'UTF-8') > 60 || preg_match("/^\p{L}[\p{L} .'-]*$/u", $v['ad']) !== 1) {
        $hatalar['ad'] = 'Ad soyad 3-60 karakter olmalı ve yalnızca harflerden oluşmalıdır.';
    }
    $telefon = preg_replace('/[\s()-]/', '', $v['telefon']);
    if (preg_match('/^(\+90|0)?5[0-9]{9}$/', $telefon) !== 1) {
        $hatalar['telefon'] = 'Geçerli bir cep telefonu giriniz. Örnek: 0532 123 45 67';
    }
    if (!plakaGecerliMi($v['plaka'])) {
        $hatalar['plaka'] = 'Plaka geçersiz. Örnek: 60 ABC 123';
    }
    $bas = rzZaman($v['giris_tarih'], $v['giris_saat']);
    $bit = rzZaman($v['cikis_tarih'], $v['cikis_saat']);
    if ($bas === null || $bit === null) {
        $hatalar['zaman'] = 'Giriş ve çıkış tarih-saatini eksiksiz giriniz.';
    } elseif ($bas < time() - 300) {
        $hatalar['zaman'] = 'Geçmiş bir zaman için rezervasyon yapılamaz.';
    } elseif ($bit <= $bas) {
        $hatalar['zaman'] = 'Çıkış zamanı giriş zamanından sonra olmalıdır.';
    } elseif ($bit - $bas < 30 * 60) {
        $hatalar['zaman'] = 'Rezervasyon en az 30 dakika olmalıdır.';
    } elseif ($bit - $bas > RZ_EN_UZUN_GUN * 86400) {
        $hatalar['zaman'] = 'Bir rezervasyon en fazla ' . RZ_EN_UZUN_GUN . ' gün olabilir.';
    } elseif ($bas > time() + RZ_ILERI_GUN * 86400) {
        $hatalar['zaman'] = 'En fazla ' . RZ_ILERI_GUN . ' gün sonrası için rezervasyon yapılabilir.';
    }

    $park = rzParkDurum();
    $yer  = (int)$v['yer'];
    if ($park === null) {
        $hatalar['yer'] = 'Otopark sistemi şu an rezervasyon alamıyor. Lütfen daha sonra deneyiniz.';
    } elseif (!isset($park['yerler'][$yer])) {
        $hatalar['yer'] = 'Lütfen haritadan bir park yeri seçiniz.';
    } elseif (!isset($hatalar['plaka']) && in_array($v['plaka'], $park['kara'] ?? [], true)) {
        $hatalar['plaka'] = 'Bu plaka için rezervasyon yapılamıyor. Lütfen otopark yönetimiyle görüşünüz.';
    }
    if (count($hatalar) > 0) {
        return ['hatalar' => $hatalar];
    }

    // Kilit içinde: yer hâlâ uygun mu? Uygunsa aynı anda kaydet.
    $kayit = null;
    rzVeri(function (&$veri) use ($v, $park, $yer, $bas, $bit, $telefon, &$hatalar, &$kayit) {
        $durumlar = rzYerDurumlari($park, $veri['liste'], $bas, $bit);
        if ($durumlar[$yer] !== 'bos') {
            $hatalar['yer'] = yerAdi($yer) . ' seçtiğiniz saatlerde artık uygun değil. Lütfen başka bir yer seçiniz.';
            return;
        }
        $acikSayisi = 0;
        foreach ($veri['liste'] as $r) {
            if ($r['plaka'] === $v['plaka'] && in_array($r['durum'], RZ_ACIK, true)) {
                $acikSayisi++;
                if ($r['giris'] < $bit && $bas < $r['cikis']) {
                    $hatalar['plaka'] = 'Bu plakanın aynı saatlerde başka bir rezervasyonu var (' . $r['no'] . ').';
                    return;
                }
            }
        }
        if ($acikSayisi >= 3) {
            $hatalar['plaka'] = 'Bir plaka için en fazla 3 açık rezervasyon yapılabilir.';
            return;
        }
        $id    = $veri['sonraki']++;
        $kayit = [
            'id' => $id, 'no' => 'RZV-' . date('Ymd') . '-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT),
            'kod' => bin2hex(random_bytes(12)),   // QR kodun bağlı olduğu, tahmin edilemeyen anahtar
            'ad' => $v['ad'], 'telefon' => $telefon, 'plaka' => $v['plaka'], 'yer' => $yer,
            'giris' => $bas, 'cikis' => $bit, 'durum' => 'Bekliyor', 'olusturma' => time(),
        ];
        $veri['liste'][] = $kayit;
    });
    if ($kayit === null) {
        return ['hatalar' => $hatalar ?: ['yer' => 'Rezervasyon kaydedilemedi. Lütfen tekrar deneyiniz.']];
    }
    tgBildir('rezervasyon', "📅 <b>Yeni rezervasyon</b>\nNo: " . $kayit['no'] . "\nAd soyad: " . tgK($kayit['ad']) . "\nPlaka: " . tgK($kayit['plaka']) . "\nPark yeri: " . yerAdi($yer) . "\nGiriş: " . date('d.m.Y H:i', $bas) . "\nÇıkış: " . date('d.m.Y H:i', $bit));
    return ['kayit' => $kayit];
}

// rzZaman: "2026-11-12" + "14:30" → zaman damgası (geçersizse null)
function rzZaman($tarih, $saat)
{
    $t = DateTime::createFromFormat('Y-m-d H:i', $tarih . ' ' . $saat);
    return ($t && $t->format('Y-m-d H:i') === $tarih . ' ' . $saat) ? $t->getTimestamp() : null;
}

function rzDurumSinifi($durum)
{
    $esleme = ['Bekliyor' => 'etiket-turuncu', 'Onaylandı' => 'etiket-yesil', 'Aktif' => 'etiket-yesil', 'Tamamlandı' => 'rozet-gri', 'İptal' => 'etiket-kirmizi', 'Süresi doldu' => 'rozet-gri'];
    return $esleme[$durum] ?? 'rozet-gri';
}
