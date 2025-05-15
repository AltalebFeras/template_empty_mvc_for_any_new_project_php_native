<?php include_once __DIR__ . '/../includes/header.php'; ?>
<?php include_once __DIR__ . '/../includes/navbar.php'; ?>


<main>
    <h1>dashboard</h1>

    <p class="card">Bonjour <?= $_SESSION['firstName'] ?></p>
    <?php include_once __DIR__ . '/../includes/messages.php'; ?>
    <div class="m">
        <a href="<?= HOME_URL . "all_lists" ?>" class="btn linkNotDecorated">Toutes les listes</a>
    </div>

</main>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>