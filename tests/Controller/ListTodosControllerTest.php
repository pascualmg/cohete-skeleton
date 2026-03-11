<?php

namespace App\Tests\Controller;

use App\Controller\ListTodosController;
use App\Domain\Todo;
use App\Domain\TodoId;
use App\Domain\TodoRepository;
use Cohete\HttpServer\JsonResponse;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

class ListTodosControllerTest extends TestCase
{
    public function testInvokeReturns200WithJsonEmptyArray(): void
    {
        $repository = $this->createMock(TodoRepository::class);
        $repository->expects($this->once())
            ->method('findAll')
            ->willReturn(resolve([]));

        $controller = new ListTodosController($repository);
        $request = $this->createMock(ServerRequestInterface::class);

        $response = $controller->__invoke($request, null);

        if ($response instanceof PromiseInterface) {
            $actualResponse = null;
            $response->then(function ($res) use (&$actualResponse) {
                $actualResponse = $res;
            });
            $response = $actualResponse;
        }

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertEquals('[]', (string) $response->getBody());
    }

    public function testInvokeReturns200WithPrePopulatedTodos(): void
    {
        $todo = new Todo(TodoId::from('550e8400-e29b-41d4-a716-446655440000'), 'Test Todo');
        $repository = $this->createMock(TodoRepository::class);
        $repository->expects($this->once())
            ->method('findAll')
            ->willReturn(resolve([$todo]));

        $controller = new ListTodosController($repository);
        $request = $this->createMock(ServerRequestInterface::class);

        $response = $controller->__invoke($request, null);

        if ($response instanceof PromiseInterface) {
            $actualResponse = null;
            $response->then(function ($res) use (&$actualResponse) {
                $actualResponse = $res;
            });
            $response = $actualResponse;
        }

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(
            json_encode([$todo->toArray()]),
            (string) $response->getBody()
        );
    }
}
