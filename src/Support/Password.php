<?php

declare(strict_types=1);

namespace Panelix\Support;

/**
 * Password hashing that upgrades legacy hashes transparently.
 *
 * Old PHP apps (both target repos) store bare md5() password hashes. We verify
 * those, but hash new/changed passwords with password_hash(), and report when a
 * stored hash should be re-hashed on next successful login.
 */
final class Password
{
    public static function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_DEFAULT);
    }

    public static function verify(string $plain, string $stored): bool
    {
        // Modern hash?
        if (self::isModern($stored)) {
            return password_verify($plain, $stored);
        }
        // Legacy bare md5.
        return hash_equals(strtolower($stored), md5($plain));
    }

    /** True when a stored hash is legacy and should be re-hashed after login. */
    public static function needsRehash(string $stored): bool
    {
        return !self::isModern($stored) || password_needs_rehash($stored, PASSWORD_DEFAULT);
    }

    private static function isModern(string $stored): bool
    {
        // bcrypt/argon hashes start with $2y$, $argon2i$, etc.
        return str_starts_with($stored, '$');
    }
}
