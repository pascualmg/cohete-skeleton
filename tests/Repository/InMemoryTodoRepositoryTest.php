<?php

namespace App\Tests\Repository;

use App\Domain\Todo;
use App\Domain\TodoId;
use App\Repository\InMemoryTodoRepository;
use Cohete\Bus\Message;
use Cohete\Bus\MessageBus;
use PHPUnit\Framework\TestCase;

class InMemoryTodoRepositoryTest extends TestCase
{
    private InMemoryTodoRepository $repository;
    private MessageBus $messageBus;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBus::class);
        $this->repository = new InMemoryTodoRepository($this->messageBus);
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
        $todo = Todo::reconstitute(TodoId::v4(), 'Test Todo', false);
        $this->repository->save($todo);

        $result = null;
        $this->repository->findAll()->then(function ($v) use (&$result) {
            $result = $v;
        });

        $this->assertCount(1, $result);
        $this->assertSame($todo, $result[0]);
    }

    public function testSavePublishesDomainEvents(): void
    {
        $todo = Todo::create(TodoId::v4(), 'Event Todo');

        $this->messageBus->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Message $message) {
                return $message->name === 'domain_event.todo_created'
                    && $message->payload['title'] === 'Event Todo';
            }));

        $this->repository->save($todo);
    }

    public function testSaveReconstitutedDoesNotPublishEvents(): void
    {
        $todo = Todo::reconstitute(TodoId::v4(), 'No Events', false);

        $this->messageBus->expects($this->never())
            ->method('publish');

        $this->repository->save($todo);
    }

    public function testFindById(): void
    {
        $todo = Todo::reconstitute(TodoId::v4(), 'Test Todo', false);
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
        $todo = Todo::reconstitute(TodoId::v4(), 'Test Todo', false);
        $result = null;
        $this->repository->save($todo)->then(function ($v) use (&$result) {
            $result = $v;
        });

        $this->assertSame($todo, $result);
    }

    public function testDelete(): void
    {
        $todo = Todo::reconstitute(TodoId::v4(), 'Test Todo', false);
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
