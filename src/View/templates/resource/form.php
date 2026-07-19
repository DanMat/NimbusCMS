<?php
/**
 * @var \Panelix\Resource\Resource         $resource
 * @var array<string,mixed>|null           $row
 * @var array<string,array<string,string>> $options belongsTo value=>label maps
 * @var string                             $csrf
 */
use Panelix\View\View;

$e       = static fn (?string $v): string => View::e($v);
$editing = $row !== null;
$id      = $editing ? (int) $row[$resource->pk] : 0;
$action  = $editing ? 'update' : 'create';
$value   = static fn (string $name) => $row[$name] ?? '';
$formUrl = $config->url('p=resource&r=' . $resource->key . '&a=' . $action . ($editing ? '&id=' . $id : ''));
?>
<div class="pnx-page-head">
    <h1><?= $editing ? 'Edit' : 'New' ?> · <?= $e($resource->label) ?></h1>
    <a class="pnx-btn" href="<?= $e($config->url('p=resource&r=' . $resource->key)) ?>">← Back</a>
</div>

<form class="pnx-form pnx-form-card" method="post" action="<?= $e($formUrl) ?>">
    <input type="hidden" name="_token" value="<?= $e($csrf) ?>">

    <?php foreach ($resource->formFields() as $f): $v = $value($f->name); ?>
        <div class="pnx-field">
            <label for="f_<?= $e($f->name) ?>">
                <?= $e($f->label) ?><?php if ($f->required): ?> <span class="pnx-req">*</span><?php endif; ?>
            </label>

            <?php if ($f->type === 'textarea'): ?>
                <textarea id="f_<?= $e($f->name) ?>" name="<?= $e($f->name) ?>" rows="4" <?= $f->required ? 'required' : '' ?>><?= $e((string) $v) ?></textarea>

            <?php elseif ($f->type === 'select' || $f->type === 'belongsTo'): ?>
                <?php $opts = $f->type === 'select' ? $f->options : ($options[$f->name] ?? []); ?>
                <select id="f_<?= $e($f->name) ?>" name="<?= $e($f->name) ?>">
                    <?php foreach ($opts as $ov => $ol): ?>
                        <option value="<?= $e((string) $ov) ?>" <?= (string) $v === (string) $ov ? 'selected' : '' ?>><?= $e((string) $ol) ?></option>
                    <?php endforeach; ?>
                </select>

            <?php elseif ($f->type === 'boolean'): ?>
                <label class="pnx-check"><input type="checkbox" name="<?= $e($f->name) ?>" value="1" <?= $v ? 'checked' : '' ?>> Yes</label>

            <?php elseif ($f->type === 'password'): ?>
                <input id="f_<?= $e($f->name) ?>" type="password" name="<?= $e($f->name) ?>" autocomplete="new-password"
                       placeholder="<?= $editing ? 'Leave blank to keep current' : '' ?>" <?= (!$editing && $f->required) ? 'required' : '' ?>>

            <?php else: $inputType = $f->type === 'email' ? 'email' : (in_array($f->type, ['money', 'number'], true) ? 'number' : 'text'); ?>
                <input id="f_<?= $e($f->name) ?>" type="<?= $inputType ?>" name="<?= $e($f->name) ?>"
                       <?= $f->type === 'money' ? 'step="0.01"' : '' ?> value="<?= $e((string) $v) ?>" <?= $f->required ? 'required' : '' ?>>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <div class="pnx-form-actions">
        <button type="submit" class="pnx-btn pnx-btn-primary"><?= $editing ? 'Save changes' : 'Create' ?></button>
        <a class="pnx-btn" href="<?= $e($config->url('p=resource&r=' . $resource->key)) ?>">Cancel</a>
    </div>
</form>
