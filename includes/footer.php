        </main>
        <footer class="alt-serit">
            <span><?= UYGULAMA_ADI ?> &middot; Otopark Abone ve Giriş-Çıkış Takip Sistemi</span>
            <span class="alt-not">Kapasite: <?= TOPLAM_KAPASITE ?> araç</span>
        </footer>
    </div>
</div>

<!-- Onay penceresi: silme ve sıfırlama gibi işlemlerden önce açılır -->
<dialog class="onay-pencere" id="onay-pencere" aria-labelledby="onay-baslik">
    <form method="dialog" class="onay-ic">
        <span class="onay-ikon"><?= ikon('uyari', 22) ?></span>
        <h2 id="onay-baslik">Emin misiniz?</h2>
        <p id="onay-metin"></p>
        <div class="onay-butonlar">
            <button type="button" class="buton buton-ikincil" id="onay-vazgec">Vazgeç</button>
            <button type="button" class="buton buton-tehlike" id="onay-tamam">Onayla</button>
        </div>
    </form>
</dialog>
<?php if (tgAyar() !== null): ?>
<!-- Telegram işleyici: sayfa açıkken kuyruğu gönderir ve komutları cevaplar (arayüzü bekletmez) -->
<script>
(function () {
    function isle() { fetch('telegram-isle.php', { credentials: 'same-origin', cache: 'no-store' }).catch(function () {}); }
    setTimeout(isle, 800);
    setInterval(function () { if (!document.hidden) { isle(); } }, 6000);
})();
</script>
<?php endif; ?>
</body>
</html>
