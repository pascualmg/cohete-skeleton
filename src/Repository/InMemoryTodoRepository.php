<?php

namespace App\Repository;

use App\Domain\Todo;
use App\Domain\TodoId;
use App\Domain\TodoRepository;
use Cohete\Bus\Message;
use Cohete\Bus\MessageBus;
use React\Promise\PromiseInterface;
use Rx\Observable;

use function React\Promise\resolve;

class InMemoryTodoRepository implements TodoRepository
{
    /** @var array<string, Todo> */
    private array $todos = [];

    public function __construct(
        private readonly MessageBus $messageBus,
    ) {
    }

    public function findAll(): PromiseInterface
    {
        return Observable::fromArray(array_values($this->todos))
            ->toArray()
            ->toPromise();
    }

    public function findById(TodoId $id): PromiseInterface
    {
        return resolve($this->todos[$id->value] ?? null);
    }

    public function save(Todo $todo): PromiseInterface
    {
        $this->todos[$todo->id->value] = $todo;

        foreach ($todo->pullDomainEvents() as $event) {
            $this->messageBus->publish(
                new Message($event->eventName(), $event->payload())
            );
        }

        return resolve($todo);
    }

    public function delete(TodoId $id): PromiseInterface
    {
        unset($this->todos[$id->value]);
        return resolve(null);
    }
}
