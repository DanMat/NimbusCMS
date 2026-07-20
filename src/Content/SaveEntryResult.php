<?php

declare(strict_types=1);

namespace Nimbus\Content;

/** Outcome of EntryService::save — either a saved entry id, or validation errors. */
final readonly class SaveEntryResult
{
    /** @param array<string,string> $errors */
    private function __construct(
        public bool $successful,
        public ?int $entryId,
        public array $errors,
        public EntryInput $input,
    ) {
    }

    public static function ok(int $entryId, EntryInput $input): self
    {
        return new self(true, $entryId, [], $input);
    }

    /** @param array<string,string> $errors */
    public static function failed(array $errors, EntryInput $input): self
    {
        return new self(false, null, $errors, $input);
    }
}
