<?php include_once __DIR__ . '/../includes/header.php'; ?>
<?php include_once __DIR__ . '/../includes/navbar.php'; ?>

<main>
  <p>Home page</p>
  <?= (new DateTime())->format('Y-m-d H:i:s'); ?>
</main>

<!-- Cloudflare Turnstile Widget -->
<?php include __DIR__ . '/../includes/turnstile.php'; ?>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>