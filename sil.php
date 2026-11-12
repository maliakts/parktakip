<?php
/*
 * sil.php — Abone silme
 * Yalnızca POST ile çalışır. Gönderilen id'ye sahip kaydı oturumdan kaldırır.
 */
require_once 'includes/functions.php';

// Sayfa adres çubuğundan (GET) açılırsa hiçbir şey silmeden geri dön
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    yonlendir('index.php');
}

yoneticiGerek();   // abone silme yalnızca yöneticiye açık

$silinecekId = (int)($_POST['id'] ?? 0);
$bulundu = false;

foreach ($_SESSION['araclar'] as $sira => $kayit) {
    if ($kayit['id'] === $silinecekId) {
        $plaka = $kayit['plaka'];
        // Araç içerideyse açık giriş kaydını kapat (geçmişte kalır)
        $acik = acikHareket($plaka);
        if ($acik !== null) {
            hareketKapat($acik['id'], 0, null);
        }
        unset($_SESSION['araclar'][$sira]);   // kaydı diziden çıkar
        $bulundu = true;
        break;
    }
}

if ($bulundu) {
    // Dizinin sıra numaralarını 0,1,2... şeklinde yeniden düzenle
    $_SESSION['araclar'] = array_values($_SESSION['araclar']);
    logKaydet($plaka . ' plakalı abone silindi');
    mesajKaydet('basari', $plaka . ' plakalı abone silindi.');
} else {
    mesajKaydet('hata', 'Silinmek istenen kayıt bulunamadı.');
}

yonlendir('index.php');
