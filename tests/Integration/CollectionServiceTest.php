<?php

declare(strict_types=1);

namespace Nimbus\Tests\Integration;

use Nimbus\Content\CollectionRepository;
use Nimbus\Content\CollectionService;
use Nimbus\Database\Connection;

final class CollectionServiceTest extends IntegrationTestCase
{
    private CollectionService $service;
    private CollectionRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo    = new CollectionRepository($this->db);
        $this->service = new CollectionService($this->db, $this->repo);
    }

    private function make(string $handle, array $fields = []): int
    {
        return $this->service->create($handle, ucfirst($handle), '#', '', ['kind' => 'collection', 'permissions' => ['manage' => []]], $fields);
    }

    public function test_collection_handle_is_unique(): void
    {
        $this->make('posts');
        try {
            $this->make('posts');
            self::fail('expected a duplicate-key exception');
        } catch (\PDOException $e) {
            self::assertTrue(Connection::isDuplicateKey($e));
        }
    }

    public function test_field_handle_is_unique_within_a_collection(): void
    {
        $id = $this->make('posts', [
            ['handle' => 'body', 'label' => 'Body', 'type' => 'text', 'required' => false, 'options' => []],
        ]);
        $this->expectException(\PDOException::class);
        $this->db->execute(
            "INSERT INTO nb_fields (collection_id, handle, label, type, required, sort, created_at) VALUES (:c, 'body', 'Body 2', 'text', 0, 1, NOW())",
            ['c' => $id]
        );
    }

    public function test_collection_and_fields_roll_back_together(): void
    {
        // A field label longer than VARCHAR(120) fails under strict mode mid-transaction.
        try {
            $this->service->create('rollbacktest', 'RB', '#', '', ['kind' => 'collection', 'permissions' => ['manage' => []]], [
                ['handle' => 'f', 'label' => str_repeat('x', 300), 'type' => 'text', 'required' => false, 'options' => []],
            ]);
            self::fail('expected the field insert to fail');
        } catch (\Throwable) {
            // the collection insert must have rolled back with the failed field insert
            self::assertNull($this->repo->findByHandle('rollbacktest'));
        }
    }
}
