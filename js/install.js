const installBtn = document.getElementById('installBtn');
let deferredPrompt;

installBtn.style.display = 'none'; // Sembunyikan dari awal

const isStandalone =
  window.matchMedia('(display-mode: standalone)').matches ||
  window.navigator.standalone === true;

// ❗ Cek apakah browser mendukung install prompt
let supportsInstall = false;
window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  deferredPrompt = e;
  supportsInstall = true;

  if (!isStandalone) {
    installBtn.style.display = 'flex';
  }

  installBtn.addEventListener('click', () => {
    installBtn.style.display = 'none';
    deferredPrompt.prompt();

    deferredPrompt.userChoice.then((choiceResult) => {
      if (choiceResult.outcome === 'accepted') {
        console.log('✅ Aplikasi diinstall');
      }
      deferredPrompt = null;
    });
  });
});

// ❗ Tambahan: kalau browser tidak pernah mendukung beforeinstallprompt
// pastikan tombol tetap disembunyikan
window.addEventListener('DOMContentLoaded', () => {
  setTimeout(() => {
    if (!supportsInstall && !isStandalone) {
      installBtn.style.display = 'none'; // Jangan tampilkan tombol di browser non-supported
    }
  }, 1000); // kasih waktu 1 detik untuk memastikan event belum muncul
});

// Jika sudah terinstal
window.addEventListener('appinstalled', () => {
  console.log('📱 PWA sudah diinstal');
  installBtn.style.display = 'none';
});