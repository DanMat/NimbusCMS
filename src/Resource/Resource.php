<?php

declare(strict_types=1);

namespace Panelix\Resource;

/**
 * A declarative description of one manageable entity: which table it maps to,
 * its fields, and which roles may manage it. Everything the admin UI needs to
 * generate list/create/edit/delete screens comes from here — no per-entity code.
 */
final class Resource
{
    /** @var Field[] */
    private array $fields = [];

    /** @var string[] roles allowed to manage this resource ([] = any signed-in user) */
    private array $roles = [];

    private ?string $icon = null;

    private function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $table,
        public readonly string $pk,
    ) {
    }

    public static function make(string $key, string $label, string $table, string $pk = 'id'): self
    {
        return new self($key, $label, $table, $pk);
    }

    public function field(Field $field): self
    {
        $this->fields[] = $field;
        return $this;
    }

    /** @param string[] $roles */
    public function roles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;
        return $this;
    }

    public function iconChar(): string
    {
        return $this->icon ?? mb_strtoupper(mb_substr($this->label, 0, 1));
    }

    /** @return Field[] */
    public function fields(): array
    {
        return $this->fields;
    }

    /** @return Field[] columns shown in the list table */
    public function listFields(): array
    {
        return array_values(array_filter($this->fields, static fn (Field $f): bool => $f->inList));
    }

    /** @return Field[] inputs shown in the create/edit form */
    public function formFields(): array
    {
        return array_values(array_filter($this->fields, static fn (Field $f): bool => $f->inForm));
    }

    public function allowedFor(?string $role): bool
    {
        return $this->roles === [] || ($role !== null && in_array($role, $this->roles, true));
    }
}
