<?php

declare(strict_types=1);

namespace Panelix\Auth;

/** The signed-in user, as stored in the session. */
final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $role,
    ) {
    }

    public function initial(): string
    {
        return mb_strtoupper(mb_substr($this->username, 0, 1));
    }
}
