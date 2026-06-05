<?php
$type = $type ?? 'info';
$message = $message ?? '';
$icon = 'fa-info-circle';
if ($type === 'success') $icon = 'fa-check-circle';
if ($type === 'danger') $icon = 'fa-times-circle';
if ($type === 'warning') $icon = 'fa-exclamation-triangle';
?>
<div class="alert alert-<?= htmlspecialchars($type) ?> alert-dismissible fade show" role="alert">
    <i class="fas <?= $icon ?> me-2"></i> <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
