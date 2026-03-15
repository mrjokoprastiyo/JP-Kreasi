</div> <!-- end layout -->

<footer class="footer">
  <small>
    © <?= date('Y') ?> <?= e(setting('site-name')) ?> — <?= setting('site-tagline') ?>
  </small>
</footer>
<script>
const toggleBtn = document.createElement('div');
toggleBtn.className = 'menu-toggle';
toggleBtn.innerHTML = '☰';
document.querySelector('.topbar').prepend(toggleBtn);

toggleBtn.addEventListener('click', () => {
  document.querySelector('.sidebar').classList.toggle('active');
});
</script>
<script>
function previewLogo(input) {
    if (!input.files || !input.files[0]) return;

    const file = input.files[0];

    // basic validation (client-side)
    if (!file.type.startsWith('image/')) {
        alert('File harus berupa gambar');
        input.value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = function (e) {
        const img = document.getElementById('logoPreview');
        img.src = e.target.result;
        img.style.display = 'inline-block';
    };
    reader.readAsDataURL(file);
}
</script>
</body>
</html>