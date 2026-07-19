<?php

declare(strict_types=1);

namespace Panelix\Resource;

use Panelix\Database\Connection;

/**
 * Generic CRUD over any resource's table. Table/column identifiers come from
 * developer-authored Resource definitions (never request data); all values are
 * bound as parameters. One repository serves every entity in the CMS.
 */
final class EntityRepository
{
    public function __construct(private Connection $db)
    {
    }

    /** @return array<int,array<string,mixed>> */
    public function all(Resource $resource, ?string $search = null): array
    {
        $sql    = "SELECT * FROM `{$resource->table}`";
        $params = [];

        if ($search !== null && trim($search) !== '') {
            $textCols = [];
            foreach ($resource->listFields() as $field) {
                if (in_array($field->type, ['text', 'textarea', 'email'], true)) {
                    $textCols[] = $field->name;
                }
            }
            if ($textCols !== []) {
                $clauses = [];
                foreach ($textCols as $i => $col) {
                    $clauses[]     = "`{$col}` LIKE :s{$i}";
                    $params["s{$i}"] = '%' . $search . '%';
                }
                $sql .= ' WHERE ' . implode(' OR ', $clauses);
            }
        }

        $sql .= " ORDER BY `{$resource->pk}` DESC";
        return $this->db->select($sql, $params);
    }

    /** @return array<string,mixed>|null */
    public function find(Resource $resource, int $id): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM `{$resource->table}` WHERE `{$resource->pk}` = :id",
            ['id' => $id]
        );
    }

    /** @param array<string,mixed> $data */
    public function create(Resource $resource, array $data): int
    {
        if ($data === []) {
            return 0;
        }
        $cols         = array_keys($data);
        $columnList   = implode(', ', array_map(static fn (string $c): string => "`{$c}`", $cols));
        $placeholders = implode(', ', array_map(static fn (string $c): string => ":{$c}", $cols));

        return $this->db->insert(
            "INSERT INTO `{$resource->table}` ({$columnList}) VALUES ({$placeholders})",
            $data
        );
    }

    /** @param array<string,mixed> $data */
    public function update(Resource $resource, int $id, array $data): void
    {
        if ($data === []) {
            return;
        }
        $assignments = implode(', ', array_map(static fn (string $c): string => "`{$c}` = :{$c}", array_keys($data)));
        $data['__id'] = $id;

        $this->db->execute(
            "UPDATE `{$resource->table}` SET {$assignments} WHERE `{$resource->pk}` = :__id",
            $data
        );
    }

    public function delete(Resource $resource, int $id): void
    {
        $this->db->execute("DELETE FROM `{$resource->table}` WHERE `{$resource->pk}` = :id", ['id' => $id]);
    }

    public function count(Resource $resource): int
    {
        $row = $this->db->selectOne("SELECT COUNT(*) AS c FROM `{$resource->table}`");
        return (int) ($row['c'] ?? 0);
    }

    /**
     * value => label options for a belongsTo dropdown.
     *
     * @return array<string,string>
     */
    public function options(string $table, string $key, string $display): array
    {
        $rows = $this->db->select("SELECT `{$key}` AS k, `{$display}` AS d FROM `{$table}` ORDER BY `{$display}`");
        $out  = [];
        foreach ($rows as $row) {
            $out[(string) $row['k']] = (string) $row['d'];
        }
        return $out;
    }
}
