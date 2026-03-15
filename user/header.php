<?php
require_once __DIR__ . '/../core/settings-loader.php';
require_once __DIR__ . '/../core/helpers/html.php';
require_once __DIR__ . '/../core/helpers/redirect.php';

if (!Auth::check()) {
    redirect("../login.php");
    exit;
}

// Tentukan class berdasarkan role session
$themeClass = (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin') ? 'admin-theme' : '';

$siteName = setting('site-name', 'JP Kreasi');
$siteLogo = setting('site-logo'); // bisa null / kosong
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
    <?php if (!empty($siteLogo)): ?>
        <img src="<?= e($siteLogo) ?>"
             alt="<?= e($siteName) ?>"
             class="brand-logo">
    <?php else: ?>
        <strong><?= e($siteName) ?></strong>
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