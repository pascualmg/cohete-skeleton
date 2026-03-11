<?php

namespace App\Domain;

use React\Promise\PromiseInterface;

interface TodoRepository
{
    /** @return PromiseInterface<Todo[]> */
    public function findAll(): PromiseInterface;

    /** @return PromiseInterface<Todo|null> */
    public function findById(TodoId $id): PromiseInterface;

    /** @return PromiseInterface<Todo> */
    public function save(Todo $todo): PromiseInterface;

    /** @return PromiseInterface<void> */
    public function delete(TodoId $id): PromiseInterface;
}
