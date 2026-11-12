<?php
/*
 * durum.php — Abone listesinden giriş / çıkış işlemi
 * Aracın durumunu "İçeride" <-> "Dışarıda" olarak değiştirir (POST).
 * Kurallar ve geçmiş kaydı functions.php içindeki aracGirisi() / aracCikisi() fonksiyonlarındadır.
 */
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    yonlendir('index.php');
}

$id = (int)($_POST['id'] ?? 0);

foreach ($_SESSION['araclar'] as $kayit) {
    if ($kayit['id'] !== $id) {
        continue;
    }
    $sonuc = $kayit['durum'] === 'İçeride' ? aracCikisi($kayit['plaka']) : aracGirisi($kayit['plaka']);
    mesajKaydet($sonuc['tamam'] ? 'basari' : 'hata', $sonuc['mesaj']);
    yonlendir('index.php');
}

mesajKaydet('hata', 'İşlem yapılmak istenen kayıt bulunamadı.');
yonlendir('index.php');
