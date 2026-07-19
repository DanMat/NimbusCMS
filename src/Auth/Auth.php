<?php

declare(strict_types=1);

namespace Panelix\Auth;

use Panelix\Config\CmsConfig;
use Panelix\Database\Connection;
use Panelix\Support\Password;

/**
 * Session-based authentication. Verifies against the host app's user table,
 * transparently upgrades legacy md5 password hashes to bcrypt on login, and
 * exposes role checks the router uses to gate resources.
 */
final class Auth
{
    private const SESSION_KEY = 'panelix_user';

    private UserRepository $users;

    public function __construct(
        Connection $db,
        private CmsConfig $config,
    ) {
        $this->users = new UserRepository($db, $config->users);
    }

    public function attempt(string $username, string $password): bool
    {
        $row = $this->users->findByUsername($username);
        if ($row === null) {
            return false;
        }

        $map    = $this->config->users;
        $stored = (string) ($row[$map['password']] ?? '');

        if (!Password::verify($password, $stored)) {
            return false;
        }

        // Opt-in: upgrade a legacy md5 hash to bcrypt now that we have the
        // plaintext. Best-effort — a failure (e.g. a password column too narrow
        // for a 60-char bcrypt hash) never fails the login, which already
        // succeeded. Off by default so coexisting legacy auth keeps working.
        if ($this->config->upgradePasswords && Password::needsRehash($stored)) {
            try {
                $this->users->updatePassword((int) $row[$map['id']], Password::hash($password));
            } catch (\Throwable) {
                // keep the legacy hash; login still valid
            }
        }

        $_SESSION[self::SESSION_KEY] = [
            'id'       => (int) $row[$map['id']],
            'username' => (string) $row[$map['username']],
            'role'     => (string) ($row[$map['role']] ?? ''),
        ];
        session_regenerate_id(true);

        return true;
    }

    public function logout(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }

    public function check(): bool
    {
        return isset($_SESSION[self::SESSION_KEY]);
    }

    public function user(): ?User
    {
        if (!$this->check()) {
            return null;
        }
        $u = $_SESSION[self::SESSION_KEY];
        return new User((int) $u['id'], (string) $u['username'], (string) $u['role']);
    }

    public function role(): ?string
    {
        return $this->user()?->role;
    }

    public function isAdmin(): bool
    {
        return in_array($this->role(), $this->config->adminRoles, true);
    }
}
