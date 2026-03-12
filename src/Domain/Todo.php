<?php

namespace App\Domain;

use App\Domain\Event\TodoCreated;
use Cohete\DDD\Aggregate\AggregateRoot;
use Cohete\DDD\ValueObject\UuidValueObject;

class TodoId extends UuidValueObject {}

class Todo extends AggregateRoot
{
    private function __construct(
        public readonly TodoId $id,
        public readonly string $title,
        public readonly bool $completed = false,
    ) {
    }

    public static function create(TodoId $id, string $title): self
    {
        $todo = new self($id, $title);
        $todo->record(new TodoCreated($id->value, $title));
        return $todo;
    }

    public static function reconstitute(TodoId $id, string $title, bool $completed): self
    {
        return new self($id, $title, $completed);
    }

    public function withCompleted(bool $completed): self
    {
        return new self($this->id, $this->title, $completed);
    }

    public function withTitle(string $title): self
    {
        return new self($this->id, $title, $this->completed);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id->value,
            'title' => $this->title,
            'completed' => $this->completed,
        ];
    }
}
