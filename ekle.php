<?php
/*
 * ekle.php — Yeni abone ekleme
 * Form POST ile bu sayfanın kendisine gönderilir.
 * Hata varsa form, girilen değerler korunarak tekrar gösterilir.
 * Hata yoksa kayıt $_SESSION'a eklenir ve ana sayfaya dönülür.
 */
require_once 'includes/functions.php';

$hatalar = [];

// Formun ilk açılışındaki varsayılan değerler
$veri = [
    'plaka'      => '',
    'ad_soyad'   => '',
    'abone_turu' => '',
    'son_tarih'  => date('Y-m-d', strtotime('+1 month')),
    'durum'      => 'Dışarıda',
    'telefon'    => '',
    'marka'      => '',
    'model'      => '',
    'renk'       => '',
    'odeme'      => 'Nakit',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) Formdan gelen verileri al ve baştaki/sondaki boşlukları temizle
    $veri['plaka']      = plakaDuzenle(formMetni('plaka'));
    $veri['ad_soyad']   = formMetni('ad_soyad');
    $veri['abone_turu'] = formMetni('abone_turu');
    $veri['son_tarih']  = formMetni('son_tarih');
    $veri['durum']      = formMetni('durum');
    $veri['telefon']    = formMetni('telefon');
    $veri['marka']      = formMetni('marka');
    $veri['model']      = formMetni('model');
    $veri['renk']       = formMetni('renk');
    $veri['odeme']      = formMetni('odeme');

    // 2) PHP tarafında doğrula
    $hatalar = kayitDogrula($veri, $_SESSION['araclar']);
    // Plaka şu an ziyaretçi olarak içerideyse önce çıkışı yapılmalı
    if (!isset($hatalar['plaka']) && acikHareket($veri['plaka']) !== null) {
        $hatalar['plaka'] = 'Bu plaka şu an ziyaretçi araç olarak otoparkta. Önce çıkışını yapınız.';
    }

    $hatalar += aracBilgisiDogrula($veri);
    if (!in_array($veri['odeme'], ODEME_YONTEMLERI, true)) {
        $hatalar['odeme'] = 'Lütfen ödeme yöntemini seçiniz.';
    }

    // 3) Hata yoksa kaydet
    if (count($hatalar) === 0) {
        $yeniKayit = [
            'id'         => $_SESSION['sonraki_id'],
            'plaka'      => $veri['plaka'],
            'ad_soyad'   => $veri['ad_soyad'],
            'abone_turu' => $veri['abone_turu'],
            'son_tarih'  => $veri['son_tarih'],
            'durum'      => $veri['durum'],
            'son_islem'  => date('d.m.Y H:i'),
            'telefon'    => $veri['telefon'],
            'marka'      => $veri['marka'],
            'model'      => $veri['model'],
            'renk'       => $veri['renk'],
            'kayit_tarihi' => date('Y-m-d'),
            'pasif'      => false,
        ];

        $_SESSION['araclar'][] = $yeniKayit;   // listeye ekle
        $_SESSION['sonraki_id']++;            // sonraki kayıt için id'yi artır
        if ($veri['durum'] === 'İçeride') {
            hareketEkle($veri['plaka'], 'Abone', $veri['ad_soyad'], time());   // giriş geçmişine yaz
            $_SESSION['yeni_yer'] = ['no' => acikHareket($veri['plaka'])['yer'], 'zaman' => time()];   // park alanı animasyonu
        }

        // Abonelik ücretini al ve abonelik geçmişine yaz
        $islemNo = abonelikKaydet($yeniKayit, paketBul($veri['abone_turu']), date('Y-m-d'), $veri['son_tarih'], $veri['odeme']);
        logKaydet($veri['plaka'] . ' abone olarak eklendi (' . $islemNo . ')');
        tgBildir('abone', "👤 <b>Yeni abone</b>\nAd soyad: " . tgK($veri['ad_soyad']) . "\nPlaka: " . tgK($veri['plaka']) . "\nPaket: " . tgK($veri['abone_turu']) . "\nBaşlangıç: " . date('d.m.Y') . "\nBitiş: " . date('d.m.Y', strtotime($veri['son_tarih'])));

        mesajKaydet('basari', $veri['plaka'] . ' plakalı araç abone olarak eklendi. Ödeme işlem no: ' . $islemNo . '.');
        yonlendir('index.php');
    }
}

$bosYerSayisi = bosParkYeri($_SESSION['araclar']);

$sayfaBasligi = 'Yeni Abone';
$aktifSayfa   = 'ekle';
require 'includes/header.php';
?>

<div class="sayfa-baslik">
    <div>
        <h1>Yeni Abone Ekle</h1>
        <p>Yıldızlı (*) alanların doldurulması zorunludur.</p>
    </div>
    <div class="sayfa-aksiyon">
        <a href="index.php#aboneler" class="buton buton-ikincil"><?= ikon('geri', 16) ?>Listeye dön</a>
    </div>
</div>

<?php if (count($hatalar) > 0): ?>
    <div class="bildirim bildirim-hata" role="alert">
        <?= ikon('hata', 20) ?>
        <span class="bildirim-metin">Kayıt eklenemedi. Lütfen aşağıdaki <?= count($hatalar) ?> hatayı düzeltiniz.</span>
    </div>
<?php endif; ?>

<div class="form-duzen">

    <!-- novalidate: tarayıcı kontrolünü kapatıyoruz ki doğrulamayı PHP yapsın -->
    <form class="kart form-kutu" method="post" action="ekle.php" novalidate>

        <div class="form-bolum">
            <div class="form-bolum-baslik">Araç ve sahip bilgileri</div>

            <div class="alan <?= isset($hatalar['plaka']) ? 'alan-hatali' : '' ?>">
                <label for="plaka">Plaka *</label>
                <input type="text" id="plaka" name="plaka" maxlength="12" placeholder="60 ABC 123" class="plaka-girdi"
                       autocomplete="off" value="<?= e($veri['plaka']) ?>">
                <?php if (isset($hatalar['plaka'])): ?>
                    <span class="hata-metni"><?= e($hatalar['plaka']) ?></span>
                <?php else: ?>
                    <span class="ipucu">Boşluksuz yazabilirsiniz; otomatik düzenlenir (örn. 60abc123 → 60 ABC 123).</span>
                <?php endif; ?>
            </div>

            <div class="alan <?= isset($hatalar['ad_soyad']) ? 'alan-hatali' : '' ?>">
                <label for="ad_soyad">Araç Sahibi (Ad Soyad) *</label>
                <input type="text" id="ad_soyad" name="ad_soyad" maxlength="60" placeholder="Örn. Ali Veli"
                       value="<?= e($veri['ad_soyad']) ?>">
                <?php if (isset($hatalar['ad_soyad'])): ?>
                    <span class="hata-metni"><?= e($hatalar['ad_soyad']) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-bolum">
            <div class="form-bolum-baslik">Abonelik</div>

            <div class="alan-ikili">
                <div class="alan <?= isset($hatalar['abone_turu']) ? 'alan-hatali' : '' ?>">
                    <label for="abone_turu">Abone Türü *</label>
                    <select id="abone_turu" name="abone_turu">
                        <option value="">Seçiniz</option>
                        <?php foreach ($_SESSION['paketler'] as $paket): if (!$paket['aktif']) { continue; } ?>
                            <option value="<?= e($paket['ad']) ?>" <?= $veri['abone_turu'] === $paket['ad'] ? 'selected' : '' ?>><?= e($paket['ad']) ?> — <?= e(paraYaz($paket['fiyat'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($hatalar['abone_turu'])): ?>
                        <span class="hata-metni"><?= e($hatalar['abone_turu']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="alan <?= isset($hatalar['son_tarih']) ? 'alan-hatali' : '' ?>">
                    <label for="son_tarih">Abonelik Son Tarihi *</label>
                    <input type="date" id="son_tarih" name="son_tarih" value="<?= e($veri['son_tarih']) ?>">
                    <?php if (isset($hatalar['son_tarih'])): ?>
                        <span class="hata-metni"><?= e($hatalar['son_tarih']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="alan <?= isset($hatalar['durum']) ? 'alan-hatali' : '' ?>">
                <span class="etiket-baslik">Şu anki durumu *</span>
                <div class="radyolar">
                    <?php foreach (DURUMLAR as $durum): ?>
                        <label class="radyo">
                            <input type="radio" name="durum" value="<?= e($durum) ?>" <?= $veri['durum'] === $durum ? 'checked' : '' ?>>
                            <span class="radyo-isaret"></span>
                            <span class="radyo-yazi">
                                <?= e($durum) ?>
                                <small><?= $durum === 'İçeride' ? 'Araç şu an otoparkta' : 'Araç otopark dışında' ?></small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php if (isset($hatalar['durum'])): ?>
                    <span class="hata-metni"><?= e($hatalar['durum']) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-bolum">
            <div class="form-bolum-baslik">İletişim ve araç (isteğe bağlı)</div>
            <div class="alan-ikili">
                <div class="alan <?= isset($hatalar['telefon']) ? 'alan-hatali' : '' ?>">
                    <label for="telefon">Telefon</label>
                    <input type="text" id="telefon" name="telefon" maxlength="16" placeholder="05xx xxx xx xx" value="<?= e($veri['telefon']) ?>">
                    <?php if (isset($hatalar['telefon'])): ?><span class="hata-metni"><?= e($hatalar['telefon']) ?></span><?php endif; ?>
                </div>
                <div class="alan">
                    <label for="renk">Araç rengi</label>
                    <input type="text" id="renk" name="renk" maxlength="20" placeholder="Örn. Beyaz" value="<?= e($veri['renk']) ?>">
                </div>
            </div>
            <div class="alan-ikili">
                <div class="alan">
                    <label for="marka">Araç markası</label>
                    <input type="text" id="marka" name="marka" maxlength="30" value="<?= e($veri['marka']) ?>">
                </div>
                <div class="alan">
                    <label for="model">Araç modeli</label>
                    <input type="text" id="model" name="model" maxlength="30" value="<?= e($veri['model']) ?>">
                </div>
            </div>
            <?php if (isset($hatalar['arac'])): ?><span class="hata-metni"><?= e($hatalar['arac']) ?></span><?php endif; ?>
        </div>

        <div class="form-bolum">
            <div class="form-bolum-baslik">Ödeme</div>
            <div class="alan <?= isset($hatalar['odeme']) ? 'alan-hatali' : '' ?>">
                <label for="odeme">Ödeme yöntemi *</label>
                <select id="odeme" name="odeme">
                    <?php foreach (ODEME_YONTEMLERI as $y): ?>
                        <option value="<?= e($y) ?>" <?= $veri['odeme'] === $y ? 'selected' : '' ?>><?= e($y) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($hatalar['odeme'])): ?>
                    <span class="hata-metni"><?= e($hatalar['odeme']) ?></span>
                <?php else: ?>
                    <span class="ipucu">Seçilen paketin ücreti kayıt sırasında tahsil edilir ve ödemelere işlenir.</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-butonlar">
            <a href="index.php" class="buton buton-ikincil">Vazgeç</a>
            <button type="submit" class="buton"><?= ikon('kaydet', 16) ?>Kaydet</button>
        </div>
    </form>

    <!-- Yan bilgi: kurallar ve anlık doluluk -->
    <aside>
        <div class="kart yan-kart">
            <div class="kart-ic">
                <div class="kart-ust"><div><h2>Kayıt kuralları</h2></div></div>
                <ul class="kural-listesi">
                    <li><?= ikon('onay', 16) ?><span><strong>Plaka:</strong> il kodu 01–81, ardından 1–3 harf ve 2–4 rakam. Aynı plaka iki kez eklenemez.</span></li>
                    <li><?= ikon('onay', 16) ?><span><strong>Ad soyad:</strong> 3–60 karakter, yalnızca harf, boşluk, nokta, tire ve kesme işareti.</span></li>
                    <li><?= ikon('onay', 16) ?><span><strong>Son tarih:</strong> geçmiş bir tarih olamaz; bugün kabul edilir.</span></li>
                    <li><?= ikon('onay', 16) ?><span><strong>Durum:</strong> otopark doluyken araç “İçeride” olarak eklenemez.</span></li>
                </ul>
            </div>
        </div>
        <div class="kart yan-kart">
            <div class="kart-ic">
                <div class="kart-ust"><div><h2>Boş park yeri</h2><p>Şu anki durum</p></div>
                    <span class="rozet <?= $bosYerSayisi > 0 ? 'rozet-yesil' : 'rozet-kirmizi' ?>"><?= $bosYerSayisi > 0 ? 'Yer var' : 'Dolu' ?></span>
                </div>
                <div class="kpi-sayi"><?= $bosYerSayisi ?><small> / <?= TOPLAM_KAPASITE ?></small></div>
            </div>
        </div>
    </aside>
</div>

<?php require 'includes/footer.php'; ?>
