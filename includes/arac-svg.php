<?php
/*
 * arac-svg.php — 3 boyutlu araç çizimleri (SVG). Park alanı (yönetim paneli) ve müşteri rezervasyon haritası ortak kullanır.
 * aracTanimlari(): gradyan tanımlarını bir kez sayfaya basar.
 */
// Park alanındaki 3 boyutlu araç çizimleri (SVG). $k = renk, $v = tip (0 sedan, 1 SUV, 2 coupe)
$aracRenkleri = [
    ['#0C447C', '#85B7EB', '#378ADD', '#042C53'],
    ['#791F1F', '#F7A8A8', '#E24B4A', '#501313'],
    ['#888780', '#FFFFFF', '#E4E2DA', '#5F5E5A'],
    ['#1a1a19', '#888780', '#444441', '#0d0d0c'],
    ['#085041', '#9FE1CB', '#1D9E75', '#04342C'],
    ['#633806', '#FAC775', '#EF9F27', '#412402'],
];
if (!function_exists('aracSvg')) {
function aracSvg(int $k, int $v, array $pal): string
{
    $u = $pal[$k][3];
    $g = 'url(#ag' . $k . ')';
    $B = 'M6.5 15 C6.5 6 12 2.5 20 2.5 C28 2.5 33.5 6 33.5 15 L35 36 L35 54 C35 67 29.5 73 20 73 C10.5 73 5 67 5 54 L5 36 Z';
    $ry = 34; $rh = 15; $arka = 'M11.8 49 L28.2 49 L29.6 56 Q20 59 10.4 56 Z'; $kuyrukY = 60;
    if ($v === 1) { $ry = 33.5; $rh = 22; $arka = 'M12 55.5 L28 55.5 L29.2 60.5 Q20 62.6 10.8 60.5 Z'; $kuyrukY = 62.5; }
    $s = '<svg class="arac3d" viewBox="0 0 40 76" aria-hidden="true">';
    $s .= '<rect x="0" y="12" width="6.5" height="13" rx="3" fill="#161615"/><rect x="33.5" y="12" width="6.5" height="13" rx="3" fill="#161615"/>';
    $s .= '<rect x="0" y="50" width="6.5" height="13" rx="3" fill="#161615"/><rect x="33.5" y="50" width="6.5" height="13" rx="3" fill="#161615"/>';
    $s .= '<path d="' . $B . '" transform="translate(0 3)" fill="' . $u . '"/>';
    $s .= '<path d="' . $B . '" fill="' . $g . '"/>';
    $s .= '<path d="M10 8 C12 5 16 4.6 20 4.6 C24 4.6 28 5 30 8 L29 20 Q20 22.5 11 20 Z" fill="url(#ash)" opacity=".8"/>';
    $s .= '<path d="M6.9 17 L5.8 36" stroke="#fff" stroke-opacity=".5" stroke-width=".9" fill="none" stroke-linecap="round"/>';
    $s .= '<path d="M8.6 10.5 Q10 7 14 6 L14.6 8.2 Q11.2 9.2 9.8 12.4 Z" fill="#FFF6D5"/><path d="M31.4 10.5 Q30 7 26 6 L25.4 8.2 Q28.8 9.2 30.2 12.4 Z" fill="#FFF6D5"/>';
    $s .= '<rect x="15.5" y="4" width="9" height="1.5" rx=".75" fill="#000" opacity=".35"/>';
    $s .= '<path d="M6 27 Q1.5 26.6 1.5 29 Q1.8 31.2 6 31.2 Z" fill="' . $g . '"/><path d="M34 27 Q38.5 26.6 38.5 29 Q38.2 31.2 34 31.2 Z" fill="' . $g . '"/>';
    $s .= '<path d="M10 25 Q20 20.5 30 25 L28.6 34 L11.4 34 Z" fill="url(#agl)"/><path d="M12.2 25.6 Q17 23.4 21.5 23.3" stroke="#fff" stroke-opacity=".5" stroke-width=".9" fill="none" stroke-linecap="round"/>';
    $s .= '<rect x="11" y="' . ($ry + 1.8) . '" width="18" height="' . $rh . '" rx="5.2" fill="#000" opacity=".3"/>';
    $s .= '<rect x="11" y="' . $ry . '" width="18" height="' . $rh . '" rx="5.2" fill="' . ($v === 2 ? 'url(#agp)' : $g) . '"/>';
    $s .= '<rect x="12.5" y="' . ($ry + 1) . '" width="15" height="5.5" rx="2.6" fill="url(#ash)" opacity="' . ($v === 2 ? '.6' : '.85') . '"/>';
    if ($v === 1) {
        $s .= '<path d="M12.6 37 L12.6 53 M27.4 37 L27.4 53" stroke="#000" stroke-opacity=".4" stroke-width=".9" stroke-linecap="round"/>';
    }
    $s .= '<path d="' . $arka . '" fill="url(#agl)"/>';
    $s .= '<path d="M5.4 35.2 L11 35.2 M29 35.2 L34.6 35.2 M5.3 50 L10.8 50 M29.2 50 L34.7 50" stroke="#000" stroke-opacity=".25" stroke-width=".6"/>';
    $s .= '<rect x="11" y="' . $kuyrukY . '" width="18" height="7" rx="3.5" fill="url(#ash)" opacity=".45"/>';
    $s .= '<path d="M8.2 66.4 Q20 71.4 31.8 66.4" stroke="#E24B4A" stroke-width="1.8" fill="none" stroke-linecap="round"/><path d="M8.2 66.4 Q10 67.4 11.4 67.6" stroke="#F7C1C1" stroke-width=".9" fill="none" stroke-linecap="round"/>';
    return $s . '</svg>';
}
}
$aracRenkSirasi = [0, 3, 2, 1, 0, 4, 3, 2, 5, 0, 3, 1];
$aracTipSirasi  = [0, 1, 0, 2, 1, 0, 2, 0, 1, 0, 2, 1];

// aracOnSvg: aracın önden-yukarıdan (3 boyutlu) görünümü. Park etmiş araçlar bununla çizilir.
// $k = renk, $v = tip (0 sedan, 1 SUV, 2 cam tavanlı coupe)
if (!function_exists('aracOnSvg')) {
function aracOnSvg(int $k, int $v, array $pal): string
{
    [$koyu, $acik, $orta, $golge] = $pal[$k];
    $g  = 'url(#ag' . $k . ')';
    $ty = $v === 1 ? 5 : 9;                 // tavanın üst kenarı (SUV daha yüksek)
    $s  = '<svg class="arac-on" viewBox="0 0 64 84" aria-hidden="true">';
    $s .= '<ellipse cx="32" cy="76" rx="29" ry="7" fill="#000" opacity=".5"/>';
    // tekerlekler
    $s .= '<rect x="4" y="60" width="11" height="19" rx="4" fill="#0b0b0c"/><rect x="49" y="60" width="11" height="19" rx="4" fill="#0b0b0c"/>';
    // gövde (arkaya doğru daralır)
    $s .= '<path d="M12 ' . ($ty + 20) . ' Q12 ' . $ty . ' 32 ' . $ty . ' Q52 ' . $ty . ' 52 ' . ($ty + 20) . ' L57 56 Q60 60 60 66 L60 70 Q60 76 54 76 L10 76 Q4 76 4 70 L4 66 Q4 60 7 56 Z" fill="' . $g . '"/>';
    // tavan
    $s .= '<path d="M17 ' . ($ty + 16) . ' Q17 ' . ($ty + 3) . ' 32 ' . ($ty + 3) . ' Q47 ' . ($ty + 3) . ' 47 ' . ($ty + 16) . ' L48 31 Q32 28 16 31 Z" fill="' . ($v === 2 ? 'url(#agp)' : $g) . '"/>';
    $s .= '<path d="M19 ' . ($ty + 14) . ' Q19 ' . ($ty + 5) . ' 32 ' . ($ty + 5) . ' Q45 ' . ($ty + 5) . ' 45 ' . ($ty + 14) . ' L45.5 22 Q32 20 18.5 22 Z" fill="url(#ash)" opacity="' . ($v === 2 ? '.35' : '.8') . '"/>';
    if ($v === 1) {
        $s .= '<path d="M20 10 L20 26 M44 10 L44 26" stroke="#000" stroke-opacity=".45" stroke-width="1.4" stroke-linecap="round"/>';
    }
    // yan camlar ve aynalar
    $s .= '<path d="M13 ' . ($ty + 19) . ' L16 31 L12.5 44 L10.5 44 Z M51 ' . ($ty + 19) . ' L48 31 L51.5 44 L53.5 44 Z" fill="#101318"/>';
    $s .= '<path d="M9 41 Q3 40 2.5 43.5 Q3 46.5 9 46 Z" fill="' . $koyu . '"/><path d="M55 41 Q61 40 61.5 43.5 Q61 46.5 55 46 Z" fill="' . $koyu . '"/>';
    // ön cam
    $s .= '<path d="M15.5 32 Q32 28.5 48.5 32 L52 45 Q32 41.5 12 45 Z" fill="url(#agl)"/>';
    $s .= '<path d="M18 33.5 Q26 31.6 33 31.5 L30 42 Q22 42.3 15.5 43.5 Z" fill="#fff" opacity=".2"/>';
    // kaput
    $s .= '<path d="M12 45 Q32 41.5 52 45 L57 58 Q32 54.5 7 58 Z" fill="' . $g . '"/>';
    $s .= '<path d="M14 46.5 Q32 43.5 50 46.5 L52 52 Q32 49 12 52 Z" fill="url(#ash)" opacity=".6"/>';
    $s .= '<path d="M24 46 L22.5 56.5 M40 46 L41.5 56.5" stroke="#000" stroke-opacity=".18" stroke-width=".8"/>';
    // ön yüz: ızgara, farlar, tampon, plaka
    $s .= '<path d="M7 58 Q32 54.5 57 58 Q60 61 60 66 L60 70 Q60 76 54 76 L10 76 Q4 76 4 70 L4 66 Q4 61 7 58 Z" fill="' . $golge . '"/>';
    $s .= '<path d="M7.5 59 Q32 55.8 56.5 59 L57.5 62 Q32 59.2 6.5 62 Z" fill="' . $orta . '" opacity=".75"/>';
    $s .= '<rect x="21" y="62" width="22" height="7" rx="2.5" fill="#08090a"/><path d="M23 64.3 H41 M23 66.6 H41" stroke="#3a3d42" stroke-width=".8"/>';
    $s .= '<ellipse class="far-isik" cx="12.5" cy="65" rx="9" ry="5.5" fill="#fff3c2" opacity=".55" filter="url(#afar)"/><ellipse class="far-isik" cx="51.5" cy="65" rx="9" ry="5.5" fill="#fff3c2" opacity=".55" filter="url(#afar)"/>';
    $s .= '<path d="M6.5 63 Q11 60.5 18.5 62 L18 66.5 Q11 67.5 6.5 67 Z" fill="#fffbe6"/><path d="M57.5 63 Q53 60.5 45.5 62 L46 66.5 Q53 67.5 57.5 67 Z" fill="#fffbe6"/>';
    $s .= '<rect x="6" y="70.5" width="52" height="4" rx="2" fill="#0d0e10"/><rect x="25" y="70" width="14" height="4.6" rx="1" fill="#f4f4f1"/>';
    return $s . '</svg>';
}
}


function aracTanimlari(array $pal): string
{
    $s = '<svg class="arac-tanim" width="0" height="0" aria-hidden="true" style="position:absolute"><defs>'
       . '<linearGradient id="agl" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#0C447C"/><stop offset=".5" stop-color="#1c1c1b"/><stop offset="1" stop-color="#5F5E5A"/></linearGradient>'
       . '<linearGradient id="agp" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#042C53"/><stop offset=".55" stop-color="#2C2C2A"/><stop offset="1" stop-color="#185FA5"/></linearGradient>'
       . '<filter id="afar" x="-60%" y="-80%" width="220%" height="260%"><feGaussianBlur stdDeviation="2.6"/></filter>'
       . '<linearGradient id="ash" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".75"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></linearGradient>';
    foreach ($pal as $ri => $p) {
        $s .= '<linearGradient id="ag' . $ri . '" x1="0" x2="1"><stop offset="0" stop-color="' . $p[0] . '"/><stop offset=".3" stop-color="' . $p[1] . '"/><stop offset=".58" stop-color="' . $p[2] . '"/><stop offset="1" stop-color="' . $p[0] . '"/></linearGradient>';
    }
    return $s . '</defs></svg>';
}
