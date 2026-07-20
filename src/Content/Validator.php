<?php

declare(strict_types=1);

namespace Nimbus\Content;

/**
 * Validates an entry's field values against its collection. Required/empty is
 * handled here centrally; type-specific rules (format, choice membership) are
 * delegated to each FieldType::validate() — so a plugin's field type validates
 * itself with no changes here.
 */
final class Validator
{
    public function __construct(private FieldTypeRegistry $types)
    {
    }

    /**
     * @param array<string,mixed> $values normalized field values keyed by handle
     * @return array<string,string> handle => error message (empty = valid)
     */
    public function validate(Collection $collection, array $values): array
    {
        $errors = [];
        foreach ($collection->fields as $field) {
            $value = $values[$field->handle] ?? null;

            if ($this->isEmpty($value)) {
                if ($field->required) {
                    $errors[$field->handle] = $field->label . ' is required.';
                }
                continue;
            }

            $error = $this->types->get($field->type)->validate($field, $value);
            if ($error !== null) {
                $errors[$field->handle] = $error;
            }
        }
        return $errors;
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
