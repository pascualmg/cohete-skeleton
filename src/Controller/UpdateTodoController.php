<?php

namespace App\Controller;

use App\Domain\Todo;
use App\Domain\TodoId;
use App\Domain\TodoRepository;
use Cohete\HttpServer\HttpRequestHandler;
use Cohete\HttpServer\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use Throwable;

class UpdateTodoController implements HttpRequestHandler
{
    public function __construct(
        private readonly TodoRepository $todoRepository
    ) {
    }

    public function __invoke(
        ServerRequestInterface $request,
        ?array $routeParams
    ): ResponseInterface|PromiseInterface {
        $id = $routeParams['id'] ?? '';

        try {
            $todoId = TodoId::from($id);
        } catch (Throwable) {
            return JsonResponse::create(400, ['error' => 'invalid id format']);
        }

        $body = json_decode($request->getBody()->getContents(), true);
        if (is_null($body)) {
            return JsonResponse::create(400, ['error' => 'invalid json']);
        }

        $title = $body['title'] ?? '';
        $completed = $body['completed'] ?? false;

        if ($title === '') {
            return JsonResponse::create(400, ['error' => 'title is required']);
        }

        return $this->todoRepository->findById($todoId)
            ->then(function (?Todo $existingTodo) use ($todoId, $title, $completed) {
                if (is_null($existingTodo)) {
                    return JsonResponse::notFound('todo');
                }

                $updatedTodo = new Todo(
                    id: $todoId,
                    title: $title,
                    completed: (bool)$completed,
                );

                return $this->todoRepository->save($updatedTodo)
                    ->then(fn(Todo $todo) => JsonResponse::withPayload($todo->toArray()));
            });
    }
}
