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
        $id = TodoId::from($routeParams['id']);
        $body = json_decode($request->getBody()->getContents(), true);

        return $this->todoRepository->findById($id)
            ->then(function (?Todo $todo) use ($body) {
                if ($todo === null) {
                    return JsonResponse::create(404, ['error' => 'Todo not found']);
                }

                $updatedTodo = $todo;
                if (isset($body['title'])) {
                    $updatedTodo = $updatedTodo->withTitle($body['title']);
                }
                if (isset($body['completed'])) {
                    $updatedTodo = $updatedTodo->withCompleted($body['completed']);
                }

                return $this->todoRepository->save($updatedTodo)
                    ->then(fn(Todo $savedTodo) => JsonResponse::create(200, $savedTodo->toArray()));
            });
    }
}
