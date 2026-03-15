<?php
require_once '../core/settings-loader.php';
require_once '../core/helpers/html.php';

// Tentukan class berdasarkan role session
$themeClass = (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin') ? 'admin-theme' : '';
?>

<?php
if (!isset($_SESSION)) session_start();
Auth::requireAdmin();

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>User Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description"
      content="<?= e(setting('site-tagline')) ?>">

<link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">

</head>
<body class="<?= $themeClass ?>">

<header class="topbar">
  <div class="brand">
  <?php if ($logo = setting('site-logo')): ?>
      <img src="<?= e($logo) ?>" alt="<?= e(setting('site-name')) ?>" style="height:32px" class="brand-logo">
  <?php else: ?>
      <strong><?= e(setting('site-name')) ?></strong>
  <?php endif; ?>
  </div>
  <div class="user-info">
      <span class="user-name"><?= e($_SESSION['user']['fullname'] ?? 'Guest') ?></span>
      <span class="divider">|</span>
      <a href="<?= isset($_SESSION['user']) ? '../logout.php' : '../login.php' ?>" class="auth-link">
          <?= isset($_SESSION['user']) ? 'Logout' : 'Login' ?>
      </a>
  </div>

</header>

<div class="layout">