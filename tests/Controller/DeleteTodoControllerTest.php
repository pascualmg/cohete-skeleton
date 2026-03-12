<?php

namespace App\Tests\Controller;

use App\Controller\DeleteTodoController;
use App\Domain\Todo;
use App\Domain\TodoId;
use App\Domain\TodoRepository;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

class DeleteTodoControllerTest extends TestCase
{
    private $repository;
    private $controller;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(TodoRepository::class);
        $this->controller = new DeleteTodoController($this->repository);
    }

    public function testReturns404WhenTodoNotFound(): void
    {
        $this->repository->method('findById')->willReturn(resolve(null));

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->controller->__invoke($request, ['id' => TodoId::v4()->value]);

        if ($response instanceof PromiseInterface) {
            $actual = null;
            $response->then(function ($r) use (&$actual) { $actual = $r; });
            $response = $actual;
        }

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testReturns204WhenDeleted(): void
    {
        $id = TodoId::v4();
        $todo = Todo::reconstitute($id, 'To Delete', false);

        $this->repository->method('findById')->willReturn(resolve($todo));
        $this->repository->method('delete')->willReturn(resolve(null));

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->controller->__invoke($request, ['id' => $id->value]);

        if ($response instanceof PromiseInterface) {
            $actual = null;
            $response->then(function ($r) use (&$actual) { $actual = $r; });
            $response = $actual;
        }

        $this->assertEquals(204, $response->getStatusCode());
    }
}
