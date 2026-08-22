<?php include_once __DIR__ . '/../includes/header.php'; ?>
<?php include_once __DIR__ . '/../includes/navbar.php'; ?>

<main id="main-content" class="section-sm">
    <div class="container auth-container">
        <div class="card auth-card">
            <div class="auth-header">
                <span class="label">Espace sécurisé</span>
                <h1 class="auth-title">Connexion</h1>
            </div>

            <form action="<?= htmlspecialchars(\App\Services\Config::baseUrl() . '/login', ENT_QUOTES, 'UTF-8') ?>" method="POST" class="d-flex flex-col gap-4">
                <?= \App\Services\Csrf::inputField() ?>

                <div class="form-group">
                    <label for="email" class="form-label">Adresse email</label>
                    <input type="email" name="email" id="email" class="form-control" placeholder="admin@example.com" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Mot de passe</label>
                    <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required>
                </div>

                <div class="form-group">
                    <?php include __DIR__ . '/../includes/turnstile.php'; ?>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg auth-submit-btn">
                    <i class="bi bi-box-arrow-in-right"></i>
                    Se connecter
                </button>
            </form>
        </div>
    </div>
</main>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
