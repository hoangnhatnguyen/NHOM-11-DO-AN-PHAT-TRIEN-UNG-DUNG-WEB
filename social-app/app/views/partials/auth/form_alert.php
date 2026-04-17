<?php if (!empty($error ?? null)): ?>
    <div class="alert alert-danger py-2 px-3 mb-3" role="alert">
        <?= htmlspecialchars((string) $error) ?>
    </div>
<?php endif; ?>

<?php if (!empty($success ?? null)): ?>
    <div class="alert alert-success py-2 px-3 mb-3" role="alert">
        <?= htmlspecialchars((string) $success) ?>
    </div>
<?php endif; ?>
