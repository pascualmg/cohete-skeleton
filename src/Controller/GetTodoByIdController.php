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

class GetTodoByIdController implements HttpRequestHandler
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

        return $this->todoRepository->findById($todoId)
            ->then(function (?Todo $todo) {
                if (is_null($todo)) {
                    return JsonResponse::notFound('todo');
                }

                return JsonResponse::withPayload($todo->toArray());
            });
    }
}
