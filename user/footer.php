</div> <!-- end layout -->

<footer class="footer">

<div class="footer-inner">

© <?= date('Y') ?> <?= e(setting('site-name','JP Kreasi')) ?> – Digital Solutions
<br>
Built with automation, chatbot, and design expertise.

</div>

</footer>

<script>

/* mobile sidebar toggle */

const toggleBtn = document.createElement('div');
toggleBtn.className = 'menu-toggle';
toggleBtn.innerHTML = '☰';

document.querySelector('.topbar').prepend(toggleBtn);

toggleBtn.addEventListener('click', () => {
  const sidebar = document.querySelector('.sidebar');
  if (sidebar) sidebar.classList.toggle('active');
});

</script>

</body>
</html>