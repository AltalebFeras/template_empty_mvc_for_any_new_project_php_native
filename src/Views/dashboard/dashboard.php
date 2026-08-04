<?php include_once __DIR__ . '/../includes/header.php'; ?>
<?php include_once __DIR__ . '/../includes/navbar.php'; ?>


<main>
    <h1>dashboard</h1>

    <p class="card">Bonjour <?= htmlspecialchars($_SESSION['firstName'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
    <?php include_once __DIR__ . '/../includes/messages.php'; ?>
    <div class="m">
        <a href="<?= \App\Services\Config::baseUrl() . '/all_lists' ?>" class="btn linkNotDecorated">Toutes les listes</a>
    </div>

</main>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>