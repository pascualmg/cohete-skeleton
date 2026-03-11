<?php

namespace App\Tests\Controller;

use App\Controller\CreateTodoController;
use App\Domain\Todo;
use App\Domain\TodoRepository;
use Cohete\Bus\Message;
use Cohete\Bus\MessageBus;
use Cohete\HttpServer\JsonResponse;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

class CreateTodoControllerTest extends TestCase
{
    private $repository;
    private $messageBus;
    private $controller;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(TodoRepository::class);
        $this->messageBus = $this->createMock(MessageBus::class);
        $this->controller = new CreateTodoController($this->repository, $this->messageBus);
    }

    public function testEmptyTitleReturns400(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn(json_encode(['title' => '']));
        $request->method('getBody')->willReturn($stream);

        $response = $this->controller->__invoke($request, null);

        $this->assertEquals(400, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals('title is required', $body['error']);
    }

    public function testValidTitleCallsSaveAndReturns201(): void
    {
        $title = 'New Todo';
        $request = $this->createMock(ServerRequestInterface::class);
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn(json_encode(['title' => $title]));
        $request->method('getBody')->willReturn($stream);

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(fn(Todo $todo) => $todo->title === $title))
            ->willReturnCallback(fn(Todo $todo) => resolve($todo));

        $this->messageBus->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Message $message) use ($title) {
                return $message->name === 'domain_event.todo_created' &&
                       $message->payload['title'] === $title;
            }));

        $response = $this->controller->__invoke($request, null);

        if ($response instanceof PromiseInterface) {
            $actualResponse = null;
            $response->then(function ($res) use (&$actualResponse) {
                $actualResponse = $res;
            });
            $response = $actualResponse;
        }

        $this->assertEquals(201, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals($title, $body['title']);
        $this->assertFalse($body['completed']);
        $this->assertNotEmpty($body['id']);
    }
}
