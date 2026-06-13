<?php
$flash = $_SESSION['flash_message'] ?? null;
$msg = $flash['message'] ?? $message ?? '';
$msgType = $flash['type'] ?? $type ?? 'info';

if (!empty($msg)):
    $icon = 'fa-info-circle';
    if ($msgType === 'success') $icon = 'fa-check-circle';
    if ($msgType === 'danger' || $msgType === 'error') $icon = 'fa-times-circle';
    if ($msgType === 'warning') $icon = 'fa-exclamation-triangle';
    
    // Convert 'error' to 'danger' for Bootstrap classes
    if ($msgType === 'error') $msgType = 'danger';
?>
<div class="alert alert-<?= htmlspecialchars($msgType) ?> alert-dismissible fade show shadow-sm border-0" role="alert">
    <i class="fas <?= $icon ?> me-2"></i> <?= htmlspecialchars($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php 
    if ($flash) unset($_SESSION['flash_message']);
endif; 
?>
