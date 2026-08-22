<?php
/**
 * Global Flash message renderer in fixed Toast Container.
 * Supports: $_SESSION['success'], $_SESSION['error'], $_SESSION['errors'][]
 */
$flashItems = [];

if (!empty($_SESSION['success'])) {
    $flashItems[] = [
        'type'    => 'success',
        'title'   => 'Succès',
        'message' => (string)$_SESSION['success'],
        'icon'    => 'bi-check-circle-fill',
    ];
    unset($_SESSION['success']);
}

if (!empty($_SESSION['error'])) {
    $flashItems[] = [
        'type'    => 'danger',
        'title'   => 'Attention',
        'message' => (string)$_SESSION['error'],
        'icon'    => 'bi-exclamation-circle-fill',
    ];
    unset($_SESSION['error']);
}

if (!empty($_SESSION['errors'])) {
    foreach ((array)$_SESSION['errors'] as $err) {
        $flashItems[] = [
            'type'    => 'danger',
            'title'   => 'Erreur de validation',
            'message' => (string)$err,
            'icon'    => 'bi-exclamation-triangle-fill',
        ];
    }
    unset($_SESSION['errors']);
}
?>

<div class="toast-container" id="toast-container" aria-live="polite" aria-atomic="true">
  <?php foreach ($flashItems as $idx => $item): ?>
  <div class="toast toast-<?= htmlspecialchars($item['type'], ENT_QUOTES, 'UTF-8') ?>" id="toast-flash-<?= $idx ?>" role="alert" data-auto-dismiss="10000">
    <i class="bi <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?> toast-icon"></i>
    <div class="toast-content">
      <div class="toast-title"><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></div>
      <div class="toast-text"><?= htmlspecialchars($item['message'], ENT_QUOTES, 'UTF-8') ?></div>
    </div>
    <button type="button" class="toast-close" aria-label="Fermer la notification">✕</button>
    <div class="toast-progress-bar"></div>
  </div>
  <?php endforeach; ?>
</div>