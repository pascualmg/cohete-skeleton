<?php

namespace App\Controller;

use App\Domain\TodoId;
use App\Domain\TodoRepository;
use App\Domain\Todo;
use Cohete\HttpServer\HttpRequestHandler;
use Cohete\HttpServer\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Promise\PromiseInterface;

class DeleteTodoController implements HttpRequestHandler
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

        return $this->todoRepository->findById($id)
            ->then(function (?Todo $todo) use ($id) {
                if ($todo === null) {
                    return JsonResponse::create(404, ['error' => 'Todo not found']);
                }

                return $this->todoRepository->delete($id)
                    ->then(fn() => new Response(204));
            });
    }
}
