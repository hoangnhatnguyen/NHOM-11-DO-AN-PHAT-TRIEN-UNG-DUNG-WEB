<?php
$modalId = $modalId ?? 'appModal';
$modalTitle = $modalTitle ?? 'Thông báo';
$modalBody = $modalBody ?? '';
?>
<div class="modal fade" id="<?= htmlspecialchars($modalId) ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title"><?= htmlspecialchars($modalTitle) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body"><?= $modalBody ?></div>
        </div>
    </div>
</div>
