<?php
$isLogged = !empty($_SESSION['user']);
?>

<aside class="sidebar">

<h3>User Menu</h3>

<ul>

<?php if ($isLogged): ?>

<li><a href="index.php">Dashboard</a></li>
<li><a href="../products.php">Produk</a></li>
<li><a href="../logout.php">Logout</a></li>

<?php else: ?>

<li><a href="../products.php">Lihat Produk</a></li>
<li><a href="../login.php">Login</a></li>
<li><a href="../register.php">Daftar Akun</a></li>

<?php endif; ?>

</ul>

</aside>