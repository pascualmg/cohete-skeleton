<?php

namespace App\Controller;

use App\Domain\TodoRepository;
use Cohete\HttpServer\HttpRequestHandler;
use Cohete\HttpServer\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;

class ListTodosController implements HttpRequestHandler
{
    public function __construct(
        private readonly TodoRepository $todoRepository
    ) {
    }

    public function __invoke(
        ServerRequestInterface $request,
        ?array $routeParams
    ): ResponseInterface|PromiseInterface {
        return $this->todoRepository->findAll()
            ->then(function (array $todos) {
                return JsonResponse::withPayload(
                    array_map(fn($todo) => $todo->toArray(), $todos)
                );
            });
    }
}
