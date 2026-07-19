<?php
/** Standalone error page (rendered without the layout). */
use Panelix\View\View;

$e = static fn (?string $v): string => View::e($v);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($title ?? 'Error') ?> · <?= $e($config->brand) ?></title>
    <style><?= @file_get_contents(dirname(__DIR__, 3) . '/public/panelix.css') ?></style>
</head>
<body class="pnx pnx-centered">
<div class="pnx-login">
    <h1 class="pnx-error-title"><?= $e($title ?? 'Error') ?></h1>
    <p class="pnx-muted"><?= $e($message ?? 'Something went wrong.') ?></p>
    <a class="pnx-btn" href="<?= $e($config->url('p=dashboard')) ?>">Back to dashboard</a>
</div>
</body>
</html>
