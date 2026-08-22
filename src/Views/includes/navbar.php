<?php
$baseUrl     = \App\Services\Config::baseUrl();
$currentUri  = $_SERVER['REQUEST_URI'] ?? $_SERVER['REDIRECT_URL'] ?? '/';
$currentPath = strtok($currentUri, '?') ?: '/';
$currentPath = '/' . trim($currentPath, '/');
if ($currentPath !== '/') $currentPath = rtrim($currentPath, '/');

$isActive = function (string $path) use ($currentPath): string {
    $norm = '/' . trim($path, '/');
    if ($norm !== '/') $norm = rtrim($norm, '/');
    return ($currentPath === $norm) ? 'active' : '';
};

$isConnected = !empty($_SESSION['connected']);
$isAdmin     = ($isConnected && ($_SESSION['role'] ?? '') === 'admin');
?>
<!-- Site Header -->
<header class="site-header" id="site-header">
  <nav class="navbar container" aria-label="Navigation principale">

    <!-- Logo -->
    <a href="<?= $baseUrl ?>/" class="nav-logo" aria-label="Accueil — Framework MVC">
      <img src="<?= asset_url('assets/imgs/logo.svg') ?>" alt="Logo MVC" width="48" height="24">
      <span class="logo-text">MVC Native</span>
    </a>

    <!-- Nav links -->
    <ul class="nav-links" id="nav-links" role="list">
      <li><a href="<?= $baseUrl ?>/" class="<?= $isActive('/') ?>" aria-current="<?= $isActive('/') === 'active' ? 'page' : 'false' ?>">Accueil</a></li>
      <?php if ($isConnected): ?>
        <li><a href="<?= $baseUrl ?>/dashboard" class="<?= $isActive('/dashboard') ?>" aria-current="<?= $isActive('/dashboard') === 'active' ? 'page' : 'false' ?>">Tableau de bord</a></li>
      <?php endif; ?>
    </ul>

    <!-- CTA Actions -->
    <div class="nav-actions">
      <?php if ($isConnected): ?>
        <form action="<?= $baseUrl ?>/logout" method="POST" class="d-inline">
          <?= \App\Services\Csrf::inputField() ?>
          <button type="submit" class="btn btn-ghost btn-sm">Déconnexion</button>
        </form>
        <a href="<?= $baseUrl ?>/dashboard" class="btn btn-primary btn-sm">Dashboard</a>
      <?php endif; ?>

      <!-- Mobile hamburger -->
      <button class="nav-toggle" id="nav-toggle" aria-controls="nav-links" aria-expanded="false" aria-label="Ouvrir le menu">
        <span></span>
        <span></span>
        <span></span>
      </button>
    </div>

  </nav>
</header>