<?php
/*
 * functions.php
 * Projedeki ortak ayarlar ve kullanıcı tanımlı fonksiyonlar bu dosyadadır.
 * Her sayfa en başta bu dosyayı require_once ile çağırır.
 */

// Oturumu başlat: kayıtlar $_SESSION içinde tutulduğu için her sayfada gerekli.
// Müşteri rezervasyon sayfası (rezervasyon.php) MUSTERI_SAYFASI sabitini tanımlar: orada oturum açılmaz,
// böylece müşteri yönetim panelinin verilerine ve işlemlerine hiçbir şekilde ulaşamaz.
if (!defined('MUSTERI_SAYFASI') && session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Europe/Istanbul');

require_once __DIR__ . '/telegram.php';      // Telegram bot entegrasyonu
require_once __DIR__ . '/rezervasyon.php';   // Online rezervasyon altyapısı

// ---------------------------------------------------------------
// SABİT AYARLAR
// ---------------------------------------------------------------
const UYGULAMA_ADI    = 'ParkTakip';
const TOPLAM_KAPASITE = 60;                          // Otoparktaki toplam park yeri
const ABONE_TURLERI   = ['Aylık', '3 Aylık', '6 Aylık', 'Yıllık'];   // abonelik paketlerinin adları
const DURUMLAR        = ['İçeride', 'Dışarıda'];
const ODEME_YONTEMLERI = ['Nakit', 'Kart', 'Diğer'];
const ROLLER          = ['Yönetici', 'Personel'];
const VERI_SURUMU     = 3;                           // oturumdaki veri yapısı değişince artırılır


// ---------------------------------------------------------------
// 1) GÜVENLİ ÇIKTI
// Kullanıcıdan gelen her metni ekrana basmadan önce bu fonksiyondan geçiriyoruz.
// htmlspecialchars() < > " ' & karakterlerini zararsız HTML karşılıklarına çevirir.
// Böylece biri plaka alanına <script> yazsa bile kod çalışmaz, düz yazı olarak görünür.
// ---------------------------------------------------------------
function e($metin)
{
    return htmlspecialchars((string)$metin, ENT_QUOTES, 'UTF-8');
}


// ---------------------------------------------------------------
// 2) ÖRNEK KAYITLAR
// Uygulama ilk açıldığında listede görünecek 24 örnek abone (18'i içeride).
// Tarihler bugüne göre hesaplanır, böylece demo her gün anlamlı görünür.
// ---------------------------------------------------------------
function ornekKayitlar()
{
    return [
        ['id' => 1, 'plaka' => '60 ABC 123', 'ad_soyad' => 'Ahmet Yılmaz',  'abone_turu' => 'Aylık',   'son_tarih' => date('Y-m-d', strtotime('+18 days')),  'durum' => 'İçeride',  'son_islem' => date('d.m.Y') . ' 08:15'],
        ['id' => 2, 'plaka' => '60 TK 456',  'ad_soyad' => 'Ayşe Demir',    'abone_turu' => 'Yıllık',  'son_tarih' => date('Y-m-d', strtotime('+240 days')), 'durum' => 'Dışarıda', 'son_islem' => date('d.m.Y') . ' 07:40'],
        ['id' => 3, 'plaka' => '34 KL 7890', 'ad_soyad' => 'Mehmet Kaya',   'abone_turu' => '3 Aylık', 'son_tarih' => date('Y-m-d', strtotime('+3 days')),   'durum' => 'İçeride',  'son_islem' => date('d.m.Y') . ' 09:05'],
        ['id' => 4, 'plaka' => '06 BZ 321',  'ad_soyad' => 'Zeynep Arslan', 'abone_turu' => 'Aylık',   'son_tarih' => date('Y-m-d', strtotime('-5 days')),   'durum' => 'Dışarıda', 'son_islem' => date('d.m.Y', strtotime('-6 days')) . ' 18:30'],
        ['id' => 5, 'plaka' => '60 E 1001',  'ad_soyad' => 'Can Öztürk',    'abone_turu' => 'Yıllık',  'son_tarih' => date('Y-m-d', strtotime('+300 days')), 'durum' => 'İçeride',  'son_islem' => date('d.m.Y') . ' 10:20'],
        ['id' => 6, 'plaka' => '58 SV 74',   'ad_soyad' => 'Elif Şahin',    'abone_turu' => '3 Aylık', 'son_tarih' => date('Y-m-d', strtotime('+60 days')),  'durum' => 'Dışarıda', 'son_islem' => date('d.m.Y', strtotime('-1 day')) . ' 17:45'],
        ['id' => 7,  'plaka' => '60 AD 215',  'ad_soyad' => 'Hasan Çelik',    'abone_turu' => 'Aylık',   'son_tarih' => date('Y-m-d', strtotime('+22 days')),  'durum' => 'İçeride',  'son_islem' => date('d.m.Y') . ' 07:55'],
        ['id' => 8,  'plaka' => '60 BC 348',  'ad_soyad' => 'Fatma Koç',      'abone_turu' => 'Yıllık',  'son_tarih' => date('Y-m-d', strtotime('+190 days')), 'durum' => 'İçeride',  'son_islem' => date('d.m.Y') . ' 08:02'],
        ['id' => 9,  'plaka' => '34 DF 1207', 'ad_soyad' => 'Mustafa Aydın',  'abone_turu' => '3 Aylık', 'son_tarih' => date('Y-m-d', strtotime('+45 days')),  'durum' => 'İçeride',  'son_islem' => date('d.m.Y') . ' 08:10'],
        ['id' => 10, 'plaka' => '60 GH 562',  'ad_soyad' => 'Hatice Polat',   'abone_turu' => 'Aylık',   'son_tarih' => date('Y-m-d', strtotime('+12 days')),  'durum' => 'İçeride',  'son_islem' => date('d.m.Y') . ' 08:22'],
        ['id' => 11, 'plaka' => '06 JK 784',  'ad_soyad' => 'İbrahim Şen',    'abone_turu' => 'Yıllık',  'son_tarih' => date('Y-m-d', strtotime('+275 days')), 'durum' => 'İçeride',  'son_islem' => date('d.m.Y') . ' 08:31'],
        ['id' => 12, 'plaka' => '60 LM 903',  'ad_soyad' => 'Emine Yıldız',   'abone_turu' => 'Aylık',   'son_tarih' => date('Y-m-d', strtotime('+6 days')),   'durum' => 'İçeride',  'son_islem' => date('d.m.Y') . ' 08:40'],
        ['id' => 13, 'plaka' => '58 NP 136',  'ad_soyad' => 'Ömer Kurt',      'abone_turu' => '3 Aylık', 'son_tarih' => date('Y-m-d', strtotime('+70 days')),  'durum' => 'İçeride',  'son_islem' => date('d.m.Y') . ' 08:48'],
        ['id' => 14, 'plaka' => '60 RS 471',  'ad_soyad' => 'Merve Acar',     'abone_turu' => 'Yıllık',  'son_tarih' => date('Y-m-d', strtotime('+320 days')), 'durum' => 'İçeride',  'son_islem' => date('d.m.Y') . ' 08:56'],
        ['id' => 15, 'plaka' => '60 TU 628',  'ad_soyad' => 'Yusuf Güneş',    'abone_turu' => 'Aylık',   'son_tarih' => date('Y-m-d', strtotime('+27 days')),  'durum' => 'İçeride',  'son_islem' => date('d.m.Y') . ' 09:12'],
        ['id' => 16, 'plaka' => '34 VY 3054', 'ad_soyad' => 'Sultan Doğan',   'abone_turu' => '3 Aylık', 'son_tarih' => date('Y-m-d', strtotime('+38 days')),  'durum' => 'İçeride',  'son_islem' => date('d.m.Y') . ' 09:20'],
        ['id' => 17, 'plaka' => '60 ZA 719',  'ad_soyad' => 'Ramazan Özkan',  'abone_turu' => 'Aylık',   'son_tarih' => date('Y-m-d', strtotime('+15 days')),  'durum' => 'İçeride',  'son_islem' => date('d.m.Y') . ' 09:33'],
        ['id' => 18, 'plaka' => '66 CE 842',  'ad_soyad' => 'Kübra Erdoğan',  'abone_turu' => 'Yıllık',  'son_tarih' => date('Y-m-d', strtotime('+210 days')), 'durum' => 'İçeride',  'son_islem' => date('d.m.Y') . ' 09:41'],
        ['id' => 19, 'plaka' => '60 FG 295',  'ad_soyad' => 'Osman Bulut',    'abone_turu' => '3 Aylık', 'son_tarih' => date('Y-m-d', strtotime('+52 days')),  'durum' => 'İçeride',  'son_islem' => date('d.m.Y') . ' 09:50'],
        ['id' => 20, 'plaka' => '60 HJ 380',  'ad_soyad' => 'Esra Taş',       'abone_turu' => 'Aylık',   'son_tarih' => date('Y-m-d', strtotime('+9 days')),   'durum' => 'İçeride',  'son_islem' => date('d.m.Y') . ' 10:05'],
        ['id' => 21, 'plaka' => '19 KL 657',  'ad_soyad' => 'Murat Aksoy',    'abone_turu' => 'Yıllık',  'son_tarih' => date('Y-m-d', strtotime('+150 days')), 'durum' => 'İçeride',  'son_islem' => date('d.m.Y') . ' 10:14'],
        ['id' => 22, 'plaka' => '60 MN 164',  'ad_soyad' => 'Selin Karaca',   'abone_turu' => 'Aylık',   'son_tarih' => date('Y-m-d', strtotime('+20 days')),  'durum' => 'Dışarıda', 'son_islem' => date('d.m.Y') . ' 07:25'],
        ['id' => 23, 'plaka' => '60 PR 533',  'ad_soyad' => 'Burak Tekin',    'abone_turu' => '3 Aylık', 'son_tarih' => date('Y-m-d', strtotime('+80 days')),  'durum' => 'Dışarıda', 'son_islem' => date('d.m.Y', strtotime('-1 day')) . ' 18:10'],
        ['id' => 24, 'plaka' => '34 ST 2486', 'ad_soyad' => 'Derya Avcı',     'abone_turu' => 'Yıllık',  'son_tarih' => date('Y-m-d', strtotime('+130 days')), 'durum' => 'Dışarıda', 'son_islem' => date('d.m.Y', strtotime('-2 days')) . ' 16:35'],
    ];
}


// ---------------------------------------------------------------
// 3) KAYITLARI HAZIRLA
// Oturumda kayıt yoksa örnek kayıtları oturuma koyar.
// Sayfa yenilense de kayıtlar $_SESSION'da kaldığı için kaybolmaz.
// ---------------------------------------------------------------
function kayitlariHazirla()
{
    if (($_SESSION['surum'] ?? 0) === VERI_SURUMU) {
        return;
    }
    // Oturum açmış kullanıcı varsa koru, geri kalan her şeyi baştan kur
    $girisYapan = $_SESSION['kullanici'] ?? null;
    $_SESSION   = [];
    $_SESSION['surum'] = VERI_SURUMU;
    if ($girisYapan !== null) {
        $_SESSION['kullanici'] = $girisYapan;
    }

    // Ayarlar: ziyaretçi tarifesi (TL)
    $_SESSION['ayarlar'] = ['saatlik' => 40, 'gunluk' => 250];

    // Abonelik paketleri
    $_SESSION['paketler'] = [
        ['ad' => 'Aylık',   'ay' => 1,  'fiyat' => 1500,  'aciklama' => '1 ay sınırsız giriş-çıkış',   'aktif' => true],
        ['ad' => '3 Aylık', 'ay' => 3,  'fiyat' => 4000,  'aciklama' => '3 ay sınırsız giriş-çıkış',   'aktif' => true],
        ['ad' => '6 Aylık', 'ay' => 6,  'fiyat' => 7500,  'aciklama' => '6 ay sınırsız giriş-çıkış',   'aktif' => true],
        ['ad' => 'Yıllık',  'ay' => 12, 'fiyat' => 14000, 'aciklama' => '12 ay sınırsız giriş-çıkış',  'aktif' => true],
    ];

    // Park yerleri: 1..TOPLAM_KAPASITE. durum: normal / rezerve / kapali. İlk 3 yer engelli park yeridir.
    $_SESSION['yerler'] = [];
    for ($no = 1; $no <= TOPLAM_KAPASITE; $no++) {
        $_SESSION['yerler'][$no] = ['durum' => 'normal', 'engelli' => $no <= 3];
    }

    // Kullanıcılar (şifreler hash olarak saklanır)
    $_SESSION['kullanicilar'] = [
        ['kadi' => 'admin',    'ad' => 'Sistem Yöneticisi', 'rol' => 'Yönetici', 'sifre' => password_hash('admin123', PASSWORD_DEFAULT),    'aktif' => true],
        ['kadi' => 'personel', 'ad' => 'Otopark Görevlisi', 'rol' => 'Personel', 'sifre' => password_hash('personel123', PASSWORD_DEFAULT), 'aktif' => true],
    ];

    $_SESSION['odemeler']          = [];
    $_SESSION['sonraki_odeme_id']  = 1;
    $_SESSION['abonelik_gecmisi']  = [];
    $_SESSION['kara_liste']        = [];
    $_SESSION['loglar']            = [];

    // Aboneler: örnek kayıtlara yeni alanlar eklenir (örneklerde boş bırakılır)
    $_SESSION['araclar'] = [];
    foreach (ornekKayitlar() as $kayit) {
        $_SESSION['araclar'][] = $kayit + ['telefon' => '', 'marka' => '', 'model' => '', 'renk' => '', 'kayit_tarihi' => null, 'pasif' => false];
    }
    $_SESSION['sonraki_id'] = count($_SESSION['araclar']) + 1;

    // Giriş-çıkış geçmişi: içerideki abonelere açık kayıt ve park yeri verilir
    $_SESSION['hareketler']         = [];
    $_SESSION['sonraki_hareket_id'] = 1;
    foreach ($_SESSION['araclar'] as $kayit) {
        $zaman = islemZamani($kayit['son_islem']);
        if ($zaman === 0) {
            $zaman = time();
        }
        if ($kayit['durum'] === 'İçeride') {
            hareketEkle($kayit['plaka'], 'Abone', $kayit['ad_soyad'], $zaman);
        } elseif (date('Y-m-d', $zaman) === date('Y-m-d')) {
            // Bugün çıkış yapmış abone: çıkış saati biliniyor, giriş saati kayıtlı değil
            $id = hareketEkle($kayit['plaka'], 'Abone', $kayit['ad_soyad'], null);
            hareketKapat($id, 0, null, $zaman);
        }
    }
}


// ---------------------------------------------------------------
// 4) PLAKA İŞLEMLERİ
// plakaDuzenle: "60abc123" gibi bir girişi "60 ABC 123" biçimine çevirir.
// plakaGecerliMi: Türk plaka kuralına uyup uymadığını kontrol eder.
//   - İl kodu 01-81, ardından 1-3 harf, ardından 2-4 rakam.
// ---------------------------------------------------------------
const PLAKA_DESENI = '/^(0[1-9]|[1-7][0-9]|8[01])([A-Z]{1,3})([0-9]{2,4})$/';

function plakaDuzenle($plaka)
{
    $temiz = strtoupper(str_replace(' ', '', trim($plaka)));

    if (preg_match(PLAKA_DESENI, $temiz, $parcalar)) {
        // $parcalar[1] = il kodu, [2] = harfler, [3] = rakamlar
        return $parcalar[1] . ' ' . $parcalar[2] . ' ' . $parcalar[3];
    }
    return $temiz; // geçersizse olduğu gibi döner, doğrulamada yakalanır
}

function plakaGecerliMi($plaka)
{
    $temiz = str_replace(' ', '', $plaka);
    return preg_match(PLAKA_DESENI, $temiz) === 1;
}


// ---------------------------------------------------------------
// 5) FORM DOĞRULAMA
// formMetni: POST ile gelen alanı temizleyerek alır. Alan hiç gelmediyse ya da
//   metin değil de dizi olarak gönderildiyse (plaka[]=x) boş metin döndürür.
// kayitDogrula: Formdan gelen verileri kontrol eder, bulunan hataları dizi olarak
//   döndürür. Dizi boşsa veri geçerlidir.
// ---------------------------------------------------------------
function formMetni($alan)
{
    $deger = $_POST[$alan] ?? '';
    return is_string($deger) ? trim($deger) : '';
}

function kayitDogrula($veri, $kayitlar)
{
    $hatalar = [];

    // Plaka
    if ($veri['plaka'] === '') {
        $hatalar['plaka'] = 'Plaka alanı boş bırakılamaz.';
    } elseif (!plakaGecerliMi($veri['plaka'])) {
        $hatalar['plaka'] = 'Plaka geçersiz. Örnek biçim: 60 ABC 123 (il kodu 01-81).';
    } else {
        // Aynı plaka daha önce kaydedilmiş mi?
        foreach ($kayitlar as $kayit) {
            if ($kayit['plaka'] === $veri['plaka']) {
                $hatalar['plaka'] = 'Bu plaka zaten kayıtlı.';
                break;
            }
        }
        if (karaListede($veri['plaka']) !== null) {
            $hatalar['plaka'] = 'Bu plaka kara listede, abone yapılamaz.';
        }
    }

    // Ad Soyad
    if ($veri['ad_soyad'] === '') {
        $hatalar['ad_soyad'] = 'Ad soyad alanı boş bırakılamaz.';
    } elseif (mb_strlen($veri['ad_soyad'], 'UTF-8') < 3) {
        $hatalar['ad_soyad'] = 'Ad soyad en az 3 karakter olmalıdır.';
    } elseif (mb_strlen($veri['ad_soyad'], 'UTF-8') > 60) {
        $hatalar['ad_soyad'] = 'Ad soyad en fazla 60 karakter olabilir.';
    } elseif (preg_match("/^\p{L}[\p{L} .'-]*$/u", $veri['ad_soyad']) !== 1) {
        // \p{L}: Türkçe dahil tüm harfler. Rakam ve < > gibi işaretler kabul edilmez.
        $hatalar['ad_soyad'] = 'Ad soyad yalnızca harflerden oluşmalıdır.';
    }

    // Abone türü: yalnızca listedeki değerler kabul edilir
    $secilenPaket = paketBul($veri['abone_turu']);
    if ($secilenPaket === null) {
        $hatalar['abone_turu'] = 'Lütfen bir abone türü seçiniz.';
    } elseif (!$secilenPaket['aktif']) {
        $hatalar['abone_turu'] = 'Bu paket şu an satışta değil.';
    }

    // Son tarih: boş olmamalı, gerçek bir tarih olmalı, geçmişte olmamalı
    if ($veri['son_tarih'] === '') {
        $hatalar['son_tarih'] = 'Abonelik bitiş tarihi boş bırakılamaz.';
    } else {
        // YYYY-AA-GG biçiminde okunup aynı metne dönmüyorsa tarih geçersizdir (31 Şubat, 2030-01-01xyz)
        $tarih = DateTime::createFromFormat('Y-m-d', $veri['son_tarih']);
        if ($tarih === false || $tarih->format('Y-m-d') !== $veri['son_tarih']) {
            $hatalar['son_tarih'] = 'Geçerli bir tarih giriniz.';
        } elseif ($veri['son_tarih'] < date('Y-m-d')) {
            $hatalar['son_tarih'] = 'Yeni abonelik için bitiş tarihi geçmiş bir tarih olamaz.';
        }
    }

    // Durum
    if (!in_array($veri['durum'], DURUMLAR, true)) {
        $hatalar['durum'] = 'Lütfen aracın durumunu seçiniz.';
    } elseif ($veri['durum'] === 'İçeride' && bosParkYeri($kayitlar) <= 0) {
        $hatalar['durum'] = 'Otopark dolu! Araç "İçeride" olarak eklenemez.';
    }

    return $hatalar;
}


// aracBilgisiDogrula: isteğe bağlı telefon ve araç alanlarını kontrol eder.
function aracBilgisiDogrula($veri)
{
    $hatalar = [];
    if ($veri['telefon'] !== '') {
        $rakamlar = preg_replace('/[\s()-]/', '', $veri['telefon']);
        if (preg_match('/^(\+90|0)?5[0-9]{9}$/', $rakamlar) !== 1) {
            $hatalar['telefon'] = 'Telefon geçersiz. Örnek: 0532 123 45 67';
        }
    }
    foreach (['marka', 'model', 'renk'] as $alan) {
        if (mb_strlen($veri[$alan], 'UTF-8') > 30) {
            $hatalar['arac'] = 'Araç bilgileri en fazla 30 karakter olabilir.';
        }
    }
    return $hatalar;
}


// ---------------------------------------------------------------
// 6) FİLTRELEME
// Boş gelen filtre "hepsi" anlamına gelir.
// ---------------------------------------------------------------
function kayitlariFiltrele($kayitlar, $durum, $aboneTuru)
{
    $sonuc = [];
    foreach ($kayitlar as $kayit) {
        if ($durum !== '' && $kayit['durum'] !== $durum) {
            continue; // durum uymuyorsa bu kaydı atla
        }
        if ($aboneTuru !== '' && $kayit['abone_turu'] !== $aboneTuru) {
            continue;
        }
        $sonuc[] = $kayit;
    }
    return $sonuc;
}


// ---------------------------------------------------------------
// 7) SAYAÇLAR
// ---------------------------------------------------------------
function durumaGoreSay($kayitlar, $durum)
{
    $sayac = 0;
    foreach ($kayitlar as $kayit) {
        if ($kayit['durum'] === $durum) {
            $sayac++;
        }
    }
    return $sayac;
}

// bosParkYeri: araç alınabilecek (normal durumda ve boş) park yeri sayısı.
// Rezerve ve kullanım dışı yerler sayılmaz. ($kayitlar eski çağrılarla uyum için duruyor.)
function bosParkYeri($kayitlar = null)
{
    $dolu    = doluYerler();
    $rezerve = rzSimdiRezerve();   // online rezervasyonla ayrılmış yerler
    $bos     = 0;
    foreach ($_SESSION['yerler'] as $no => $yer) {
        if ($yer['durum'] === 'normal' && !isset($dolu[$no]) && !isset($rezerve[$no])) {
            $bos++;
        }
    }
    return $bos;
}

function suresiDolanSayisi($kayitlar)
{
    $sayac = 0;
    foreach ($kayitlar as $kayit) {
        if (kalanGun($kayit['son_tarih']) < 0) {
            $sayac++;
        }
    }
    return $sayac;
}


// ---------------------------------------------------------------
// 8) ABONELİK SÜRESİ
// kalanGun: bitiş tarihine kaç gün kaldığını verir (geçtiyse eksi sayı).
// abonelikEtiketi: tabloda gösterilecek renkli etiketin metnini ve CSS sınıfını verir.
// ---------------------------------------------------------------
function kalanGun($sonTarih)
{
    $bugun = strtotime(date('Y-m-d'));
    $bitis = strtotime($sonTarih);
    return (int)round(($bitis - $bugun) / 86400);
}

function abonelikEtiketi($sonTarih)
{
    $gun = kalanGun($sonTarih);

    if ($gun < 0) {
        return ['metin' => 'Süresi doldu', 'sinif' => 'etiket-kirmizi'];
    } elseif ($gun <= 7) {
        return ['metin' => $gun . ' gün kaldı', 'sinif' => 'etiket-turuncu'];
    } else {
        return ['metin' => 'Aktif', 'sinif' => 'etiket-yesil'];
    }
}


// ---------------------------------------------------------------
// 9) BİLGİ MESAJLARI (flash mesaj)
// Bir işlemden sonra yönlendirme yapınca mesaj kaybolmasın diye
// mesajı oturuma yazıp bir sonraki sayfada bir kez gösteriyoruz.
// ---------------------------------------------------------------
function mesajKaydet($tur, $metin)
{
    $_SESSION['mesaj'] = ['tur' => $tur, 'metin' => $metin];
}

function mesajAl()
{
    if (isset($_SESSION['mesaj'])) {
        $mesaj = $_SESSION['mesaj'];
        unset($_SESSION['mesaj']); // bir kez gösterilsin
        return $mesaj;
    }
    return null;
}


// ---------------------------------------------------------------
// 10) YÖNLENDİRME
// ---------------------------------------------------------------
function yonlendir($sayfa)
{
    header('Location: ' . $sayfa);
    exit;
}

// ---------------------------------------------------------------
// 11) ARAYÜZ YARDIMCILARI
// Sayfalardaki simgeler, avatar harfleri ve panelde gösterilen
// özetler bu bölümdedir. Hepsi mevcut kayıtlardan hesaplanır.
// ---------------------------------------------------------------

// ikon: satır içi SVG simge döndürür (dış dosya veya kütüphane gerekmez).
function ikon($ad, $boyut = 18)
{
    static $yollar = [
        'otopark'  => '<rect x="3" y="3" width="18" height="18" rx="4"/><path d="M9 17V7h4a3 3 0 0 1 0 6H9"/>',
        'panel'    => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'kullanicilar' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'kullanici' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'arti'     => '<path d="M12 5v14M5 12h14"/>',
        'arac'     => '<path d="M5 17h-.5A1.5 1.5 0 0 1 3 15.5V12l1.8-4.5A2 2 0 0 1 6.7 6h10.6a2 2 0 0 1 1.9 1.5L21 12v3.5a1.5 1.5 0 0 1-1.5 1.5H19"/><path d="M3 12h18M8 17h8"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/>',
        'giris'    => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/>',
        'cikis'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'saat'     => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'uyari'    => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0zM12 9v4M12 17h.01"/>',
        'ara'      => '<circle cx="11" cy="11" r="7.5"/><path d="m21 21-4.3-4.3"/>',
        'gunes'    => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'ay'       => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
        'menu'     => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'daralt'   => '<path d="m11 17-5-5 5-5M18 17l-5-5 5-5"/>',
        'cop'      => '<path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6M10 11v6M14 11v6"/>',
        'sifirla'  => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/>',
        'onay'     => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
        'hata'     => '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/>',
        'bilgi'    => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
        'sirala'   => '<path d="m7 15 5 5 5-5M7 9l5-5 5 5"/>',
        'kutu'     => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.5 5.1 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.5-6.9A2 2 0 0 0 16.7 4H7.3a2 2 0 0 0-1.8 1.1z"/>',
        'filtre'   => '<path d="M22 3H2l8 9.5V19l4 2v-8.5z"/>',
        'etkinlik' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
        'takvim'   => '<rect x="3" y="4" width="18" height="18" rx="3"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'sol'      => '<path d="m15 18-6-6 6-6"/>',
        'sag'      => '<path d="m9 18 6-6-6-6"/>',
        'geri'     => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
        'kaydet'   => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/>',
        'ajanda'   => '<rect x="3" y="4" width="18" height="18" rx="3"/><path d="M16 2v4M8 2v4M3 10h18M9 15l2 2 4-4"/>',
        'kapat'    => '<path d="M18 6 6 18M6 6l12 12"/>',
        'paket'    => '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/>',
        'izgara'   => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'rapor'    => '<path d="M4 20V4M4 20h16M8 16v-5M12 16V8M16 16v-3"/>',
        'yasak'    => '<circle cx="12" cy="12" r="9"/><path d="m5.6 5.6 12.8 12.8"/>',
        'ayar'     => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-2.8 1.2V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-2.8-1.2l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.7 1.7 0 0 0 3.1 14H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.2-2.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.7 1.7 0 0 0 10 3.1V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 2.8 1.2l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0 1.2 2.8h.1a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.4 1z"/>',
        'zil'      => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10.3 21a1.9 1.9 0 0 0 3.4 0"/>',
        'duzenle'  => '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'indir'    => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
        'yazdir'   => '<path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
        'yenile'   => '<path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/>',
        'engelli'  => '<circle cx="12" cy="4" r="2"/><path d="M12 6v7h5l2 6M8 11a6 6 0 1 0 7 8"/>',
        'bariyer'  => '<path d="M4 21V9M4 13h16M8 13l-2 4M13 13l-2 4M18 13l-2 4"/><circle cx="4" cy="7" r="2"/>',
        'para'     => '<rect x="2" y="6" width="20" height="12" rx="2.5"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/>',
    ];
    $yol = $yollar[$ad] ?? '';
    return '<svg class="ikon" width="' . (int)$boyut . '" height="' . (int)$boyut . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $yol . '</svg>';
}

// bas_harfler: "Ayşe Demir" -> "AD" (avatar için)
function bas_harfler($adSoyad)
{
    $kelimeler = preg_split('/\s+/', trim($adSoyad));
    $harf = function ($k) {
        return function_exists('mb_substr') ? mb_strtoupper(mb_substr($k, 0, 1, 'UTF-8'), 'UTF-8') : strtoupper(substr($k, 0, 1));
    };
    $sonuc = $harf($kelimeler[0]);
    if (count($kelimeler) > 1) {
        $sonuc .= $harf($kelimeler[count($kelimeler) - 1]);
    }
    return $sonuc;
}

// islemZamani: "05.11.2026 08:15" metnini zaman damgasına çevirir (okunamazsa 0).
function islemZamani($sonIslem)
{
    $tarih = DateTime::createFromFormat('d.m.Y H:i', (string)$sonIslem);
    return $tarih ? $tarih->getTimestamp() : 0;
}

// bugunHareketleri: giriş-çıkış geçmişinden bugünkü giriş ve çıkış sayılarını verir.
function bugunHareketleri()
{
    $sonuc = ['giris' => 0, 'cikis' => 0];
    $bugun = date('Y-m-d');
    foreach ($_SESSION['hareketler'] as $h) {
        if ($h['giris'] !== null && date('Y-m-d', $h['giris']) === $bugun) {
            $sonuc['giris']++;
        }
        if ($h['cikis'] !== null && date('Y-m-d', $h['cikis']) === $bugun) {
            $sonuc['cikis']++;
        }
    }
    return $sonuc;
}

// saatlikHareket: bugünkü giriş ve çıkışları saat saat gruplar.
// Dönen dizi: [saat => ['giris' => n, 'cikis' => n]] (yalnızca hareket olan saatler)
function saatlikHareket()
{
    $saatler = [];
    $bugun = date('Y-m-d');
    foreach ($_SESSION['hareketler'] as $h) {
        foreach (['giris', 'cikis'] as $tur) {
            if ($h[$tur] === null || date('Y-m-d', $h[$tur]) !== $bugun) {
                continue;
            }
            $saat = (int)date('G', $h[$tur]);
            if (!isset($saatler[$saat])) {
                $saatler[$saat] = ['giris' => 0, 'cikis' => 0];
            }
            $saatler[$saat][$tur]++;
        }
    }
    ksort($saatler);
    return $saatler;
}

// sonHareketler: son işlemi en yeni olan kayıtları döndürür.
function sonHareketler($kayitlar, $adet = 6)
{
    usort($kayitlar, function ($a, $b) {
        return islemZamani($b['son_islem']) <=> islemZamani($a['son_islem']);
    });
    return array_slice($kayitlar, 0, $adet);
}

// dolulukDurumu: doluluk yüzdesine göre etiket ve renk sınıfı verir.
function dolulukDurumu($yuzde)
{
    if ($yuzde >= 100) {
        return ['metin' => 'Otopark dolu', 'sinif' => 'rozet-kirmizi'];
    } elseif ($yuzde >= 85) {
        return ['metin' => 'Çok yoğun', 'sinif' => 'rozet-turuncu'];
    } elseif ($yuzde >= 60) {
        return ['metin' => 'Yoğun', 'sinif' => 'rozet-mavi'];
    }
    return ['metin' => 'Müsait', 'sinif' => 'rozet-yesil'];
}


// ---------------------------------------------------------------
// 12) GİRİŞ-ÇIKIŞ GEÇMİŞİ, PARK YERİ ATAMA, ZİYARETÇİ ARAÇLAR VE ÜCRET
// Her giriş bir "hareket" kaydı açar, çıkışta aynı kayıt kapatılır.
// Hareket: id, plaka, tip (Abone / Ziyaretçi), ad, yer (park yeri no), giris, cikis,
//          ucret (TL), odeme (yöntem), islem_no (ödeme alındıysa)
// ---------------------------------------------------------------

// doluYerler: [park yeri no => içerideki hareket]
function doluYerler()
{
    $dolu = [];
    foreach ($_SESSION['hareketler'] ?? [] as $h) {
        if ($h['cikis'] === null && $h['yer'] !== null) {
            $dolu[$h['yer']] = $h;
        }
    }
    return $dolu;
}

// yerAta: uygun ilk boş park yerini verir. Önce normal yerler, onlar dolduysa engelli yerleri.
function yerAta()
{
    $dolu    = doluYerler();
    $rezerve = rzSimdiRezerve();   // rezervasyonlu yer başka araca verilmez
    foreach ([false, true] as $engelli) {
        foreach ($_SESSION['yerler'] as $no => $yer) {
            if ($yer['durum'] === 'normal' && $yer['engelli'] === $engelli && !isset($dolu[$no]) && !isset($rezerve[$no])) {
                return $no;
            }
        }
    }
    return null;
}

function yerAdi($no)
{
    return $no === null ? '—' : 'P' . str_pad((string)$no, 2, '0', STR_PAD_LEFT);
}

function hareketEkle($plaka, $tip, $ad, $girisZamani, $yer = null)
{
    $id = $_SESSION['sonraki_hareket_id']++;
    $_SESSION['hareketler'][] = [
        'id' => $id, 'plaka' => $plaka, 'tip' => $tip, 'ad' => $ad,
        'yer' => $girisZamani === null ? null : ($yer ?? yerAta()),   // rezervasyonlu yer ya da otomatik atama
        'giris' => $girisZamani, 'cikis' => null,
        'ucret' => 0, 'odeme' => null, 'islem_no' => null,
    ];
    return $id;
}

function hareketKapat($id, $ucret, $odeme, $cikisZamani = null)
{
    foreach ($_SESSION['hareketler'] as $sira => $h) {
        if ($h['id'] === $id) {
            $_SESSION['hareketler'][$sira]['cikis'] = $cikisZamani ?? time();   // park yeri de böylece boşalır
            $_SESSION['hareketler'][$sira]['ucret'] = $ucret;
            $_SESSION['hareketler'][$sira]['odeme'] = $odeme;
            if ($ucret > 0) {
                $_SESSION['hareketler'][$sira]['islem_no'] = odemeEkle($h['plaka'], $h['ad'], 'Ziyaretçi', 'Park ücreti (' . sureYazi(sureDakika($h['giris'])) . ')', $ucret, $odeme);
            }
            return $_SESSION['hareketler'][$sira];
        }
    }
    return null;
}

// Plakanın içeride (çıkışı yapılmamış) bir hareketi varsa onu döndürür.
function acikHareket($plaka)
{
    foreach ($_SESSION['hareketler'] as $h) {
        if ($h['plaka'] === $plaka && $h['cikis'] === null) {
            return $h;
        }
    }
    return null;
}

function ziyaretciIcerideSayisi()
{
    $sayac = 0;
    foreach ($_SESSION['hareketler'] ?? [] as $h) {
        if ($h['tip'] === 'Ziyaretçi' && $h['cikis'] === null) {
            $sayac++;
        }
    }
    return $sayac;
}

// Otoparktaki toplam araç: içerideki aboneler + içerideki ziyaretçiler
function iceridekiToplam()
{
    return durumaGoreSay($_SESSION['araclar'], 'İçeride') + ziyaretciIcerideSayisi();
}

function aboneBul($plaka)
{
    foreach ($_SESSION['araclar'] as $sira => $kayit) {
        if ($kayit['plaka'] === $plaka) {
            return $sira;
        }
    }
    return null;
}

function aboneSirasi($id)
{
    foreach ($_SESSION['araclar'] as $sira => $kayit) {
        if ($kayit['id'] === $id) {
            return $sira;
        }
    }
    return null;
}

function ayar($ad)
{
    return $_SESSION['ayarlar'][$ad];
}

// ucretHesapla: ziyaretçi aracın kaldığı dakikaya göre ücreti verir.
// Başlanan her saat ücretlidir; 24 saatlik dilimde günlük tavan ücret aşılmaz.
function ucretHesapla($dakika)
{
    $dakika  = max(1, (int)$dakika);
    $tamGun  = intdiv($dakika, 1440);
    $kalan   = $dakika % 1440;
    $saat    = (int)ceil($kalan / 60);
    return $tamGun * ayar('gunluk') + min($saat * ayar('saatlik'), ayar('gunluk'));
}

function sureDakika($giris, $cikis = null)
{
    if ($giris === null) {
        return null;
    }
    return max(1, (int)ceil((($cikis ?? time()) - $giris) / 60));
}

// sureYazi: 135 -> "2 sa 15 dk"
function sureYazi($dakika)
{
    if ($dakika === null) {
        return '—';
    }
    $gun  = intdiv($dakika, 1440);
    $saat = intdiv($dakika % 1440, 60);
    $dk   = $dakika % 60;
    $parca = [];
    if ($gun > 0)  { $parca[] = $gun . ' gün'; }
    if ($saat > 0) { $parca[] = $saat . ' sa'; }
    if ($dk > 0 || count($parca) === 0) { $parca[] = $dk . ' dk'; }
    return implode(' ', $parca);
}

function paraYaz($tutar)
{
    return number_format((float)$tutar, 2, ',', '.') . ' ₺';
}

function zamanYaz($zaman)
{
    if ($zaman === null) {
        return '—';
    }
    return date('Y-m-d', $zaman) === date('Y-m-d') ? 'Bugün ' . date('H:i', $zaman) : date('d.m.Y H:i', $zaman);
}

// aracGirisi: plakaya göre giriş yapar. Abone ise abonelik kuralları uygulanır,
// kayıtlı değilse ziyaretçi araç olarak içeri alınır.
// Dönen dizi: ['tamam' => bool, 'mesaj' => string]
function aracGirisi($plaka)
{
    if (!plakaGecerliMi($plaka)) {
        return ['tamam' => false, 'mesaj' => 'Plaka geçersiz. Örnek biçim: 60 ABC 123 (il kodu 01-81).'];
    }
    $yasak = karaListede($plaka);
    if ($yasak !== null) {
        logKaydet('Kara listedeki ' . $plaka . ' giriş yapmak istedi, engellendi');
        tgBildir('durum', "⛔ <b>Olağan dışı işlem</b>\nKara listedeki " . tgK($plaka) . " giriş yapmak istedi, engellendi.\nSebep: " . tgK($yasak['sebep']));
        return ['tamam' => false, 'mesaj' => 'UYARI: ' . $plaka . ' kara listede, giriş yapamaz. Sebep: ' . $yasak['sebep'] . '.'];
    }
    $sira = aboneBul($plaka);
    if ($sira !== null && !empty($_SESSION['araclar'][$sira]['pasif'])) {
        return ['tamam' => false, 'mesaj' => $plaka . ' giriş yapamaz: abone kaydı pasif.'];
    }

    // Aynı araç ikinci kez giriş yapamaz
    if (acikHareket($plaka) !== null || ($sira !== null && $_SESSION['araclar'][$sira]['durum'] === 'İçeride')) {
        return ['tamam' => false, 'mesaj' => $plaka . ' zaten otoparkta. Aynı araç ikinci kez giriş yapamaz.'];
    }
    if ($sira !== null && kalanGun($_SESSION['araclar'][$sira]['son_tarih']) < 0) {
        return ['tamam' => false, 'mesaj' => $plaka . ' giriş yapamaz: abonelik süresi dolmuş.'];
    }
    // Online rezervasyonu varsa kendi yerine alınır (yer zaten ayrılmıştır)
    $rez    = rzPlakaIcin($plaka);
    $rezYer = null;
    if ($rez !== null && !isset(doluYerler()[$rez['yer']]) && ($_SESSION['yerler'][$rez['yer']]['durum'] ?? '') === 'normal') {
        $rezYer = $rez['yer'];
    }
    if ($rezYer === null && bosParkYeri() <= 0) {
        return ['tamam' => false, 'mesaj' => 'Otopark dolu! ' . $plaka . ' giriş yapamaz.'];
    }
    if ($rez !== null) {
        rzDurumDegistir($rez['id'], 'Aktif');
    }

    if ($sira !== null) {
        $_SESSION['araclar'][$sira]['durum']     = 'İçeride';
        $_SESSION['araclar'][$sira]['son_islem'] = date('d.m.Y H:i');
        hareketEkle($plaka, 'Abone', $_SESSION['araclar'][$sira]['ad_soyad'], time(), $rezYer);
        $yer = acikHareket($plaka)['yer'];
        logKaydet($plaka . ' giriş yaptı (abone, ' . yerAdi($yer) . ')');
        tgGirisBildir($plaka, $_SESSION['araclar'][$sira]['ad_soyad'], 'Abone', $yer);
        return ['tamam' => true, 'mesaj' => $plaka . ' otoparka giriş yaptı (abone). Park yeri: ' . yerAdi($yer) . '.'];
    }
    hareketEkle($plaka, 'Ziyaretçi', $rez !== null ? $rez['ad'] . ' (rezervasyon ' . $rez['no'] . ')' : 'Ziyaretçi araç', time(), $rezYer);
    $yer = acikHareket($plaka)['yer'];
    logKaydet($plaka . ' giriş yaptı (ziyaretçi, ' . yerAdi($yer) . ')');
    tgGirisBildir($plaka, 'Ziyaretçi araç', 'Ziyaretçi', $yer);
    return ['tamam' => true, 'mesaj' => $plaka . ' ziyaretçi araç olarak giriş yaptı. Park yeri: ' . yerAdi($yer) . ', saatlik ücret: ' . paraYaz(ayar('saatlik')) . '.'];
}

// aracCikisi: plakanın çıkışını yapar. Ziyaretçi araçta ödeme yöntemi seçilmeden çıkış onaylanmaz.
// Yönetici "Ücretsiz" seçerek ödemesiz çıkış verebilir (yetkili istisnası).
function aracCikisi($plaka, $odemeYontemi = '')
{
    $hareket = acikHareket($plaka);
    $sira    = aboneBul($plaka);

    if ($hareket === null && ($sira === null || $_SESSION['araclar'][$sira]['durum'] !== 'İçeride')) {
        return ['tamam' => false, 'mesaj' => $plaka . ' otoparkta görünmüyor.'];
    }
    // Ziyaretçi ödeme seçmeden çıkamaz; rezervasyon ancak çıkış gerçekten yapılacaksa kapatılır
    $cikisYapilacak = !($hareket !== null && $hareket['tip'] === 'Ziyaretçi' && !in_array($odemeYontemi, ODEME_YONTEMLERI, true) && !($odemeYontemi === 'Ücretsiz' && yoneticiMi()));
    if ($cikisYapilacak) {
        foreach (rzVeri()['liste'] as $r) {
            if ($r['plaka'] === $plaka && $r['durum'] === 'Aktif') {
                rzDurumDegistir($r['id'], 'Tamamlandı');   // araç çıktı: rezervasyon tamamlandı, yer yeniden uygun
            }
        }
    }

    if ($hareket !== null && $hareket['tip'] === 'Ziyaretçi') {
        $dakika = sureDakika($hareket['giris']);
        if ($odemeYontemi === 'Ücretsiz' && yoneticiMi()) {
            hareketKapat($hareket['id'], 0, 'Ücretsiz (yönetici)');
            logKaydet($plaka . ' ödemesiz çıkış yaptı (yönetici istisnası)');
            tgCikisBildir($plaka, $hareket['giris'], 0);
            tgBildir('durum', "⚠️ <b>Olağan dışı işlem</b>\n" . tgK($plaka) . ' yönetici onayıyla ödemesiz çıkış yaptı.');
            return ['tamam' => true, 'mesaj' => $plaka . ' yönetici onayıyla ücretsiz çıkış yaptı. Süre: ' . sureYazi($dakika) . '.'];
        }
        if (!in_array($odemeYontemi, ODEME_YONTEMLERI, true)) {
            return ['tamam' => false, 'mesaj' => 'Ödeme alınmadan çıkış onaylanamaz. Lütfen ödeme yöntemini seçiniz.'];
        }
        $ucret  = ucretHesapla($dakika);
        $kapali = hareketKapat($hareket['id'], $ucret, $odemeYontemi);
        logKaydet($plaka . ' çıkış yaptı, ' . paraYaz($ucret) . ' tahsil edildi');
        tgCikisBildir($plaka, $hareket['giris'], $ucret);
        return ['tamam' => true, 'mesaj' => $plaka . ' çıkış yaptı. Süre: ' . sureYazi($dakika) . ', ücret: ' . paraYaz($ucret) . ' (' . $odemeYontemi . ', ' . $kapali['islem_no'] . ').'];
    }

    // Abone araç: ücret alınmaz
    if ($hareket !== null) {
        hareketKapat($hareket['id'], 0, null);
    }
    if ($sira !== null) {
        $_SESSION['araclar'][$sira]['durum']     = 'Dışarıda';
        $_SESSION['araclar'][$sira]['son_islem'] = date('d.m.Y H:i');
    }
    logKaydet($plaka . ' çıkış yaptı (abone)');
    tgCikisBildir($plaka, $hareket['giris'] ?? null, 0);
    return ['tamam' => true, 'mesaj' => $plaka . ' otoparktan çıkış yaptı.'];
}


// Giriş ve çıkış için Telegram bildirimleri (kuyruğa yazılır, işlemi bekletmez)
function tgGirisBildir($plaka, $sahip, $tip, $yer)
{
    $_SESSION['yeni_yer'] = ['no' => $yer, 'zaman' => time()];   // park alanında "yerine sürme" animasyonu için
    tgBildir('giris', "🚗 <b>Araç girişi</b>\nPlaka: " . tgK($plaka) . "\nAraç sahibi: " . tgK($sahip) . "\nGiriş: " . date('d.m.Y H:i') . "\nTür: $tip\nPark yeri: " . yerAdi($yer));
    if (bosParkYeri() === 0) {
        tgBildir('durum', "🅿️ <b>Otopark doldu</b>\nBoş park yeri kalmadı.");
    }
}

function tgCikisBildir($plaka, $giris, $ucret)
{
    tgBildir('cikis', "🚘 <b>Araç çıkışı</b>\nPlaka: " . tgK($plaka) . "\nGiriş: " . ($giris === null ? '—' : date('d.m.Y H:i', $giris)) . "\nÇıkış: " . date('d.m.Y H:i') . "\nPark süresi: " . sureYazi(sureDakika($giris)) . "\nÖdenen ücret: " . ($ucret > 0 ? paraYaz($ucret) : 'Yok'));
    if (count(doluYerler()) === 0) {
        tgBildir('durum', "🅿️ <b>Otopark tamamen boşaldı</b>\nİçeride araç kalmadı.");
    }
}


// ---------------------------------------------------------------
// 13) ÖDEMELER VE GELİR
// Ödeme: id, islem_no, zaman, plaka, ad, tur (Abonelik / Ziyaretçi), aciklama, tutar, indirim, yontem, kullanici
// ---------------------------------------------------------------
function odemeEkle($plaka, $ad, $tur, $aciklama, $tutar, $yontem, $indirim = 0)
{
    $id      = $_SESSION['sonraki_odeme_id']++;
    $islemNo = 'ODM-' . date('Ymd') . '-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);
    $_SESSION['odemeler'][] = [
        'id' => $id, 'islem_no' => $islemNo, 'zaman' => time(), 'plaka' => $plaka, 'ad' => $ad,
        'tur' => $tur, 'aciklama' => $aciklama, 'tutar' => round((float)$tutar, 2), 'indirim' => round((float)$indirim, 2),
        'yontem' => $yontem, 'kullanici' => $_SESSION['kullanici']['kadi'] ?? '-',
    ];
    tgBildir('odeme', "💰 <b>Ödeme alındı</b>\nİşlem no: $islemNo\nTutar: " . paraYaz($tutar) . "\nYöntem: " . tgK($yontem) . "\nTür: " . tgK($tur) . ' · ' . tgK($plaka) . "\nTarih: " . date('d.m.Y H:i'));
    return $islemNo;
}

// gelirToplami: verilen gün ('Y-m-d') ya da ay ('Y-m') içinde alınan ödemelerin toplamı
function gelirToplami($donem)
{
    $bicim  = strlen($donem) === 7 ? 'Y-m' : 'Y-m-d';
    $toplam = 0;
    foreach ($_SESSION['odemeler'] as $o) {
        if (date($bicim, $o['zaman']) === $donem) {
            $toplam += $o['tutar'];
        }
    }
    return $toplam;
}


// ---------------------------------------------------------------
// 14) ABONELİK PAKETLERİ VE YENİLEME
// ---------------------------------------------------------------
function paketBul($ad)
{
    foreach ($_SESSION['paketler'] as $paket) {
        if ($paket['ad'] === $ad) {
            return $paket;
        }
    }
    return null;
}

// abonelikKaydet: abonelik geçmişine satır ekler ve ödemeyi alır. İşlem numarasını döndürür.
function abonelikKaydet($abone, $paket, $baslangic, $bitis, $yontem, $indirimYuzde = 0)
{
    $indirim = round($paket['fiyat'] * $indirimYuzde / 100, 2);
    $tutar   = $paket['fiyat'] - $indirim;
    $islemNo = odemeEkle($abone['plaka'], $abone['ad_soyad'], 'Abonelik', $paket['ad'] . ' abonelik (' . date('d.m.Y', strtotime($baslangic)) . ' - ' . date('d.m.Y', strtotime($bitis)) . ')', $tutar, $yontem, $indirim);
    $_SESSION['abonelik_gecmisi'][] = [
        'abone_id' => $abone['id'], 'paket' => $paket['ad'], 'baslangic' => $baslangic, 'bitis' => $bitis,
        'tutar' => $tutar, 'islem_no' => $islemNo, 'zaman' => time(),
    ];
    return $islemNo;
}

// abonelikYenile: süresi bitmediyse mevcut bitiş tarihinin üstüne, bittiyse bugünden itibaren uzatır.
function abonelikYenile($sira, $paketAd, $yontem, $indirimYuzde)
{
    $paket = paketBul($paketAd);
    if ($paket === null || !$paket['aktif']) {
        return ['tamam' => false, 'mesaj' => 'Lütfen satışta olan bir paket seçiniz.'];
    }
    if (!in_array($yontem, ODEME_YONTEMLERI, true)) {
        return ['tamam' => false, 'mesaj' => 'Lütfen ödeme yöntemini seçiniz.'];
    }
    if ($indirimYuzde < 0 || $indirimYuzde > 100) {
        return ['tamam' => false, 'mesaj' => 'İndirim oranı 0 ile 100 arasında olmalıdır.'];
    }
    $abone     = $_SESSION['araclar'][$sira];
    $baslangic = max(date('Y-m-d'), $abone['son_tarih']);
    $bitis     = date('Y-m-d', strtotime($baslangic . ' +' . $paket['ay'] . ' months'));
    $islemNo   = abonelikKaydet($abone, $paket, $baslangic, $bitis, $yontem, $indirimYuzde);

    $_SESSION['araclar'][$sira]['abone_turu'] = $paket['ad'];
    $_SESSION['araclar'][$sira]['son_tarih']  = $bitis;
    logKaydet($abone['plaka'] . ' aboneliği yenilendi (' . $paket['ad'] . ', ' . $islemNo . ')');
    tgBildir('abone', "🔄 <b>Abonelik yenilendi</b>\nAd soyad: " . tgK($abone['ad_soyad']) . "\nPlaka: " . tgK($abone['plaka']) . "\nPaket: " . tgK($paket['ad']) . "\nBaşlangıç: " . date('d.m.Y', strtotime($baslangic)) . "\nBitiş: " . date('d.m.Y', strtotime($bitis)));
    return ['tamam' => true, 'mesaj' => $abone['plaka'] . ' aboneliği ' . date('d.m.Y', strtotime($bitis)) . ' tarihine kadar uzatıldı. İşlem no: ' . $islemNo . '.'];
}


// ---------------------------------------------------------------
// 15) KARA LİSTE
// ---------------------------------------------------------------
function karaListede($plaka)
{
    foreach ($_SESSION['kara_liste'] as $k) {
        if ($k['plaka'] === $plaka) {
            return $k;
        }
    }
    return null;
}


// ---------------------------------------------------------------
// 16) KULLANICILAR, YETKİ VE İŞLEM GEÇMİŞİ
// ---------------------------------------------------------------
function yoneticiMi()
{
    return ($_SESSION['kullanici']['rol'] ?? '') === 'Yönetici';
}

// yoneticiGerek: sayfa ya da işlem yalnızca yöneticiye açıksa çağrılır.
function yoneticiGerek()
{
    if (!yoneticiMi()) {
        logKaydet('Yetkisiz erişim denemesi: ' . basename($_SERVER['SCRIPT_NAME'] ?? ''));
        mesajKaydet('hata', 'Bu işlem için yönetici yetkisi gerekir.');
        yonlendir('index.php');
    }
}

function logKaydet($islem)
{
    $_SESSION['loglar'][] = ['zaman' => time(), 'kullanici' => $_SESSION['kullanici']['kadi'] ?? '-', 'islem' => $islem];
    if (count($_SESSION['loglar']) > 500) {
        array_shift($_SESSION['loglar']);   // en eski kaydı at
    }
}

// uyarilar: üst şeritteki bildirim zilinde gösterilecek sistem uyarıları
function uyarilar()
{
    $liste = [];
    $dolan = 0; $yaklasan = 0;
    foreach ($_SESSION['araclar'] as $k) {
        $gun = kalanGun($k['son_tarih']);
        if ($gun < 0) { $dolan++; } elseif ($gun <= 7) { $yaklasan++; }
    }
    if ($dolan > 0)    { $liste[] = ['tur' => 'kirmizi', 'metin' => $dolan . ' abonenin süresi dolmuş', 'adres' => 'raporlar.php#suresi-dolan']; }
    if ($yaklasan > 0) { $liste[] = ['tur' => 'turuncu', 'metin' => $yaklasan . ' abonelik 7 gün içinde bitiyor', 'adres' => 'raporlar.php#suresi-dolan']; }
    $bos = bosParkYeri();
    if ($bos === 0)    { $liste[] = ['tur' => 'kirmizi', 'metin' => 'Otopark dolu', 'adres' => 'park.php']; }
    elseif ($bos <= 5) { $liste[] = ['tur' => 'turuncu', 'metin' => 'Yalnızca ' . $bos . ' boş park yeri kaldı', 'adres' => 'park.php']; }
    return $liste;
}


// Her sayfa açıldığında kayıtların hazır olduğundan emin ol (müşteri sayfasında oturum yoktur)
if (!defined('MUSTERI_SAYFASI')) {
    kayitlariHazirla();
}

// Oturum açılmamışsa giriş sayfasına gönder (giriş sayfasının kendisi hariç)
if (!defined('MUSTERI_SAYFASI') && PHP_SAPI !== 'cli' && !isset($_SESSION['kullanici']) && basename($_SERVER['SCRIPT_NAME']) !== 'giris.php') {
    yonlendir('giris.php');
}

// Yönetim panelinde her isteğin sonunda park durumunu müşteri sayfası için ortak dosyaya yaz
if (!defined('MUSTERI_SAYFASI') && isset($_SESSION['kullanici'])) {
    register_shutdown_function('rzParkDurumYaz');
}
