<?php
$role        = $_SESSION['role'] ?? 'user';
$firstName   = $_SESSION['firstName'] ?? 'Utilisateur';
$baseUrl     = \App\Services\Config::baseUrl();

include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/navbar.php';
?>

<main id="main-content" class="section-sm">
  <div class="container">
    <div class="card p-5">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-4 mb-4">
        <div>
          <span class="label">Espace d'administration</span>
          <h1 class="h2 mt-1 mb-1">Bonjour, <?= htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') ?> 👋</h1>
          <p class="text-muted">Vous êtes connecté avec le rôle : <strong><?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?></strong></p>
        </div>
        <form action="<?= $baseUrl ?>/logout" method="POST" class="d-inline">
          <?= \App\Services\Csrf::inputField() ?>
          <button type="submit" class="btn btn-outline-danger">
            <i class="bi bi-box-arrow-right"></i> Déconnexion
          </button>
        </form>
      </div>

      <hr class="my-4">

      <div class="d-grid grid-3 gap-4">
        <div class="card p-4">
          <div class="icon-wrap mb-3"><i class="bi bi-shield-check"></i></div>
          <h3>Session Active</h3>
          <p class="text-muted text-sm">Votre session est protégée avec régénération d'ID et validation de l'empreinte client.</p>
        </div>
        <div class="card p-4">
          <div class="icon-wrap mb-3"><i class="bi bi-database"></i></div>
          <h3>Base de données</h3>
          <p class="text-muted text-sm">Gestion des migrations et modèles prête pour vos entités personnalisées.</p>
        </div>
        <div class="card p-4">
          <div class="icon-wrap mb-3"><i class="bi bi-speedometer2"></i></div>
          <h3>Monitoring & API</h3>
          <p class="text-muted text-sm">Endpoint de santé disponible sur <code>/health</code> avec vérification DB/Cache.</p>
          <a href="<?= $baseUrl ?>/health" target="_blank" class="btn btn-ghost btn-sm mt-2">Vérifier /health <i class="bi bi-arrow-right"></i></a>
        </div>
      </div>
    </div>
  </div>
</main>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>