<?php
declare(strict_types=1);

// $content is passed from Controller
require __DIR__ . '/header.php';
?>

<div class="container-fluid px-3 px-md-4 py-3 flex-grow-1" id="page-content" hx-target="#page-content">
    <?= $content ?? '' ?>
</div>

<?php
require __DIR__ . '/footer.php';
?>

