<?php include_once __DIR__ . '/../includes/header.php'; ?>
<?php include_once __DIR__ . '/../includes/navbar.php'; ?>

<main id="main-content" class="section-sm">
    <div class="container text-center py-6">
        <div class="card p-6 max-w-lg mx-auto">
            <div class="display-1 text-primary fw-bold mb-2">404</div>
            <h1 class="h2 mb-3">Page Introuvable</h1>
            <p class="text-muted mb-4">La page que vous recherchez semble introuvable ou a été déplacée.</p>
            <div>
                <a class="btn btn-primary" href="<?= \App\Services\Config::baseUrl() ?>/">
                    <i class="bi bi-house"></i> Retour à l'accueil
                </a>
            </div>
        </div>
    </div>
</main>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>