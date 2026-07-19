<?php

declare(strict_types=1);

namespace Panelix\Resource;

/**
 * One field of a resource. Declared with a named constructor (Field::money(...))
 * that fixes its input type + rendering; fluent setters tweak visibility/rules.
 * The controller/view read $type to decide how to render and cast the value.
 */
final class Field
{
    /** @param array<string,mixed> $options select options or belongsTo config */
    private function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly string $type,
        public array $options = [],
        public bool $inList = true,
        public bool $inForm = true,
        public bool $required = false,
    ) {
    }

    public static function text(string $name, string $label): self
    {
        return new self($name, $label, 'text');
    }

    public static function textarea(string $name, string $label): self
    {
        return new self($name, $label, 'textarea');
    }

    public static function number(string $name, string $label): self
    {
        return new self($name, $label, 'number');
    }

    public static function money(string $name, string $label): self
    {
        return new self($name, $label, 'money');
    }

    public static function email(string $name, string $label): self
    {
        return new self($name, $label, 'email');
    }

    public static function boolean(string $name, string $label): self
    {
        return new self($name, $label, 'boolean');
    }

    /** Password field: write-only (blank on edit keeps the current value). */
    public static function password(string $name, string $label): self
    {
        return new self($name, $label, 'password', inList: false);
    }

    /** @param array<string,string> $options value => label */
    public static function select(string $name, string $label, array $options): self
    {
        return new self($name, $label, 'select', $options);
    }

    /** Foreign key rendered as a dropdown of $table.$display keyed by $table.$key. */
    public static function belongsTo(string $name, string $label, string $table, string $key, string $display): self
    {
        return new self($name, $label, 'belongsTo', ['table' => $table, 'key' => $key, 'display' => $display]);
    }

    public function required(bool $value = true): self
    {
        $this->required = $value;
        return $this;
    }

    public function hideFromList(): self
    {
        $this->inList = false;
        return $this;
    }

    public function hideFromForm(): self
    {
        $this->inForm = false;
        return $this;
    }
}
