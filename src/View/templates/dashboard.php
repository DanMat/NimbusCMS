<?php
/** @var array<int,array{resource:\Panelix\Resource\Resource,count:int}> $cards */
use Panelix\View\View;

$e = static fn (?string $v): string => View::e($v);
?>
<div class="pnx-page-head">
    <h1>Dashboard</h1>
</div>

<div class="pnx-cards">
    <?php foreach ($cards as $card): $r = $card['resource']; ?>
        <a class="pnx-card" href="<?= $e($config->url('p=resource&r=' . $r->key)) ?>">
            <span class="pnx-card-ic"><?= $e($r->iconChar()) ?></span>
            <span class="pnx-card-count"><?= (int) $card['count'] ?></span>
            <span class="pnx-card-label"><?= $e($r->label) ?></span>
        </a>
    <?php endforeach; ?>
    <?php if ($cards === []): ?>
        <p class="pnx-muted">No resources are available for your role.</p>
    <?php endif; ?>
</div>
