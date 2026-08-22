<?php
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/navbar.php';
$baseUrl = \App\Services\Config::baseUrl();
?>

<main id="main-content">

  <!-- ============================================================
       HERO SECTION
  ============================================================ -->
  <section class="section hero-section" aria-labelledby="hero-heading">
    <div class="container">
      <div class="hero-grid">

        <!-- Left: Copy & Actions -->
        <div class="hero-content">
          <div class="hero-badge">
            <span class="badge badge-sage">PHP 8.2+ Native MVC</span>
            <span class="hero-badge-version">v2.0 Enterprise</span>
          </div>

          <h1 class="hero-title" id="hero-heading">
            Framework MVC Moderne<br>
            <em>Performant, Typé &amp; Sécurisé</em>
          </h1>

          <p class="hero-desc">
            Une fondation MVC native, propre et modulaire conçue pour démarrer rapidement tout projet web moderne sans la lourdeur d'un framework monolithique.
          </p>

          <div class="hero-features-list">
            <div class="hero-feat-item">
              <i class="bi bi-shield-check text-success"></i>
              <span>Argon2id &amp; Turnstile</span>
            </div>
            <div class="hero-feat-item">
              <i class="bi bi-signpost-split text-primary"></i>
              <span>Attributs de Routage</span>
            </div>
            <div class="hero-feat-item">
              <i class="bi bi-cpu text-accent"></i>
              <span>Files d'attente &amp; Migrations</span>
            </div>
          </div>
        </div>

        <!-- Right: Code / Architecture preview card -->
        <div class="hero-visual">
          <div class="card p-4 code-preview-card">
            <div class="code-header d-flex justify-content-between align-items-center mb-3">
              <div class="code-dots d-flex gap-1">
                <span class="dot dot-red"></span>
                <span class="dot dot-yellow"></span>
                <span class="dot dot-green"></span>
              </div>
              <span class="code-filename text-xs text-muted">src/Controllers/ExampleController.php</span>
            </div>
            <pre class="code-block"><code><span class="token-keyword">#[Route(</span><span class="token-string">'/dashboard'</span>, authRequired: <span class="token-keyword">true</span><span class="token-keyword">)]</span>
<span class="token-keyword">public function</span> <span class="token-function">dashboard</span>(): <span class="token-keyword">void</span>
{
    <span class="token-variable">$user</span> = <span class="token-variable">$this</span>-><span class="token-function">getUser</span>();
    <span class="token-variable">$this</span>-><span class="token-function">render</span>(<span class="token-string">'dashboard/index'</span>, [
        <span class="token-string">'user'</span> => <span class="token-variable">$user</span>
    ]);
}</code></pre>
          </div>
        </div>

      </div>
    </div>
  </section>

  <!-- ============================================================
       ARCHITECTURE & FEATURES GRID
  ============================================================ -->
  <section class="section bg-surface" aria-labelledby="features-heading">
    <div class="container">

      <div class="section-header text-center">
        <span class="label">Architecture</span>
        <h2 class="section-title" id="features-heading">
          Composants &amp; Fonctionnalités Inclus
        </h2>
        <p class="section-desc">
          Tous les modules nécessaires pour une application robuste sont pré-configurés et testés.
        </p>
      </div>

      <div class="d-grid grid-3 gap-6">

        <article class="card feature-card p-5">
          <div class="icon-wrap mb-3"><i class="bi bi-signpost-split"></i></div>
          <h3>Routage par Attributs</h3>
          <p class="text-muted">Définissez vos routes avec <code>#[Route('/path', methods: ['GET', 'POST'])]</code> directement au-dessus des méthodes de contrôleurs.</p>
        </article>

        <article class="card feature-card p-5">
          <div class="icon-wrap mb-3"><i class="bi bi-shield-lock"></i></div>
          <h3>Sécurité &amp; Sessions</h3>
          <p class="text-muted">Protection CSRF par formulaire, en-têtes HTTP de sécurité stricts (CSP, HSTS), sessions protégées contre la fixation et limitation de débit (Rate Limiting).</p>
        </article>

        <article class="card feature-card p-5">
          <div class="icon-wrap mb-3"><i class="bi bi-robot"></i></div>
          <h3>Cloudflare Turnstile</h3>
          <p class="text-muted">Anti-bot invisible ou interactif avec chargement différé (lazy-load) pour des performances web et scores Core Web Vitals optimaux.</p>
        </article>

        <article class="card feature-card p-5">
          <div class="icon-wrap mb-3"><i class="bi bi-database-check"></i></div>
          <h3>Migrations &amp; Repositories</h3>
          <p class="text-muted">Gestionnaire de migration intégré via CLI (<code>composer migrate</code>) et couche d'abstraction de données avec PDO typé.</p>
        </article>

        <article class="card feature-card p-5">
          <div class="icon-wrap mb-3"><i class="bi bi-envelope-paper"></i></div>
          <h3>Emails Transactionnels</h3>
          <p class="text-muted">Service de messagerie PHPMailer avec gabarit HTML responsive compatible Outlook et autodétection TLS/SSL.</p>
        </article>

        <article class="card feature-card p-5">
          <div class="icon-wrap mb-3"><i class="bi bi-hdd-stack"></i></div>
          <h3>Cache &amp; Background Jobs</h3>
          <p class="text-muted">Support de Redis avec repli automatique sur fichiers locaux et worker de tâches d'arrière-plan via CLI.</p>
        </article>

      </div>

    </div>
  </section>

  <!-- ============================================================
       QUICK START CALL TO ACTION
  ============================================================ -->
  <section class="section cta-band-dark" aria-label="Démarrage rapide">
    <div class="container text-center container-sm">
      <span class="label cta-band-accent">Prêt à développer ?</span>
      <h2 class="section-title cta-band-title">
        Créez votre première route en quelques minutes
      </h2>
      <p class="cta-band-desc">
        Configurez votre <code>.env</code>, exécutez <code>composer migrate</code> puis lancez votre serveur de développement.
      </p>
    </div>
  </section>

</main>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>