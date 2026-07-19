<?php
/** Admin shell: sidebar nav (from resources) + topbar (current user) + content. */
use Panelix\View\View;

$e    = static fn (?string $v): string => View::e($v);
$user = $auth->user();
$page = $_GET['p'] ?? 'dashboard';
$curr = $_GET['r'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($config->brand) ?> · Admin</title>
    <style><?= file_get_contents(dirname(__DIR__, 3) . '/public/panelix.css') ?></style>
</head>
<body class="pnx">
<aside class="pnx-side">
    <a class="pnx-brand" href="<?= $e($config->url('p=dashboard')) ?>"><span class="pnx-logo">◧</span> <?= $e($config->brand) ?></a>
    <nav class="pnx-nav">
        <a class="<?= $page === 'dashboard' ? 'active' : '' ?>" href="<?= $e($config->url('p=dashboard')) ?>">
            <span class="pnx-ic">▦</span> Dashboard
        </a>
        <?php foreach ($config->resourcesFor($auth->role()) as $r): ?>
            <a class="<?= ($page === 'resource' && $curr === $r->key) ? 'active' : '' ?>" href="<?= $e($config->url('p=resource&r=' . $r->key)) ?>">
                <span class="pnx-ic"><?= $e($r->iconChar()) ?></span> <?= $e($r->label) ?>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>

<div class="pnx-main">
    <header class="pnx-top">
        <div class="pnx-top-title"></div>
        <div class="pnx-user">
            <span class="pnx-avatar"><?= $e($user?->initial()) ?></span>
            <span class="pnx-uname"><?= $e($user?->username) ?><small><?= $e($user?->role) ?></small></span>
            <a class="pnx-signout" href="<?= $e($config->url('p=logout')) ?>">Sign out</a>
        </div>
    </header>
    <main class="pnx-content"><?= $__content ?></main>
</div>
</body>
</html>
