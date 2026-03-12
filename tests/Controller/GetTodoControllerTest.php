<?php

namespace App\Tests\Controller;

use App\Controller\GetTodoController;
use App\Domain\Todo;
use App\Domain\TodoId;
use App\Domain\TodoRepository;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

class GetTodoControllerTest extends TestCase
{
    private $repository;
    private $controller;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(TodoRepository::class);
        $this->controller = new GetTodoController($this->repository);
    }

    public function testReturns200WithTodoWhenFound(): void
    {
        $id = TodoId::v4();
        $todo = Todo::reconstitute($id, 'Found Todo', false);

        $this->repository->expects($this->once())
            ->method('findById')
            ->willReturn(resolve($todo));

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->controller->__invoke($request, ['id' => $id->value]);

        if ($response instanceof PromiseInterface) {
            $actual = null;
            $response->then(function ($r) use (&$actual) { $actual = $r; });
            $response = $actual;
        }

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals('Found Todo', $body['title']);
    }

    public function testReturns404WhenNotFound(): void
    {
        $this->repository->expects($this->once())
            ->method('findById')
            ->willReturn(resolve(null));

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->controller->__invoke($request, ['id' => TodoId::v4()->value]);

        if ($response instanceof PromiseInterface) {
            $actual = null;
            $response->then(function ($r) use (&$actual) { $actual = $r; });
            $response = $actual;
        }

        $this->assertEquals(404, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals('Todo not found', $body['error']);
    }
}
