<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="the description of the website">
  <meta name="author" content="To identify">
  <meta name="robots" content="index, follow">
  <link rel="canonical" href="<?php echo DOMAIN . HOME_URL; ?>">
 
 <?php
  /** translation title */
  $translations = [
    'home' => 'Accueil',
    'signUp' => 'Inscription',
    'signIn' => 'Connexion',
  ];

  $title = '';
  $title = explode('/', trim($_SERVER['REDIRECT_URL']));
  $title = $title[1];

  if (empty($title)) {
    $title = 'Accueil';
  } elseif (isset($translations[$title])) {
    $title =  $translations[$title];
    $title = ucfirst($title);
  } else {
    $title = ucfirst($title);
  }

  ?>
  <title><?= $title ?></title>
  <?php
  //TODO include  favicon links like 
  ?>
 <!--  
  <link rel="icon" href="" type="image/x-icon">
  <link rel="apple-touch-icon" sizes="180x180" href="">
  <link rel="icon" type="image/png" sizes="32x32" href="">
  <link rel="icon" type="image/png" sizes="16x16" href="">
  <link rel="manifest" href="">
  <link rel="stylesheet" href=""> 
-->
 
  <?php
  $route = $_SERVER['REDIRECT_URL'];

  // Include CSS file based on current route. This is just a placeholder. Replace with your own logic.
  switch ($route) {

    case HOME_URL:
      echo '<link rel="stylesheet" href="' . HOME_URL . 'assets/styles/accueil.css' . '">';
      break;
      // we can put more than one url for the same file css
    case HOME_URL . 'signIn':
    case HOME_URL . 'signUp':
      echo '<link rel="stylesheet" href="' . HOME_URL . 'assets/styles/auth.css">';
      break;
    default:
      echo '<link rel="stylesheet" href="' . HOME_URL . 'assets/styles/404.css">';
      break;
  }
  ?>
</head>

<body>