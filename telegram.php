<?php
/*
 * telegram.php — Telegram ayarları (yalnızca yönetici)
 * Bağlantı durumu, test mesajı, bildirim türleri, token yenileme ve son gönderilen mesajlar.
 */
require_once 'includes/functions.php';
yoneticiGerek();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $islem = formMetni('islem');

    if ($islem === 'dogrula') {
        // Token geçerli mi? Telegram'a getMe ile sorulur.
        $sonuc = tgIstek('getMe');
        if (!empty($sonuc['ok'])) {
            $bot = '@' . $sonuc['result']['username'];
            tgDurum(function (&$d) use ($bot) { $d['bot'] = $bot; $d['son_hata'] = ''; });
            mesajKaydet('basari', 'Bağlantı başarılı. Bot: ' . $bot);
        } else {
            $hata = (string)($sonuc['description'] ?? 'Bilinmeyen hata');
            tgDurum(function (&$d) use ($hata) { $d['bot'] = ''; $d['son_hata'] = $hata; });
            mesajKaydet('hata', 'Bağlantı kurulamadı: ' . $hata . '. Token geçersizse BotFather\'dan yeni token alıp aşağıya giriniz.');
        }
    } elseif ($islem === 'test') {
        $sonuc = tgMesajGonder('✅ <b>' . UYGULAMA_ADI . "</b> test mesajı\n" . date('d.m.Y H:i:s') . "\nGönderen: " . tgK($_SESSION['kullanici']['ad']), 'test');
        logKaydet('Telegram test mesajı gönderildi (' . ($sonuc['tamam'] ? 'başarılı' : 'başarısız') . ')');
        mesajKaydet($sonuc['tamam'] ? 'basari' : 'hata', $sonuc['tamam'] ? 'Test mesajı Telegram\'a gönderildi.' : 'Test mesajı gönderilemedi: ' . $sonuc['hata']);
    } elseif ($islem === 'tercih') {
        $acik    = isset($_POST['acik']);
        $secilen = isset($_POST['turler']) && is_array($_POST['turler']) ? $_POST['turler'] : [];
        tgDurum(function (&$d) use ($acik, $secilen) {
            $d['acik'] = $acik;
            foreach (array_keys(TG_TURLER) as $tur) {
                $d['turler'][$tur] = in_array($tur, $secilen, true);
            }
        });
        logKaydet('Telegram bildirim tercihleri güncellendi');
        mesajKaydet('basari', 'Bildirim tercihleri kaydedildi.');
    } elseif ($islem === 'ayar') {
        $token  = isset($_POST['token']) && is_string($_POST['token']) ? trim($_POST['token']) : '';
        $chatId = formMetni('chat_id');
        $eski   = tgAyar();
        if ($token === '' && $eski !== null) {
            $token = $eski['token'];   // boş bırakılırsa mevcut token korunur
        }
        if (preg_match('/^[0-9]{6,12}:[A-Za-z0-9_-]{30,50}$/', $token) !== 1) {
            mesajKaydet('hata', 'Token biçimi geçersiz. BotFather\'ın verdiği token 123456789:AA... biçimindedir.');
        } elseif (preg_match('/^-?[0-9]{5,15}$/', $chatId) !== 1) {
            mesajKaydet('hata', 'Chat ID yalnızca rakamlardan oluşmalıdır.');
        } elseif (!tgAyarKaydet($token, $chatId)) {
            mesajKaydet('hata', 'Ayar dosyası yazılamadı (includes klasörünün yazma izni yok).');
        } else {
            tgDurum(function (&$d) { $d['bot'] = ''; $d['offset'] = 0; });
            logKaydet('Telegram bağlantı bilgileri güncellendi');   // token kayda yazılmaz
            mesajKaydet('basari', 'Bağlantı bilgileri kaydedildi. "Bağlantıyı doğrula" ile kontrol ediniz.');
        }
    }
    yonlendir('telegram.php');
}

$ayar  = tgAyar();
$durum = tgDurum();

$sayfaBasligi = 'Telegram Ayarları';
$aktifSayfa   = 'telegram';
require 'includes/header.php';
?>

<div class="sayfa-baslik">
    <div>
        <h1>Telegram Ayarları</h1>
        <p>Uygulama tarayıcıda açıkken giriş, çıkış, ödeme ve uyarılar yönetici Telegram hesabına gönderilir; komutlarla otopark sorgulanır.</p>
    </div>
</div>

<section class="kpi-izgara">
    <div class="kart kpi">
        <div class="kpi-ust"><span>Bağlantı</span><span class="kpi-ikon <?= $durum['bot'] !== '' && $durum['son_hata'] === '' ? 'yesil' : 'kirmizi' ?>"><?= ikon('etkinlik', 17) ?></span></div>
        <div class="kpi-sayi" style="font-size: 24px"><?= $ayar === null ? 'Ayarlanmadı' : ($durum['bot'] !== '' && $durum['son_hata'] === '' ? 'Bağlı' : ($durum['son_hata'] !== '' ? 'Hata' : 'Doğrulanmadı')) ?></div>
        <div class="kpi-alt"><?= $durum['son_hata'] !== '' ? e($durum['son_hata']) : 'Son doğrulamaya göre' ?></div>
    </div>
    <div class="kart kpi">
        <div class="kpi-ust"><span>Bot</span><span class="kpi-ikon"><?= ikon('kullanici', 17) ?></span></div>
        <div class="kpi-sayi" style="font-size: 24px"><?= $durum['bot'] !== '' ? e($durum['bot']) : '—' ?></div>
        <div class="kpi-alt">Token: <?= $ayar === null ? 'tanımlı değil' : e(tgTokenMaskeli()) ?></div>
    </div>
    <div class="kart kpi">
        <div class="kpi-ust"><span>Yönetici Chat ID</span><span class="kpi-ikon gri"><?= ikon('kullanicilar', 17) ?></span></div>
        <div class="kpi-sayi" style="font-size: 24px"><?= $ayar === null ? '—' : e($ayar['chat_id']) ?></div>
        <div class="kpi-alt">Yalnızca bu hesap komut verebilir</div>
    </div>
    <div class="kart kpi">
        <div class="kpi-ust"><span>Bildirimler</span><span class="kpi-ikon <?= $durum['acik'] ? 'yesil' : 'gri' ?>"><?= ikon('zil', 17) ?></span></div>
        <div class="kpi-sayi" style="font-size: 24px"><?= $durum['acik'] ? 'Açık' : 'Kapalı' ?></div>
        <div class="kpi-alt"><?= count($durum['kuyruk']) ?> mesaj kuyrukta</div>
    </div>
</section>

<section class="panel-izgara">
    <div class="kart kol-4">
        <div class="kart-ic">
            <div class="kart-ust"><div><h2>Bağlantı Testi</h2><p>Gerçek Telegram API çağrısı yapılır</p></div></div>
            <div class="tg-butonlar">
                <form method="post" action="telegram.php"><input type="hidden" name="islem" value="dogrula"><button type="submit" class="buton buton-ikincil" <?= $ayar === null ? 'disabled' : '' ?>><?= ikon('onay', 16) ?>Bağlantıyı doğrula</button></form>
                <form method="post" action="telegram.php"><input type="hidden" name="islem" value="test"><button type="submit" class="buton" <?= $ayar === null ? 'disabled' : '' ?>><?= ikon('giris', 16) ?>Test mesajı gönder</button></form>
            </div>
            <form method="post" action="telegram.php" novalidate autocomplete="off" style="margin-top: 20px; padding-top: 18px; border-top: 1px solid var(--kenar)">
                <input type="hidden" name="islem" value="ayar">
                <div class="alan"><label for="token">Bot token</label><input type="password" id="token" name="token" maxlength="60" placeholder="<?= $ayar === null ? '123456789:AA...' : 'Değiştirmek için yeni token yazın' ?>" autocomplete="new-password"><span class="ipucu">Token kaynak kodunda değil, <code>includes/gizli-ayar.php</code> dosyasında saklanır ve ekranda gösterilmez.</span></div>
                <div class="alan"><label for="chat_id">Yönetici Chat ID</label><input type="text" id="chat_id" name="chat_id" maxlength="15" inputmode="numeric" value="<?= e($ayar['chat_id'] ?? '') ?>"></div>
                <div class="form-butonlar"><button type="submit" class="buton buton-ikincil buton-kucuk"><?= ikon('kaydet', 15) ?>Bağlantı bilgilerini kaydet</button></div>
            </form>
        </div>
    </div>

    <form class="kart kol-4" method="post" action="telegram.php">
        <div class="kart-ic">
            <div class="kart-ust"><div><h2>Bildirim Türleri</h2><p>Hangi olaylar gönderilsin</p></div></div>
            <input type="hidden" name="islem" value="tercih">
            <label class="onay-kutu"><input type="checkbox" name="acik" <?= $durum['acik'] ? 'checked' : '' ?>> <strong>Bildirimler açık</strong></label>
            <div class="tg-turler">
            <?php foreach (TG_TURLER as $tur => $ad): ?>
                <label class="onay-kutu"><input type="checkbox" name="turler[]" value="<?= e($tur) ?>" <?= !empty($durum['turler'][$tur]) ? 'checked' : '' ?>> <?= e($ad) ?></label>
            <?php endforeach; ?>
            </div>
            <div class="form-butonlar"><button type="submit" class="buton"><?= ikon('kaydet', 16) ?>Kaydet</button></div>
        </div>
    </form>

    <div class="kart kol-4">
        <div class="kart-ic">
            <div class="kart-ust"><div><h2>Komutlar</h2><p>Telegram'dan bota yazılır</p></div></div>
            <ul class="tg-komutlar">
                <li><code>/durum</code><span>Anlık doluluk</span></li>
                <li><code>/iceride</code><span>İçerideki araçlar</span></li>
                <li><code>/arac PLAKA</code><span>Araç bilgisi</span></li>
                <li><code>/aboneler</code><span>Aktif abone sayısı</span></li>
                <li><code>/abonelik PLAKA</code><span>Abonelik durumu</span></li>
                <li><code>/gelir</code><span>Bugünkü gelir</span></li>
                <li><code>/aylikgelir</code><span>Bu ayki gelir</span></li>
                <li><code>/bosyer</code><span>Boş park yeri</span></li>
                <li><code>/yardim</code><span>Komut listesi</span></li>
            </ul>
        </div>
    </div>
</section>

<section class="kart tablo-kart">
    <div class="tablo-ust"><h2>Son Gönderilen Mesajlar <span class="sayi-rozet"><?= count($durum['mesajlar']) ?> kayıt</span></h2></div>
    <?php if (count($durum['mesajlar']) === 0): ?>
        <div class="bos-durum"><span class="ikon-daire"><?= ikon('zil', 26) ?></span><h3>Henüz mesaj yok</h3><p>Gönderilen bildirimler ve komut cevapları burada listelenir.</p></div>
    <?php else: ?>
        <div class="tablo-kaydir"><table style="min-width: 620px">
            <thead><tr><th>Zaman</th><th>Tür</th><th>Mesaj</th><th>Sonuç</th></tr></thead>
            <tbody>
            <?php foreach ($durum['mesajlar'] as $m): ?>
                <tr>
                    <td class="soluk"><?= e(zamanYaz($m['zaman'])) ?></td>
                    <td><?= e(TG_TURLER[$m['tur']] ?? ucfirst($m['tur'])) ?></td>
                    <td><?= e($m['metin']) ?></td>
                    <td><span class="etiket <?= $m['tamam'] ? 'etiket-yesil' : 'etiket-kirmizi' ?>"><?= $m['tamam'] ? 'Gönderildi' : e('Hata: ' . $m['hata']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>

<?php require 'includes/footer.php'; ?>
