<?php
/** Standalone sign-in page. */
use Panelix\View\View;

$e = static fn (?string $v): string => View::e($v);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · <?= $e($config->brand) ?></title>
    <style><?= file_get_contents(dirname(__DIR__, 3) . '/public/panelix.css') ?></style>
</head>
<body class="pnx pnx-centered">
<div class="pnx-login">
    <div class="pnx-login-brand"><span class="pnx-logo">◧</span> <?= $e($config->brand) ?></div>
    <p class="pnx-muted">Sign in to the admin panel</p>

    <?php if (!empty($error)): ?>
        <div class="pnx-alert pnx-alert-error"><?= $e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= $e($config->url('p=login')) ?>" class="pnx-form">
        <input type="hidden" name="_token" value="<?= $e($csrf) ?>">
        <div class="pnx-field">
            <label for="username">Username</label>
            <input id="username" name="username" autocomplete="username" autofocus required>
        </div>
        <div class="pnx-field">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" autocomplete="current-password" required>
        </div>
        <button type="submit" class="pnx-btn pnx-btn-primary pnx-btn-block">Sign in</button>
    </form>
</div>
</body>
</html>
