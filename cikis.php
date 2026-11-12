<?php
/* cikis.php — Oturumu kapatır. Otopark verileri oturumda kalır, yalnızca kullanıcı çıkar. */
require_once 'includes/functions.php';

logKaydet('Oturum kapatıldı');
unset($_SESSION['kullanici']);
yonlendir('giris.php');
