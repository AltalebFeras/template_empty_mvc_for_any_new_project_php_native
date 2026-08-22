<?php
$baseUrl = \App\Services\Config::baseUrl();
$year    = date('Y');
?>
<!-- Site Footer -->
<footer class="site-footer" aria-label="Pied de page">
  <div class="container">
    <div class="footer-grid">

      <!-- Brand -->
      <div class="footer-brand">
        <a href="<?= $baseUrl ?>/" class="footer-logo d-flex align-items-center gap-2 mb-2" aria-label="Accueil">
          <img src="<?= asset_url('assets/imgs/logo.svg') ?>" alt="Logo MVC" width="40" height="20">
          <strong>MVC Native Framework</strong>
        </a>
        <p class="footer-tagline">
          Architecture MVC légère, robuste et sécurisée en PHP 8.2+ natif avec attributs de routage, PSR-3 logging, validation et sécurité renforcée.
        </p>
      </div>

      <!-- Quick links -->
      <div>
        <p class="footer-col-title">Navigation</p>
        <ul class="footer-links" role="list">
          <li><a href="<?= $baseUrl ?>/">Accueil</a></li>
        </ul>
      </div>

      <!-- Tech Stack -->
      <div>
        <p class="footer-col-title">Fonctionnalités</p>
        <ul class="footer-links" role="list">
          <li><span>PHP 8.2+ Typed MVC</span></li>
          <li><span>Argon2id & Turnstile</span></li>
          <li><span>Monolog 3 & Predis</span></li>
          <li><span>Migrations & Background Jobs</span></li>
        </ul>
      </div>

      <!-- Docs / Info -->
      <div>
        <p class="footer-col-title">Documentation</p>
        <p class="text-sm text-muted mb-2">
          Consultez le dossier <code>/docs</code> pour les guides d'architecture, sécurité, routage et déploiement.
        </p>
        <p class="footer-micro-location text-xs text-muted">
          Environnement : <strong><?= htmlspecialchars(\App\Services\Config::get('APP_ENV', 'development'), ENT_QUOTES) ?></strong>
        </p>
      </div>

    </div>

    <!-- Bottom bar -->
    <div class="footer-bottom">
      <p class="footer-copyright">
        &copy; <?= $year ?> Enterprise-Ready PHP MVC Framework Template. MIT License.
      </p>
      <div class="footer-bottom-links">
        <a href="<?= $baseUrl ?>/">Accueil</a>
        <a href="<?= $baseUrl ?>/login">Connexion</a>
      </div>
    </div>
  </div>
</footer>

</body>

</html>