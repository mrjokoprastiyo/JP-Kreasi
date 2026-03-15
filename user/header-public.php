<?php
require_once __DIR__ . '/../core/settings-loader.php';
require_once __DIR__ . '/../core/helpers/html.php';

$siteName = setting('site-name', 'JP Kreasi');
$siteLogo = setting('site-logo');

$isLogged = isset($_SESSION['user']);
$themeClass = ($isLogged && ($_SESSION['user']['role'] ?? '') === 'admin') ? 'admin-theme' : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title><?= e($siteName) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description"
      content="<?= e(setting('site-tagline')) ?>">

<link rel="stylesheet" href="/assets/css/style.css?v=<?php echo time(); ?>">

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

      <?php if ($isLogged): ?>

          <span class="user-name">
              <?= e($_SESSION['user']['fullname'] ?? 'User') ?>
          </span>

          <span class="divider">|</span>

          <a href="/logout.php" class="auth-link">
              Logout
          </a>

      <?php else: ?>

          <a href="/login.php" class="auth-link">
              Login
          </a>

          <span class="divider">|</span>

          <a href="/register.php" class="auth-link">
              Register
          </a>

      <?php endif; ?>

  </div>

</header>

<div class="layout">