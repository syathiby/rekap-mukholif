<?php
declare(strict_types=1);

// $content is passed from Controller
require __DIR__ . '/header.php';
?>

<div id="page-content">
    <?= $content ?? '' ?>
</div>

<?php
require __DIR__ . '/footer.php';
?>

