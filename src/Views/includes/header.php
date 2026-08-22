<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="index, follow">

  <?php
  /** Dynamic title & meta description */
  $pageMetaMap = [
    '/'          => ['title' => 'Accueil — Framework PHP MVC',   'desc' => 'Template moderne et robuste d\'application MVC en PHP natif sans dépendance lourde.'],
    '/login'     => ['title' => 'Connexion — Espace Sécurisé',   'desc' => 'Connexion sécurisée à votre compte utilisateur ou administrateur.'],
    '/dashboard' => ['title' => 'Tableau de bord',               'desc' => 'Espace de gestion et tableau de bord sécurisé.'],
    '/403'       => ['title' => '403 — Accès refusé',            'desc' => 'Vous n\'avez pas l\'autorisation d\'accéder à cette ressource.'],
    '/404'       => ['title' => '404 — Page non trouvée',        'desc' => 'La page demandée est introuvable.'],
    '/500'       => ['title' => '500 — Erreur serveur',          'desc' => 'Une erreur interne est survenue.'],
  ];

  $currentPath = strtok($_SERVER['REQUEST_URI'] ?? $_SERVER['REDIRECT_URL'] ?? '/', '?') ?: '/';
  $currentPath = '/' . trim($currentPath, '/');
  if ($currentPath !== '/') $currentPath = rtrim($currentPath, '/');

  $meta    = $pageMetaMap[$currentPath] ?? ['title' => ucfirst(trim($currentPath, '/')) ?: 'Accueil', 'desc' => 'Application MVC PHP Native.'];
  $title   = $meta['title'];
  $desc    = $meta['desc'];
  $baseUrl = \App\Services\Config::baseUrl();
  ?>

  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="description" content="<?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="author" content="PHP MVC Framework">
  <link rel="canonical" href="<?= htmlspecialchars($baseUrl . $currentPath, ENT_QUOTES, 'UTF-8') ?>">

  <!-- Open Graph -->
  <meta property="og:title" content="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:description" content="<?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= htmlspecialchars($baseUrl . $currentPath, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:locale" content="fr_FR">

  <!-- Favicons -->
  <link rel="icon" href="<?= asset_url('assets/imgs/logo.svg') ?>" type="image/svg+xml">
  <link rel="shortcut icon" href="<?= asset_url('assets/imgs/logo.svg') ?>" type="image/svg+xml">

  <!-- Stylesheets -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= asset_url('assets/css/root.css') ?>">
  <link rel="stylesheet" href="<?= asset_url('assets/css/global.css') ?>">
  <link rel="stylesheet" href="<?= asset_url('assets/css/app.css') ?>">

  <!-- Global JavaScript -->
  <script src="<?= asset_url('assets/js/main.js') ?>" defer></script>
</head>

<body>
  <a href="#main-content" class="skip-link">Aller au contenu principal</a>
  <?php include_once __DIR__ . '/messages.php'; ?>