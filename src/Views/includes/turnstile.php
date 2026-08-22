<?php
/**
 * Cloudflare Turnstile CAPTCHA Widget.
 *
 * Include in any form that needs bot protection:
 *   <?php include __DIR__ . '/../includes/turnstile.php'; ?>
 *
 * The Turnstile JS is loaded lazily — only when the user first interacts
 * with any form field. The response token is submitted as 'cf-turnstile-response'.
 * Verify it server-side using App\Services\Turnstile::verify().
 */

$turnstileSiteKey = \App\Services\Config::get('TURNSTILE_SITE_KEY', '');
?>

<?php if ($turnstileSiteKey !== ''): ?>
    <div class="cf-turnstile-wrapper">
        <div class="cf-turnstile"
             data-sitekey="<?= htmlspecialchars($turnstileSiteKey, ENT_QUOTES, 'UTF-8') ?>"
             data-theme="auto"
             data-size="normal">
        </div>
    </div>
    <script>
    (function () {
        if (window.__cfTurnstileLazyLoaded) return;
        function loadTurnstileScript() {
            if (window.__cfTurnstileLazyLoaded) return;
            window.__cfTurnstileLazyLoaded = true;
            var script = document.createElement('script');
            script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
            script.async = true;
            script.defer = true;
            document.head.appendChild(script);
        }
        var forms = document.querySelectorAll('form');
        forms.forEach(function (form) {
            if (form.querySelector('.cf-turnstile')) {
                ['focusin', 'input', 'change', 'click'].forEach(function (evt) {
                    form.addEventListener(evt, loadTurnstileScript, { once: true, passive: true });
                });
            }
        });
    })();
    </script>
<?php endif; ?>
