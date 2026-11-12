<?php
/*
 * hareket.php — Araç Giriş-Çıkış sayfasındaki işlemler (POST)
 * islem=giris : plakaya göre giriş (abone ya da ziyaretçi)
 * islem=cikis : plakaya göre çıkış (ziyaretçide ödeme yöntemi zorunlu)
 */
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    yonlendir('giris-cikis.php');
}

$islem = formMetni('islem');
$plaka = plakaDuzenle(formMetni('plaka'));

if ($plaka === '') {
    mesajKaydet('hata', 'Plaka alanı boş bırakılamaz.');
} elseif ($islem === 'giris') {
    $sonuc = aracGirisi($plaka);
    mesajKaydet($sonuc['tamam'] ? 'basari' : 'hata', $sonuc['mesaj']);
    if ($sonuc['tamam']) { $_SESSION['bariyer'] = 'giris'; }   // bariyer simülasyonu
} elseif ($islem === 'cikis') {
    $sonuc = aracCikisi($plaka, formMetni('odeme'));
    mesajKaydet($sonuc['tamam'] ? 'basari' : 'hata', $sonuc['mesaj']);
    if ($sonuc['tamam']) { $_SESSION['bariyer'] = 'cikis'; }
} else {
    mesajKaydet('hata', 'Geçersiz işlem.');
}

yonlendir('giris-cikis.php');
