<?php

namespace App\Domain\Event;

use Cohete\DDD\Event\DomainEvent;

class TodoCreated implements DomainEvent
{
    public function __construct(
        private readonly string $todoId,
        private readonly string $title,
        private readonly \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {
    }

    public function eventName(): string
    {
        return 'domain_event.todo_created';
    }

    public function payload(): array
    {
        return [
            'id' => $this->todoId,
            'title' => $this->title,
        ];
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
