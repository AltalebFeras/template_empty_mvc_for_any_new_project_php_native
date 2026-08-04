<?php
/**
 * Cloudflare Turnstile Widget — Reusable View Partial.
 *
 * Include in any form that needs bot protection:
 *   <?php include __DIR__ . '/../includes/turnstile.php'; ?>
 *
 * The Turnstile response token is submitted as 'cf-turnstile-response'.
 * Verify it server-side using App\Services\Turnstile::verify().
 */

$turnstileSiteKey = \App\Services\Config::get('TURNSTILE_SITE_KEY', '');
?>

<?php if ($turnstileSiteKey !== ''): ?>
    <div class="cf-turnstile"
         data-sitekey="<?= htmlspecialchars($turnstileSiteKey, ENT_QUOTES, 'UTF-8') ?>"
         data-theme="auto"
         data-size="normal">
    </div>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>
