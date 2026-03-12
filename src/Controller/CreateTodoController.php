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

class CreateTodoController implements HttpRequestHandler
{
    public function __construct(
        private readonly TodoRepository $todoRepository,
    ) {
    }

    public function __invoke(
        ServerRequestInterface $request,
        ?array $routeParams
    ): ResponseInterface|PromiseInterface {
        $body = json_decode($request->getBody()->getContents(), true);
        $title = $body['title'] ?? '';

        if (empty($title)) {
            return JsonResponse::create(400, ['error' => 'title is required']);
        }

        $todo = Todo::create(
            id: TodoId::v4(),
            title: $title,
        );

        return $this->todoRepository->save($todo)
            ->then(function (Todo $todo) {
                return JsonResponse::create(201, $todo->toArray());
            });
    }
}
