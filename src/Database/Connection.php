<?php

declare(strict_types=1);

namespace Panelix\Database;

use PDO;

/**
 * The DB layer: a thin, OO facade over a single PDO connection. Every query is
 * parameterised, so this is the only place SQL is executed. Lazily connects so
 * building the CMS object graph doesn't touch the database.
 */
final class Connection
{
    private ?PDO $pdo = null;

    /** @param array{host?:string,port?:int,name?:string,user?:string,pass?:string,charset?:string} $config */
    public function __construct(private array $config)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $c   = $this->config;
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $c['host'] ?? '127.0.0.1',
                $c['port'] ?? 3306,
                $c['name'] ?? '',
                $c['charset'] ?? 'utf8mb4'
            );
            $this->pdo = new PDO($dsn, $c['user'] ?? 'root', $c['pass'] ?? '', [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return $this->pdo;
    }

    /** @return array<int,array<string,mixed>> */
    public function select(string $sql, array $params = []): array
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    /** @return array<string,mixed>|null */
    public function selectOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /** Run an INSERT and return the new auto-increment id. */
    public function insert(string $sql, array $params = []): int
    {
        $this->execute($sql, $params);
        return (int) $this->pdo()->lastInsertId();
    }
}
