<?php

declare(strict_types=1);

namespace Panelix\Auth;

use Panelix\Database\Connection;

/**
 * Reads users from whatever table the host app already has (Restaurant's
 * `employee`, Foodmart's `customer`/admin, ...). The column mapping is supplied
 * in config so no schema changes are required to adopt the CMS.
 */
final class UserRepository
{
    /** @param array{table:string,id:string,username:string,password:string,role:string} $map */
    public function __construct(
        private Connection $db,
        private array $map,
    ) {
    }

    /** @return array<string,mixed>|null */
    public function findByUsername(string $username): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM `{$this->map['table']}` WHERE `{$this->map['username']}` = :u",
            ['u' => $username]
        );
    }

    public function updatePassword(int $id, string $hash): void
    {
        $this->db->execute(
            "UPDATE `{$this->map['table']}` SET `{$this->map['password']}` = :p WHERE `{$this->map['id']}` = :id",
            ['p' => $hash, 'id' => $id]
        );
    }
}
