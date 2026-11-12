/* ParkTakip — arayüz betiği
   Tema, yan menü, bildirimler, onay penceresi, buton yükleme durumu ve tablo
   (arama · sıralama · sayfalama). Sunucu tarafı mantığına dokunmaz: tüm veri
   işlemleri PHP formlarıyla yapılmaya devam eder. */
(function () {
    'use strict';

    var kok = document.documentElement;

    function depola(anahtar, deger) {
        try { localStorage.setItem(anahtar, deger); } catch (hata) { /* depolama kapalı olabilir */ }
    }

    /* ---------- Tema ---------- */
    var temaDugme = document.getElementById('tema-degistir');
    if (temaDugme) {
        temaDugme.addEventListener('click', function () {
            var yeni = kok.getAttribute('data-tema') === 'acik' ? 'koyu' : 'acik';
            kok.setAttribute('data-tema', yeni);
            depola('parktakip-tema', yeni);
        });
    }

    /* ---------- Yan menü: masaüstünde daralt, mobilde çekmece ---------- */
    var daralt = document.getElementById('yan-daralt');
    function daraltDurumu() {
        var dar = kok.classList.contains('yan-dar');
        if (daralt) {
            daralt.setAttribute('aria-pressed', dar ? 'true' : 'false');
            daralt.setAttribute('aria-label', dar ? 'Menüyü genişlet' : 'Menüyü daralt');
            daralt.setAttribute('title', dar ? 'Menüyü genişlet' : 'Menüyü daralt');
        }
    }
    if (daralt) {
        daraltDurumu();
        daralt.addEventListener('click', function () {
            kok.classList.toggle('yan-dar');
            depola('parktakip-yan', kok.classList.contains('yan-dar') ? 'dar' : 'genis');
            daraltDurumu();
        });
    }

    var menuAc = document.getElementById('menu-ac');
    var perde = document.getElementById('yan-perde');
    var yanMenu = document.getElementById('yan-menu');
    function menuKapat() {
        kok.classList.remove('yan-acik');
        if (perde) { perde.hidden = true; }
        if (menuAc) { menuAc.setAttribute('aria-expanded', 'false'); }
    }
    if (menuAc && perde && yanMenu) {
        menuAc.addEventListener('click', function () {
            kok.classList.add('yan-acik');
            perde.hidden = false;
            menuAc.setAttribute('aria-expanded', 'true');
        });
        perde.addEventListener('click', menuKapat);
        yanMenu.addEventListener('click', function (olay) {
            if (olay.target.closest('a')) { menuKapat(); }
        });
        document.addEventListener('keydown', function (olay) {
            if (olay.key === 'Escape') { menuKapat(); }
        });
        window.addEventListener('resize', function () {
            if (window.innerWidth > 1000) { menuKapat(); }
        });
    }

    /* ---------- Bildirim: kapatma ve kendiliğinden kaybolma ---------- */
    var bildirim = document.querySelector('.bildirim');
    if (bildirim) {
        var bildirimKapat = function () {
            bildirim.classList.add('kapaniyor');
            setTimeout(function () { bildirim.remove(); }, 260);
        };
        var kapatDugme = bildirim.querySelector('.bildirim-kapat');
        if (kapatDugme) { kapatDugme.addEventListener('click', bildirimKapat); }
        if (bildirim.classList.contains('bildirim-basari')) { setTimeout(bildirimKapat, 6500); }
    }

    /* ---------- Onay penceresi (silme, sıfırlama) ---------- */
    var pencere = document.getElementById('onay-pencere');
    var bekleyenForm = null;

    function onayIste(form) {
        var metin = form.getAttribute('data-onay');
        if (!pencere || typeof pencere.showModal !== 'function') {
            // Eski tarayıcı: yerleşik onay kutusuna dön
            if (window.confirm(metin)) { form.setAttribute('data-onaylandi', '1'); form.requestSubmit ? form.requestSubmit() : form.submit(); }
            return;
        }
        bekleyenForm = form;
        document.getElementById('onay-baslik').textContent = form.getAttribute('data-onay-baslik') || 'Emin misiniz?';
        document.getElementById('onay-metin').textContent = metin;
        document.getElementById('onay-tamam').textContent = form.getAttribute('data-onay-buton') || 'Onayla';
        pencere.showModal();
        document.getElementById('onay-vazgec').focus();
    }

    if (pencere) {
        document.getElementById('onay-vazgec').addEventListener('click', function () { bekleyenForm = null; pencere.close(); });
        document.getElementById('onay-tamam').addEventListener('click', function () {
            var form = bekleyenForm;
            bekleyenForm = null;
            pencere.close();
            if (form) {
                form.setAttribute('data-onaylandi', '1');
                if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); }
            }
        });
        pencere.addEventListener('click', function (olay) {
            if (olay.target === pencere) { bekleyenForm = null; pencere.close(); }
        });
        pencere.addEventListener('cancel', function () { bekleyenForm = null; });
    }

    /* ---------- Form gönderimi: onay + yükleniyor durumu ---------- */
    document.addEventListener('submit', function (olay) {
        var form = olay.target;
        if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() === 'dialog') { return; }

        if (form.hasAttribute('data-onay') && !form.hasAttribute('data-onaylandi')) {
            olay.preventDefault();
            onayIste(form);
            return;
        }
        form.removeAttribute('data-onaylandi');

        var dugme = form.querySelector('button[type="submit"]');
        if (dugme) { dugme.classList.add('yukleniyor'); }
    });

    // Geri dönüldüğünde (önbellekten) düğmeler takılı kalmasın
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('.yukleniyor').forEach(function (d) { d.classList.remove('yukleniyor'); });
    });

    /* ---------- Plaka alanı: yazarken büyük harf ---------- */
    var plakaAlani = document.getElementById('plaka');
    if (plakaAlani) {
        plakaAlani.addEventListener('input', function () {
            var son = plakaAlani.selectionStart;
            plakaAlani.value = plakaAlani.value.toLocaleUpperCase('en-US');
            try { plakaAlani.setSelectionRange(son, son); } catch (hata) { /* bazı türlerde desteklenmez */ }
        });
    }

    /* ---------- Tablo: arama, sıralama, sayfalama ---------- */
    var tablo = document.getElementById('abone-tablosu');
    if (tablo) {
        var govde = tablo.tBodies[0];
        var satirlar = Array.prototype.slice.call(govde.rows);
        var kutu = document.getElementById('hizli-ara');
        var sayac = document.getElementById('kayit-sayisi');
        var bosKutu = document.getElementById('arama-bos');
        var bosSorgu = document.getElementById('arama-bos-sorgu');
        var sayfalamaKutu = document.getElementById('sayfalama');
        var sayfaBilgi = document.getElementById('sayfa-bilgi');
        var tabloAlt = document.getElementById('tablo-alt');
        var SAYFA_BOYU = 10;

        var durum = { sorgu: '', sutun: -1, yon: 1, sayfa: 1 };

        // Türkçe harfleri ve aksanları yok sayan karşılaştırma metni
        var sadelestir = function (metin) {
            return String(metin).toLocaleLowerCase('tr').replace(/ı/g, 'i').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/\s+/g, '');
        };

        satirlar.forEach(function (tr, sira) {
            tr._sira = sira;
            tr._aranan = sadelestir(tr.cells[0].textContent) + '|' + sadelestir(tr.cells[1].textContent);
        });

        var cizdir = function () {
            var uyanlar = satirlar.filter(function (tr) {
                return durum.sorgu === '' || tr._aranan.indexOf(durum.sorgu) !== -1;
            });

            if (durum.sutun >= 0) {
                var sut = durum.sutun;
                var sayisal = tablo.tHead.rows[0].cells[sut].getAttribute('data-tip') === 'sayi';
                uyanlar.sort(function (a, b) {
                    var x = a.cells[sut].getAttribute('data-sort') || a.cells[sut].textContent.trim();
                    var y = b.cells[sut].getAttribute('data-sort') || b.cells[sut].textContent.trim();
                    var fark = sayisal ? (parseFloat(x) - parseFloat(y)) : x.localeCompare(y, 'tr', { numeric: true, sensitivity: 'base' });
                    return fark * durum.yon || (a._sira - b._sira);
                });
            } else {
                uyanlar.sort(function (a, b) { return a._sira - b._sira; });
            }

            var toplam = uyanlar.length;
            var sayfaSayisi = Math.max(1, Math.ceil(toplam / SAYFA_BOYU));
            if (durum.sayfa > sayfaSayisi) { durum.sayfa = sayfaSayisi; }
            var bas = (durum.sayfa - 1) * SAYFA_BOYU;
            var gorunen = uyanlar.slice(bas, bas + SAYFA_BOYU);

            satirlar.forEach(function (tr) { tr.hidden = true; });
            gorunen.forEach(function (tr) { tr.hidden = false; govde.appendChild(tr); });

            if (sayac) { sayac.textContent = toplam; }
            tablo.parentNode.hidden = toplam === 0;
            if (bosKutu) {
                bosKutu.hidden = toplam !== 0;
                if (bosSorgu) { bosSorgu.textContent = kutu ? kutu.value : ''; }
            }
            if (tabloAlt) { tabloAlt.hidden = toplam === 0; }
            if (sayfaBilgi) {
                sayfaBilgi.textContent = toplam === 0 ? '' : (bas + 1) + '–' + (bas + gorunen.length) + ' / ' + toplam + ' kayıt';
            }
            sayfalamaCiz(sayfaSayisi);
        };

        var sayfalamaCiz = function (sayfaSayisi) {
            if (!sayfalamaKutu) { return; }
            sayfalamaKutu.innerHTML = '';
            sayfalamaKutu.hidden = sayfaSayisi <= 1;
            if (sayfaSayisi <= 1) { return; }

            var dugmeYap = function (etiket, sayfa, secenek) {
                var d = document.createElement('button');
                d.type = 'button';
                d.className = 'sayfa-buton' + (secenek && secenek.aktif ? ' aktif' : '');
                d.innerHTML = etiket;
                if (secenek && secenek.ariaEtiket) { d.setAttribute('aria-label', secenek.ariaEtiket); }
                if (secenek && secenek.aktif) { d.setAttribute('aria-current', 'page'); }
                if (secenek && secenek.pasif) { d.disabled = true; }
                d.addEventListener('click', function () { durum.sayfa = sayfa; cizdir(); });
                sayfalamaKutu.appendChild(d);
            };

            var oncekiSimge = '<svg class="ikon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>';
            var sonrakiSimge = '<svg class="ikon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>';

            dugmeYap(oncekiSimge, durum.sayfa - 1, { pasif: durum.sayfa === 1, ariaEtiket: 'Önceki sayfa' });
            for (var i = 1; i <= sayfaSayisi; i++) {
                dugmeYap(String(i), i, { aktif: i === durum.sayfa, ariaEtiket: i + '. sayfa' });
            }
            dugmeYap(sonrakiSimge, durum.sayfa + 1, { pasif: durum.sayfa === sayfaSayisi, ariaEtiket: 'Sonraki sayfa' });
        };

        if (kutu) {
            kutu.addEventListener('input', function () {
                durum.sorgu = sadelestir(kutu.value);
                durum.sayfa = 1;
                cizdir();
            });
        }

        // Sıralama: asc -> desc -> özgün sıra
        var basliklar = tablo.tHead.rows[0].cells;
        Array.prototype.forEach.call(basliklar, function (th, indis) {
            var dugme = th.querySelector('.sirala-buton');
            if (!dugme) { return; }
            th.setAttribute('aria-sort', 'none');
            dugme.addEventListener('click', function () {
                if (durum.sutun === indis && durum.yon === 1) {
                    durum.yon = -1;
                } else if (durum.sutun === indis && durum.yon === -1) {
                    durum.sutun = -1; durum.yon = 1;
                } else {
                    durum.sutun = indis; durum.yon = 1;
                }
                Array.prototype.forEach.call(basliklar, function (h, j) {
                    if (h.querySelector('.sirala-buton')) {
                        h.setAttribute('aria-sort', j === durum.sutun ? (durum.yon === 1 ? 'ascending' : 'descending') : 'none');
                    }
                });
                durum.sayfa = 1;
                cizdir();
            });
        });

        var temizle = document.getElementById('arama-temizle');
        if (temizle && kutu) {
            temizle.addEventListener('click', function () {
                kutu.value = ''; durum.sorgu = ''; durum.sayfa = 1; cizdir(); kutu.focus();
            });
        }

        cizdir();
    }

    /* ---------- "/" tuşu aramaya odaklanır ---------- */
    document.addEventListener('keydown', function (olay) {
        var hedef = olay.target;
        var yazılıyor = hedef && (hedef.tagName === 'INPUT' || hedef.tagName === 'SELECT' || hedef.tagName === 'TEXTAREA');
        var arama = document.getElementById('hizli-ara');
        if (olay.key === '/' && !yazılıyor && arama && !olay.ctrlKey && !olay.metaKey) {
            olay.preventDefault();
            arama.focus();
        }
    });

    /* ---------- Menü: "Aboneler" bölümüne gelince vurgula ---------- */
    var bolum = document.getElementById('aboneler');
    var menuAboneler = document.getElementById('menu-aboneler');
    var menuGenel = document.querySelector('.yan-gezinti .menu-oge[href="index.php"]');
    if (bolum && menuAboneler && menuGenel) {
        var bekliyor = false;
        var vurguGuncelle = function () {
            bekliyor = false;
            var ustte = bolum.getBoundingClientRect().top < window.innerHeight * 0.45;
            menuAboneler.classList.toggle('aktif', ustte);
            menuGenel.classList.toggle('aktif', !ustte);
            if (ustte) { menuAboneler.setAttribute('aria-current', 'location'); menuGenel.removeAttribute('aria-current'); }
            else { menuGenel.setAttribute('aria-current', 'page'); menuAboneler.removeAttribute('aria-current'); }
        };
        window.addEventListener('scroll', function () {
            if (!bekliyor) { bekliyor = true; window.requestAnimationFrame(vurguGuncelle); }
        }, { passive: true });
        vurguGuncelle();
    }
})();
