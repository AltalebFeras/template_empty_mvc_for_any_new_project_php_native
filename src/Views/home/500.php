<?php include_once __DIR__ . '/../includes/header.php'; ?>
<?php include_once __DIR__ . '/../includes/navbar.php'; ?>

<main id="main-content" class="section-sm">
    <div class="container text-center py-6">
        <div class="card p-6 max-w-lg mx-auto">
            <div class="display-1 text-danger fw-bold mb-2">500</div>
            <h1 class="h2 mb-3">Erreur Interne du Serveur</h1>
            <p class="text-muted mb-4">Une anomalie inattendue s'est produite. L'incident a été consigné dans les journaux système.</p>
            <div>
                <a class="btn btn-primary" href="<?= \App\Services\Config::baseUrl() ?>/">
                    <i class="bi bi-house"></i> Retour à l'accueil
                </a>
            </div>
        </div>
    </div>
</main>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
