<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="the description of the website">
  <meta name="author" content="To identify">
  <meta name="robots" content="index, follow">
  <link rel="canonical" href="<?= htmlspecialchars(\App\Services\Config::baseUrl(), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="<?= \App\Services\Config::baseUrl() . '/assets/css/app.css' ?>">
  <?php
  /** translation title */
  $translations = [
    'home' => 'Accueil',
    'register' => 'Inscription',
    'login' => 'Connexion',
  ];

  $title = '';
  $redirectUrl = $_SERVER['REDIRECT_URL'] ?? '/';
  $parts = explode('/', trim($redirectUrl, '/'));
  $title = $parts[0] ?? '';

  if (empty($title)) {
    $title = 'Accueil';
  } elseif (isset($translations[$title])) {
    $title =  $translations[$title];
    $title = ucfirst($title);
  } else {
    $title = ucfirst($title);
  }

  ?>
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="icon" href="https://favicon.pub/php.net" type="image/x-icon">
  <link rel="shortcut icon" href="https://favicon.pub/php.net" type="image/x-icon">
</head>

<body>