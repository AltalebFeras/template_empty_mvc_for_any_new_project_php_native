<?php include_once __DIR__ . '/../includes/header.php'; ?>
<?php include_once __DIR__ . '/../includes/navbar.php'; ?>

<main>
    <div class="d-flex flex-column justify-content-center align-items-center">
        <h1>500 Erreur Serveur</h1>
        <p>Une erreur interne s'est produite. Veuillez réessayer plus tard.</p>
        <a class="btn btn-secondary" href="<?= \App\Services\Config::baseUrl() ?>/">Retour à l'accueil</a>
    </div>
</main>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
