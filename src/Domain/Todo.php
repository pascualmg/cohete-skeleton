<?php

namespace App\Domain;

use Cohete\DDD\ValueObject\UuidValueObject;

class TodoId extends UuidValueObject {}

class Todo
{
    public function __construct(
        public readonly TodoId $id,
        public readonly string $title,
        public readonly bool $completed = false,
    ) {
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
