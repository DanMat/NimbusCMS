<?php

declare(strict_types=1);

namespace Nimbus\Content\FieldTypes;

class EmailType extends TextType
{
    public function type(): string
    {
        return 'email';
    }

    public function label(): string
    {
        return 'Email';
    }

    protected function htmlType(): string
    {
        return 'email';
    }

    public function validate(\Nimbus\Content\Field $field, mixed $value): ?string
    {
        return filter_var((string) $value, FILTER_VALIDATE_EMAIL) !== false ? null : 'Enter a valid email address.';
    }
}
