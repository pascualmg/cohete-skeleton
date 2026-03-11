<?php

namespace App\Tests\Repository;

use App\Domain\Todo;
use App\Domain\TodoId;
use App\Repository\InMemoryTodoRepository;
use PHPUnit\Framework\TestCase;

class InMemoryTodoRepositoryTest extends TestCase
{
    private InMemoryTodoRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryTodoRepository();
    }

    public function testFindAllReturnsEmptyArrayWhenNoTodos(): void
    {
        $result = null;
        $this->repository->findAll()->then(function ($v) use (&$result) {
            $result = $v;
        });

        $this->assertEquals([], $result);
    }

    public function testSaveAndFindAll(): void
    {
        $todo = new Todo(TodoId::v4(), 'Test Todo');
        $this->repository->save($todo);

        $result = null;
        $this->repository->findAll()->then(function ($v) use (&$result) {
            $result = $v;
        });

        $this->assertCount(1, $result);
        $this->assertSame($todo, $result[0]);
    }

    public function testFindById(): void
    {
        $todo = new Todo(TodoId::v4(), 'Test Todo');
        $this->repository->save($todo);

        $result = null;
        $this->repository->findById($todo->id)->then(function ($v) use (&$result) {
            $result = $v;
        });

        $this->assertSame($todo, $result);
    }

    public function testFindByIdReturnsNullForMissingId(): void
    {
        $result = 'not null';
        $this->repository->findById(TodoId::v4())->then(function ($v) use (&$result) {
            $result = $v;
        });

        $this->assertNull($result);
    }

    public function testSaveReturnsSavedTodo(): void
    {
        $todo = new Todo(TodoId::v4(), 'Test Todo');
        $result = null;
        $this->repository->save($todo)->then(function ($v) use (&$result) {
            $result = $v;
        });

        $this->assertSame($todo, $result);
    }

    public function testDelete(): void
    {
        $todo = new Todo(TodoId::v4(), 'Test Todo');
        $this->repository->save($todo);

        $this->repository->delete($todo->id);

        $result = null;
        $this->repository->findAll()->then(function ($v) use (&$result) {
            $result = $v;
        });

        $this->assertEquals([], $result);
    }

    public function testDeleteNonExistentIdDoesNotThrow(): void
    {
        $id = TodoId::v4();
        $resolved = false;
        $this->repository->delete($id)->then(function () use (&$resolved) {
            $resolved = true;
        });

        $this->assertTrue($resolved);
    }
}
