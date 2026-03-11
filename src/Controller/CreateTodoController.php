<?php

namespace App\Controller;

use App\Domain\Todo;
use App\Domain\TodoId;
use App\Domain\TodoRepository;
use Cohete\Bus\Message;
use Cohete\Bus\MessageBus;
use Cohete\HttpServer\HttpRequestHandler;
use Cohete\HttpServer\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;

class CreateTodoController implements HttpRequestHandler
{
    public function __construct(
        private readonly TodoRepository $todoRepository,
        private readonly MessageBus $messageBus,
    ) {
    }

    public function __invoke(
        ServerRequestInterface $request,
        ?array $routeParams
    ): ResponseInterface|PromiseInterface {
        $body = json_decode($request->getBody()->getContents(), true);
        if (is_null($body)) {
            return JsonResponse::create(400, ['error' => 'invalid json']);
        }

        $title = $body['title'] ?? '';

        if ($title === '') {
            return JsonResponse::create(400, ['error' => 'title is required']);
        }

        $todo = new Todo(
            id: TodoId::v4(),
            title: $title,
        );

        return $this->todoRepository->save($todo)
            ->then(function (Todo $todo) {
                $this->messageBus->publish(
                    new Message('domain_event.todo_created', $todo->toArray())
                );
                return JsonResponse::create(201, $todo->toArray());
            });
    }
}
