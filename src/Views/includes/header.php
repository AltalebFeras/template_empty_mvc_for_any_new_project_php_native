<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="the description of the website">
  <meta name="author" content="To identify">
  <meta name="robots" content="index, follow">
  <link rel="canonical" href="<?php echo DOMAIN . HOME_URL; ?>">
  <link rel="stylesheet" href="<?php echo HOME_URL . 'assets/css/app.css'; ?>">
  <?php
  /** translation title */
  $translations = [
    'home' => 'Accueil',
    'register' => 'Inscription',
    'login' => 'Connexion',
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
</head>

<body>