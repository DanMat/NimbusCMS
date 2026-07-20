<?php

declare(strict_types=1);

namespace Nimbus\Content;

/**
 * The field contract. Everything the admin, validator and API need to handle a
 * field is defined here, so adding a type (or shipping one in a plugin) is a
 * single class registered with the FieldTypeRegistry — no core changes.
 *
 * The lifecycle of a value:
 *   raw request  --normalize()-->  stored (entry JSON)  --toApi()-->  API output
 *                                        |
 *                                    validate()  (storage value -> error|null)
 */
interface FieldType
{
    /** Stable machine key stored in nb_fields.type, e.g. "text". */
    public function type(): string;

    /** Human label shown in the field-type picker. */
    public function label(): string;

    /** Render the form control for an entry, pre-filled with $value. */
    public function renderInput(Field $field, mixed $value): string;

    /** Render the value as a cell in the entry list. */
    public function renderCell(Field $field, mixed $value): string;

    /** Convert a raw submitted value into what gets stored in the entry JSON. */
    public function normalize(mixed $input): mixed;

    /**
     * Validate a stored (normalized) value. Return null when valid, or a
     * human-readable error message. Empty/required is handled centrally by the
     * Validator; this is for type-specific rules (format, choice membership…).
     */
    public function validate(Field $field, mixed $value): ?string;

    /**
     * Serialize a stored value for the public API. Keeps the API contract
     * independent of the internal JSON storage shape.
     */
    public function toApi(Field $field, mixed $value): mixed;

    /** Whether this type is configured with a list of choices (like select). */
    public function hasChoices(): bool;
}
