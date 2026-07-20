<?php

declare(strict_types=1);

namespace Nimbus\Content;

/**
 * Immutable, normalized input for an entry write. The controller builds this
 * from the request; EntryService is the only thing that reads it. Kept small
 * and specific on purpose — not a generic DTO framework.
 */
final readonly class EntryInput
{
    /** @param array<string,mixed> $values normalized field values keyed by handle */
    public function __construct(
        public string $title,
        public string $slug,
        public string $status,
        public array $values,
    ) {
    }
}
