<?php
/**
 * @var \Panelix\Resource\Resource            $resource
 * @var array<int,array<string,mixed>>        $rows
 * @var array<string,array<string,string>>    $labels  belongsTo value=>label maps
 * @var string|null                           $search
 * @var string|null                           $flash
 */
use Panelix\Http\Csrf;
use Panelix\View\View;

$e = static fn (?string $v): string => View::e($v);

$cell = static function (\Panelix\Resource\Field $field, array $row) use ($labels, $e): string {
    $value = $row[$field->name] ?? '';
    return match ($field->type) {
        'belongsTo' => $e($labels[$field->name][(string) $value] ?? (string) $value),
        'money'     => $e(number_format((float) $value, 2)),
        'boolean'   => $value ? '✓' : '—',
        'password'  => '••••••',
        default     => $e(mb_strlen((string) $value) > 60 ? mb_substr((string) $value, 0, 60) . '…' : (string) $value),
    };
};
?>
<div class="pnx-page-head">
    <h1><?= $e($resource->label) ?></h1>
    <a class="pnx-btn pnx-btn-primary" href="<?= $e($config->url('p=resource&r=' . $resource->key . '&a=new')) ?>">+ New</a>
</div>

<?php if (!empty($flash)): ?>
    <div class="pnx-alert pnx-alert-ok"><?= $e(ucfirst($flash)) ?>.</div>
<?php endif; ?>

<form class="pnx-search" method="get" action="<?= $e($config->url()) ?>">
    <input type="hidden" name="p" value="resource">
    <input type="hidden" name="r" value="<?= $e($resource->key) ?>">
    <input type="search" name="q" value="<?= $e($search) ?>" placeholder="Search <?= $e(mb_strtolower($resource->label)) ?>…">
</form>

<div class="pnx-table-wrap">
    <table class="pnx-table">
        <thead>
        <tr>
            <?php foreach ($resource->listFields() as $f): ?>
                <th><?= $e($f->label) ?></th>
            <?php endforeach; ?>
            <th class="pnx-actions-col"></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): $id = (int) $row[$resource->pk]; ?>
            <tr>
                <?php foreach ($resource->listFields() as $f): ?>
                    <td><?= $cell($f, $row) ?></td>
                <?php endforeach; ?>
                <td class="pnx-row-actions">
                    <a href="<?= $e($config->url('p=resource&r=' . $resource->key . '&a=edit&id=' . $id)) ?>">Edit</a>
                    <form method="post" action="<?= $e($config->url('p=resource&r=' . $resource->key . '&a=delete&id=' . $id)) ?>" onsubmit="return confirm('Delete this record?');">
                        <input type="hidden" name="_token" value="<?= $e(Csrf::token()) ?>">
                        <button type="submit" class="pnx-link-danger">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($rows === []): ?>
            <tr><td class="pnx-empty" colspan="<?= count($resource->listFields()) + 1 ?>">No records yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
