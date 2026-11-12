# ParkTakip — Otopark Abone ve Giriş-Çıkış Takip Sistemi

**Ders:** İnternet Programcılığı I (PHP)
**Öğrenci:** Muhammed Ali Aktaş — Öğrenci No: `254309022`

## 1. Projenin Amacı

ParkTakip, bir otoparkın abone araçlarını kaydeden ve bu araçların otoparka giriş-çıkışını takip eden PHP tabanlı bir web uygulamasıdır. Görevli; yeni abone ekleyebilir, aracın girişini/çıkışını işleyebilir, aboneliği biten araçları görebilir ve otoparktaki **anlık boş park yeri** sayısını takip edebilir.

## 2. Özellikler

| Zorunlu özellik | Projedeki karşılığı | Dosya |
|---|---|---|
| Ana sayfa | Uygulama adı, açıklama, menü, sayaçlar ve liste | `index.php` |
| Kayıt listeleme (en az 4 alan) | Plaka, ad soyad, abone türü, son tarih, durum, son işlem — `foreach` ile | `index.php` |
| Yeni kayıt ekleme (POST) | Form `method="post"` ile kendine gönderilir, kayıt `$_SESSION`'a eklenir | `ekle.php` |
| Veri doğrulama | Boş alan, plaka biçimi, aynı plaka, ad uzunluğu ve karakterleri, geçerli/gelecek tarih, otopark doluluğu | `includes/functions.php` → `kayitDogrula()` |
| Kayıt silme (POST) | Her satırda "Sil" butonu, onay penceresi, id ile silme | `sil.php` |
| Filtreleme (GET) | Durum (İçeride/Dışarıda) ve abone türüne göre | `index.php`, `kayitlariFiltrele()` |
| Özet bilgiler | Toplam abone, içeride, dışarıda, süresi dolan, **anlık boş park yeri** | `index.php` |
| Güvenli çıktı | Tüm kullanıcı verisi `e()` fonksiyonu ile (`htmlspecialchars`) basılır | `includes/functions.php` |

**Ek özellikler**

- **Giriş / Çıkış işlemi:** Aracın durumu tek tıkla değişir, işlem saati kaydedilir (`durum.php`).
- **Giriş kuralları:** Aboneliği dolmuş araç ve otopark doluyken araç içeri alınamaz.
- **Abonelik etiketi:** Aktif (yeşil), son 7 gün (turuncu), süresi dolmuş (kırmızı).
- **Plaka düzenleme:** `60abc123` yazılsa bile `60 ABC 123` olarak kaydedilir.
- Mobil ekranlara uyumlu arayüz.

## 3. Klasör Yapısı

```
otopark/
├── index.php            Ana sayfa: sayaçlar, filtre (GET), abone listesi
├── ekle.php             Yeni abone formu ve kaydetme (POST)
├── sil.php              Abone silme (POST)
├── durum.php            Giriş / çıkış işlemi (POST)
├── sifirla.php          (kaldırıldı; yalnızca ana sayfaya yönlendirir)
├── includes/
│   ├── functions.php    Ayarlar (kapasite, abone türleri) ve tüm fonksiyonlar
│   ├── header.php       Ortak üst kısım: menü ve bilgi mesajı
│   └── footer.php       Ortak alt kısım
├── assets/
│   └── style.css        Tasarım
└── README.md
```

**Verilerin tutulması:** Kayıtlar `$_SESSION['araclar']` dizisinde saklanır. Uygulama ilk açıldığında `kayitlariHazirla()` fonksiyonu 24 örnek aboneyi oturuma yükler. Otopark kapasitesi 60 araçtır; örnek verilerde 18 araç içeride olduğu için doluluk %30, boş yer 42 görünür. Sayfa yenilense de kayıtlar korunur; tarayıcı kapatılınca oturum sona erer.

## 4. Kullanıcı Tanımlı Fonksiyonlar (`includes/functions.php`)

| Fonksiyon | Görevi |
|---|---|
| `e($metin)` | `htmlspecialchars` ile güvenli çıktı |
| `ornekKayitlar()` / `kayitlariHazirla()` | Örnek verileri oluşturur, oturuma yükler |
| `plakaDuzenle()` / `plakaGecerliMi()` | Plakayı biçimlendirir ve kurala uygunluğunu kontrol eder |
| `formMetni($alan)` | POST alanını temizleyerek alır; alan yoksa veya dizi geldiyse boş metin döndürür |
| `kayitDogrula($veri, $kayitlar)` | Form verisini doğrular, hata dizisi döndürür |
| `kayitlariFiltrele($kayitlar, $durum, $tur)` | Listeyi filtreler |
| `durumaGoreSay()` / `bosParkYeri()` / `suresiDolanSayisi()` | Sayaçlar |
| `kalanGun()` / `abonelikEtiketi()` | Abonelik süresini hesaplar, etiket belirler |
| `mesajKaydet()` / `mesajAl()` | İşlem sonrası tek seferlik bilgi mesajı |
| `yonlendir($sayfa)` | Başka sayfaya yönlendirir |

## 5. XAMPP Üzerinde Çalıştırma

1. XAMPP'ı kurun ve **XAMPP Control Panel**'den **Apache**'yi başlatın (MySQL gerekmez).
2. `otopark` klasörünü `C:\xampp\htdocs\` içine kopyalayın.
3. Tarayıcıda `http://localhost/otopark/` adresini açın.
4. Başlangıç verilerine dönmek için tarayıcıyı kapatıp yeniden açın (veriler oturumda tutulur).

Gereksinim: PHP 7.4 veya üzeri (XAMPP ile birlikte gelir).

## 6. Test Senaryoları

| Senaryo | Beklenen sonuç | Durum |
|---|---|---|
| Form boş gönderilir | Her alanın altında hata mesajı, kayıt eklenmez | ✔ |
| Plaka `99 XX 1` girilir | "Plaka geçersiz" uyarısı | ✔ |
| Geçmiş bir son tarih girilir | "Bitiş tarihi geçmiş olamaz" uyarısı | ✔ |
| Kayıtlı plaka tekrar eklenir | "Bu plaka zaten kayıtlı" uyarısı | ✔ |
| Ad alanına `<script>alert(1)</script>` yazılır | "Yalnızca harflerden oluşmalıdır" uyarısı; kod çalışmaz, formda düz metin olarak kalır | ✔ |
| Geçerli kayıt eklenir | Listeye eklenir, sayaçlar güncellenir | ✔ |
| "İçeride" filtresi | Yalnızca içerideki araçlar listelenir | ✔ |
| Adres çubuğuna `?durum=abc` yazılır | Geçersiz filtre yok sayılır, tümü listelenir | ✔ |
| Aboneliği bitmiş araca "Giriş Yap" | "Abonelik süresi dolmuş" hatası | ✔ |
| Kayıt silinir | Listeden kalkar, toplam sayı azalır | ✔ |
| `sil.php?id=3` adresi doğrudan açılır | Hiçbir şey silinmez (yalnızca POST kabul edilir) | ✔ |

## 7. Bilinen Eksikler

- Veriler `$_SESSION`'da tutulduğu için tarayıcı kapatılınca kaybolur (sonraki aşamada MySQL + PDO'ya aktarılacak).
- Kayıt güncelleme (düzenleme) henüz yok.
- Giriş-çıkış geçmişi tutulmuyor; yalnızca son işlem saati görünüyor.
- Kullanıcı girişi / yetkilendirme yok.

## 8. Yapay Zekâ Kullanım Açıklaması

**Kullanılan araç:** Claude (Anthropic)

**Kullanım amaçları:**
- Proje yapısının ve dosyaların planlanması
- PHP kodlarının ve CSS tasarımının oluşturulması
- Doğrulama kurallarının ve test senaryolarının belirlenmesi
- Kodların satır satır açıklanması

**Örnek istem (prompt):**
> "İnternet Programcılığı dersi için PHP ile Otopark Abone ve Giriş-Çıkış Takibi projesi hazırla. Alanlar: plaka, abone türü (aylık), son tarih; filtre: içeride/dışarıda; sayaç: anlık boş park yeri. Veriler $_SESSION'da tutulsun, ekleme ve silme POST ile, filtre GET ile yapılsın, htmlspecialchars kullanılsın."

**Kendi katkım:**
<!-- Bu bölümü kendi yaptıklarına göre doldur. Örnek: -->
- `__________ (ör. otopark kapasitesini ve abone türlerini belirledim)`
- `__________ (ör. XAMPP'ta kurulumu yapıp tüm senaryoları kendim test ettim)`
- `__________ (ör. ... özelliğini ekledim / ... hatasını düzelttim)`

## 9. 2. Hafta — Form İşlemleri ve Veri Ekleme

**Kontrol tarihi:** 4 Kasım 2026

### Yapılan çalışmalar

| # | Çalışma | Projedeki karşılığı |
|---|---|---|
| 1 | Yeni kayıt ekleme formu | `ekle.php` (menüde **+ Yeni Abone**) |
| 2 | Form alanları | Plaka, ad soyad, abone türü, abonelik son tarihi, durum |
| 3 | GET ve POST | **POST:** kayıt ekleme, silme, giriş/çıkış (veri değiştirir, adres çubuğunda görünmez). **GET:** `index.php` filtresi (veri değiştirmez, adres çubuğunda görünür) |
| 4 | PHP ile veri alma | `$_POST` → `formMetni()`, `$_GET` → `index.php` |
| 5 | Zorunlu alan kontrolü | `kayitDogrula()`; dizi olarak gönderilen alanlar da boş sayılır |
| 6 | Hata mesajları | Üstte hata sayısı, her alanın altında kendi mesajı; yazılan değerler silinmez |
| 7 | Yeni kaydın eklenmesi | `$_SESSION['araclar'][] = $yeniKayit` |
| 8 | Yenilemede korunma | Kayıtlar `$_SESSION`'da; kayıttan sonra yönlendirme yapıldığı için yenileme aynı kaydı tekrar eklemez |

### Bu hafta eklenen doğrulamalar

- Ad soyad en fazla 60 karakter olabilir (önceden yalnızca tarayıcıda sınırlıydı).
- Ad soyad yalnızca harflerden oluşabilir; Türkçe karakter, boşluk, nokta, tire ve kesme işareti kabul edilir.
- `2030-01-01xyz` gibi sonu bozuk tarihler reddedilir.
- `plaka[]=x` gibi dizi olarak gönderilen alanlar PHP hatasına yol açmaz.

### Başarılı giriş örnekleri

| Girilen | Sonuç |
|---|---|
| `61abc123`, Ali Veli, Aylık, gelecek tarih, Dışarıda | `61 ABC 123` olarak kaydedilir, başarı mesajı çıkar |
| `34 AB 1234`, durum İçeride | Kaydedilir, boş park yeri sayısı azalır |
| Son tarih bugün | Kaydedilir |
| Ad `Şükrü Öztürk-Çelik` | Kaydedilir |

### Başarısız giriş örnekleri

| Girilen | Hata mesajı |
|---|---|
| Boş plaka | Plaka alanı boş bırakılamaz. |
| `99 XX 1` | Plaka geçersiz. Örnek biçim: 60 ABC 123 (il kodu 01-81). |
| `60abc123` (kayıtlı) | Bu plaka zaten kayıtlı. |
| Boş ad | Ad soyad alanı boş bırakılamaz. |
| `Al` | Ad soyad en az 3 karakter olmalıdır. |
| 61 karakterlik ad | Ad soyad en fazla 60 karakter olabilir. |
| `12345` | Ad soyad yalnızca harflerden oluşmalıdır. |
| Abone türü seçilmedi | Lütfen bir abone türü seçiniz. |
| Boş tarih | Abonelik bitiş tarihi boş bırakılamaz. |
| `2020-01-01` | Yeni abonelik için bitiş tarihi geçmiş bir tarih olamaz. |
| `2030-02-31` | Geçerli bir tarih giriniz. |
| Durum seçilmedi | Lütfen aracın durumunu seçiniz. |
| Otopark doluyken İçeride | Otopark dolu! Araç "İçeride" olarak eklenemez. |

## 10. Arayüz Yenilemesi (5 Kasım 2026)

Uygulamanın **tüm arayüzü** yeni bir tasarım sistemiyle yenilendi. PHP iş mantığı (doğrulama, oturumda veri tutma, POST/GET kullanımı, dosya adları, form alan adları) **değiştirilmedi**.

### Yeni tasarım

- Koyu lacivert / kömür tonlarında, ince mavi vurgulu, **açık ve koyu tema** (üst şeritteki düğmeyle değişir, seçim tarayıcıda hatırlanır; ilk açılışta sistem temasına uyar).
- **Daraltılabilir yan menü** (masaüstü) ve mobilde açılır menü; aktif sayfa vurgusu; anlık doluluk kartı.
- **Genel Bakış paneli:** doluluk göstergesi (kapasite / dolu / boş / yüzde), park alanı ızgarası, sayaçlar (toplam abone, aktif oturum, dışarıda, bugünkü giriş-çıkış, süresi dolan), bugünkü saatlik hareket grafiği, son hareketler, yaklaşan bitişler, abone türü dağılımı.
- **Tablo:** sıralanabilir sütunlar, anlık arama (Türkçe karakter duyarsız, `/` kısayolu), sayfalama (10 kayıt), durum rozetleri, boş durum ekranları. GET filtresi aynen korundu.
- **Form:** bölümlü yerleşim, alan altı hata mesajları, kayıt kuralları paneli, kaydederken yükleniyor durumu.
- Silme işlemleri için özel **onay penceresi**.

### Değişen / eklenen dosyalar

| Dosya | Değişiklik |
|---|---|
| `assets/style.css` | Yeni tasarım sistemi (yeniden yazıldı) |
| `assets/app.js` | **Yeni:** tema, menü, tablo arama/sıralama/sayfalama, onay penceresi |
| `includes/header.php`, `includes/footer.php` | Yan menü ve üst şerit yerleşimi |
| `includes/functions.php` | Mevcut fonksiyonlara dokunulmadı; arayüz yardımcıları eklendi (`ikon`, `bas_harfler`, `islemZamani`, `bugunHareketleri`, `saatlikHareket`, `sonHareketler`, `dolulukDurumu`) |
| `index.php`, `ekle.php` | Yeni arayüz işaretlemesi |
| `yedek_eski_tasarim/` | Eski tasarımın yedeği |

### Notlar

- Sistem yalnızca her aracın **son işlemini** tuttuğu için "bugünkü hareket" ve saatlik grafik bu bilgiden hesaplanır; geçmiş hareketler henüz yoktur.
- Park yeri numarası, ödeme/gelir, müşteri, kullanıcı ve ayarlar modülleri projede **bulunmadığı** için bu ekranlar eklenmedi.

## Genişletilmiş Sürüm — Yeni Modüller

Uygulama oturum açılarak kullanılır. Deneme hesapları: yönetici `admin` / `admin123`, personel `personel` / `personel123`. Şifreler `password_hash` ile saklanır.

| Modül | Sayfa | Ne yapar |
|---|---|---|
| Kullanıcı girişi | `giris.php`, `cikis.php` | Oturum açma/kapama, hatalı girişlerin kaydı |
| Araç Giriş-Çıkış | `giris-cikis.php`, `hareket.php` | Plakayla giriş, ziyaretçi araç, süre ve ücret hesaplama, ödeme alarak çıkış, geçmiş, bariyer simülasyonu |
| Park Alanları | `park.php` | Numaralı 60 yer, otomatik yer atama, boş/dolu/rezerve/kullanım dışı, engelli yerleri |
| Abone kartı | `abone.php` | Bilgi düzenleme, abonelik yenileme (indirimli), pasife alma, ödeme ve giriş-çıkış geçmişi |
| Abonelik Paketleri | `paketler.php` | Aylık, 3 Aylık, 6 Aylık, Yıllık paket fiyatı, açıklaması, satış durumu |
| Ödemeler | `odemeler.php` | İşlem numaralı ödeme geçmişi, tarih/tür filtresi, günlük ve aylık gelir |
| Raporlar | `raporlar.php` | Günlük giriş-çıkış ve gelir, en sık giren araçlar, süresi dolanlar; CSV (Excel) indirme ve yazdırma/PDF |
| Kara Liste | `kara-liste.php` | Yasaklı plakalar; giriş denemesi engellenir ve kaydedilir |
| Kullanıcı Yönetimi | `kullanicilar.php` | Kullanıcı ekleme, pasife alma, işlem geçmişi (yalnızca yönetici) |
| Ayarlar | `ayarlar.php` | Ziyaretçi saatlik ücreti ve günlük tavan ücret (yalnızca yönetici) |

**Yetkiler:** Personel giriş-çıkış, abone ekleme/düzenleme/yenileme, ödeme ve rapor görüntüleme ile kara listeye ekleme yapabilir. Paket, park yeri durumu, kullanıcı, ayar değişikliği, abone silme, kara listeden kaldırma ve ödemesiz çıkış yalnızca yöneticidedir.

**Veri:** Tüm kayıtlar (`araclar`, `hareketler`, `odemeler`, `paketler`, `yerler`, `kara_liste`, `kullanicilar`, `loglar`, `ayarlar`) `$_SESSION` içinde tutulur; tarayıcı kapanınca başlangıç durumuna döner.

## Tanıtım Videosu

Sistemi baştan sona (giriş-çıkış, abone yönetimi, online rezervasyon ve Telegram botu) anlatan 100 saniyelik tanıtım videosu: [`tanitim_videosu/ParkTakip_Akilli_Otopark_Tanitim.mp4`](tanitim_videosu/ParkTakip_Akilli_Otopark_Tanitim.mp4)

## Online Rezervasyon Modülü

Müşteriler `rezervasyon.php` sayfasından giriş yapmadan park yeri ayırabilir; yönetim panelindeki oturuma erişemezler.

| Dosya | Görevi |
|---|---|
| `rezervasyon.php` | Müşteri sayfası: gün/saat seçimi, 3 boyutlu park haritası, form, rezervasyon bileti ve QR kod |
| `rezervasyonlar.php` | Yönetim paneli: rezervasyon listesi, filtreler (tümü, bugün, bekleyen, onaylanan, aktif, iptal), onayla / iptal et |
| `includes/rezervasyon.php` | Doğrulama, çakışma kontrolü, dosya kilidi (`flock`), süre dolumu |
| `includes/arac-svg.php` | Park alanındaki 3 boyutlu araç çizimleri (panel ve müşteri sayfası ortak kullanır) |

- Müşteri farklı cihazdan geldiği için oturumu göremez; bu yüzden rezervasyonlar ve park durumu `veri/rezervasyonlar.php` ve `veri/park-durum.php` dosyalarında tutulur (`veri/` klasörü tarayıcıdan kapalıdır).
- Aynı yer, çakışan saatlerde ikinci kez verilmez. Kontrol sunucuda, dosya kilidi içinde yapılır.
- Bugün fiziksel olarak dolu olan yer, ileri bir tarih için ayrılabilir; aynı gün için ayrılamaz.
- Rezervasyon başlamadan 2 saat önce yer yönetim panelinde "rezerve" görünür ve başka araca verilmez.

## GitHub ve Telegram Kurulumu

1. Projeyi indirdikten sonra `includes/gizli-ayar.ornek.php` dosyasını `includes/gizli-ayar.php` olarak kopyalayın.
2. Telegram kullanmak istiyorsanız bot token'ı ve chat ID'yi bu dosyaya yazın. Boş bırakılırsa uygulama Telegram olmadan çalışır.
3. `gizli-ayar.php` ve `veri/` içindeki çalışma dosyaları `.gitignore` ile repoya alınmaz.
