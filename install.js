let deferredPrompt;
const installBtn = document.getElementById('installBtn');

// Cek apakah browser mendukung event beforeinstallprompt
if ('beforeinstallprompt' in window) {
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;

    // Tampilkan tombol install
    installBtn.style.display = 'block';

    installBtn.addEventListener('click', () => {
      installBtn.style.display = 'none';
      deferredPrompt.prompt();
      deferredPrompt.userChoice.then((choiceResult) => {
        // Bisa tambahkan tracking atau log
        deferredPrompt = null;
      });
    });
  });
} else {
  // Fallback untuk browser yang tidak support prompt install otomatis
  installBtn.style.display = 'block';
  installBtn.textContent = '📱 Tambahkan ke layar utama';
  installBtn.addEventListener('click', () => {
    alert(
      "Browser ini tidak mendukung pemasangan otomatis.\n\nUntuk memasang aplikasi ini:\n• Tekan ikon menu browser (biasanya titik tiga/berbagi)\n• Pilih 'Tambahkan ke layar utama' atau 'Install App'."
    );
  });
}