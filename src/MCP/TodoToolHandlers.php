<?php

namespace App\MCP;

use App\Domain\Todo;
use App\Domain\TodoId;
use App\Domain\TodoRepository;
use PhpMcp\Server\Attributes\McpTool;

use function React\Async\await;

class TodoToolHandlers
{
    public function __construct(
        private readonly TodoRepository $todoRepository,
    ) {
    }

    /**
     * List all todos with id, title and completed status.
     */
    #[McpTool(name: 'list_todos')]
    public function listTodos(): array
    {
        $todos = await($this->todoRepository->findAll());

        return array_map(fn (Todo $t) => $t->toArray(), $todos);
    }

    /**
     * Get a single todo by its UUID.
     */
    #[McpTool(name: 'get_todo')]
    public function getTodo(string $id): array
    {
        $todo = await($this->todoRepository->findById(TodoId::from($id)));

        if ($todo === null) {
            return ['error' => "Todo not found: $id"];
        }

        return $todo->toArray();
    }

    /**
     * Create a new todo. Returns the created todo with its generated UUID.
     */
    #[McpTool(name: 'create_todo')]
    public function createTodo(string $title): array
    {
        if (empty(trim($title))) {
            return ['error' => 'title is required'];
        }

        $todo = Todo::create(TodoId::v4(), $title);
        await($this->todoRepository->save($todo));

        return $todo->toArray();
    }

    /**
     * Update a todo. Pass title and/or completed to change.
     */
    #[McpTool(name: 'update_todo')]
    public function updateTodo(string $id, string $title = '', bool $completed = false): array
    {
        $todo = await($this->todoRepository->findById(TodoId::from($id)));

        if ($todo === null) {
            return ['error' => "Todo not found: $id"];
        }

        if (!empty($title)) {
            $todo = $todo->withTitle($title);
        }
        $todo = $todo->withCompleted($completed);

        await($this->todoRepository->save($todo));

        return $todo->toArray();
    }

    /**
     * Delete a todo by its UUID.
     */
    #[McpTool(name: 'delete_todo')]
    public function deleteTodo(string $id): array
    {
        $todo = await($this->todoRepository->findById(TodoId::from($id)));

        if ($todo === null) {
            return ['error' => "Todo not found: $id"];
        }

        await($this->todoRepository->delete(TodoId::from($id)));

        return ['deleted' => true, 'id' => $id];
    }
}
