<?php

namespace App\Tests\Controller;

use App\Controller\UpdateTodoController;
use App\Domain\Todo;
use App\Domain\TodoId;
use App\Domain\TodoRepository;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

class UpdateTodoControllerTest extends TestCase
{
    private $repository;
    private $controller;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(TodoRepository::class);
        $this->controller = new UpdateTodoController($this->repository);
    }

    public function testReturns404WhenTodoNotFound(): void
    {
        $this->repository->method('findById')->willReturn(resolve(null));

        $request = $this->createMock(ServerRequestInterface::class);
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn(json_encode(['title' => 'Updated']));
        $request->method('getBody')->willReturn($stream);

        $response = $this->controller->__invoke($request, ['id' => TodoId::v4()->value]);

        if ($response instanceof PromiseInterface) {
            $actual = null;
            $response->then(function ($r) use (&$actual) { $actual = $r; });
            $response = $actual;
        }

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUpdatesTitle(): void
    {
        $id = TodoId::v4();
        $todo = Todo::reconstitute($id, 'Original', false);

        $this->repository->method('findById')->willReturn(resolve($todo));
        $this->repository->method('save')
            ->willReturnCallback(fn(Todo $t) => resolve($t));

        $request = $this->createMock(ServerRequestInterface::class);
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn(json_encode(['title' => 'Updated']));
        $request->method('getBody')->willReturn($stream);

        $response = $this->controller->__invoke($request, ['id' => $id->value]);

        if ($response instanceof PromiseInterface) {
            $actual = null;
            $response->then(function ($r) use (&$actual) { $actual = $r; });
            $response = $actual;
        }

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals('Updated', $body['title']);
        $this->assertFalse($body['completed']);
    }

    public function testUpdatesCompleted(): void
    {
        $id = TodoId::v4();
        $todo = Todo::reconstitute($id, 'Task', false);

        $this->repository->method('findById')->willReturn(resolve($todo));
        $this->repository->method('save')
            ->willReturnCallback(fn(Todo $t) => resolve($t));

        $request = $this->createMock(ServerRequestInterface::class);
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn(json_encode(['completed' => true]));
        $request->method('getBody')->willReturn($stream);

        $response = $this->controller->__invoke($request, ['id' => $id->value]);

        if ($response instanceof PromiseInterface) {
            $actual = null;
            $response->then(function ($r) use (&$actual) { $actual = $r; });
            $response = $actual;
        }

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals('Task', $body['title']);
        $this->assertTrue($body['completed']);
    }
}
