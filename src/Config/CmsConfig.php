<?php

declare(strict_types=1);

namespace Panelix\Config;

use Panelix\Resource\Resource;

/**
 * Everything the host app tells Panelix: how to reach the database, where users
 * live, which resources to manage, and a little branding. This is the entire
 * public surface an app configures.
 */
final class CmsConfig
{
    /**
     * @param array<string,mixed>                                                    $db        PDO connection settings
     * @param array{table:string,id:string,username:string,password:string,role:string} $users   user-table column map
     * @param Resource[]                                                             $resources managed entities
     * @param string[]                                                               $adminRoles roles allowed everywhere
     */
    public function __construct(
        public readonly array $db,
        public readonly array $users,
        public readonly array $resources,
        public readonly string $brand = 'Panelix',
        public readonly string $basePath = '',
        public readonly array $adminRoles = ['admin'],
        // Rewrite legacy md5 password hashes to bcrypt on login. Off by default:
        // a legacy app that still does its own md5-based auth would break if we
        // changed the stored hash. Turn on only once the app authenticates
        // exclusively through Panelix (and the password column fits 60+ chars).
        public readonly bool $upgradePasswords = false,
    ) {
    }

    public function resource(string $key): ?Resource
    {
        foreach ($this->resources as $resource) {
            if ($resource->key === $key) {
                return $resource;
            }
        }
        return null;
    }

    /**
     * Resources the given role may see in the nav.
     *
     * @return Resource[]
     */
    public function resourcesFor(?string $role): array
    {
        $admin = in_array($role, $this->adminRoles, true);
        return array_values(array_filter(
            $this->resources,
            static fn (Resource $r): bool => $admin || $r->allowedFor($role)
        ));
    }

    public function url(string $query = ''): string
    {
        $base = $this->basePath !== '' ? $this->basePath : '';
        return $base . '/' . ($query !== '' ? '?' . $query : '');
    }
}
