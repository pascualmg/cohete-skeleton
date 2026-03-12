<?php

namespace App\Tests\Domain;

use App\Domain\Event\TodoCreated;
use App\Domain\Todo;
use App\Domain\TodoId;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

class TodoTest extends TestCase
{
    public function testTodoIdV4ReturnsValidUuidFormat(): void
    {
        $id = TodoId::v4();
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $id->value
        );
    }

    public function testTodoIdFromWithValidUuidWorks(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $id = TodoId::from($uuid);
        $this->assertEquals($uuid, $id->value);
    }

    public function testTodoIdFromWithInvalidStringThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TodoId::from('invalid-uuid');
    }

    public function testCreateRecordsDomainEvent(): void
    {
        $id = TodoId::v4();
        $todo = Todo::create($id, 'Test Todo');

        $events = $todo->pullDomainEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(TodoCreated::class, $events[0]);
        $this->assertEquals('domain_event.todo_created', $events[0]->eventName());
        $this->assertEquals($id->value, $events[0]->payload()['id']);
        $this->assertEquals('Test Todo', $events[0]->payload()['title']);
    }

    public function testPullDomainEventsClearsEvents(): void
    {
        $todo = Todo::create(TodoId::v4(), 'Test');
        $todo->pullDomainEvents();

        $this->assertCount(0, $todo->pullDomainEvents());
    }

    public function testReconstituteDoesNotRecordEvents(): void
    {
        $todo = Todo::reconstitute(TodoId::v4(), 'Test', false);
        $this->assertCount(0, $todo->pullDomainEvents());
    }

    public function testTodoToArrayReturnsArrayWithKeysIdTitleCompleted(): void
    {
        $id = TodoId::v4();
        $todo = Todo::reconstitute($id, 'Test Todo', true);

        $this->assertEquals([
            'id' => $id->value,
            'title' => 'Test Todo',
            'completed' => true,
        ], $todo->toArray());
    }

    public function testCreateDefaultsCompletedToFalse(): void
    {
        $todo = Todo::create(TodoId::v4(), 'Test Todo');
        $this->assertFalse($todo->completed);
    }

    public function testWithCompleted(): void
    {
        $todo = Todo::create(TodoId::v4(), 'Test');
        $completed = $todo->withCompleted(true);

        $this->assertTrue($completed->completed);
        $this->assertFalse($todo->completed);
    }

    public function testWithTitle(): void
    {
        $todo = Todo::create(TodoId::v4(), 'Original');
        $renamed = $todo->withTitle('Renamed');

        $this->assertEquals('Renamed', $renamed->title);
        $this->assertEquals('Original', $todo->title);
    }
}
