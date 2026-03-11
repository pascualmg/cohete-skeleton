<?php

namespace App\Tests\Domain;

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

    public function testTodoToArrayReturnsArrayWithKeysIdTitleCompleted(): void
    {
        $id = TodoId::v4();
        $title = 'Test Todo';
        $completed = true;
        $todo = new Todo($id, $title, $completed);

        $array = $todo->toArray();

        $this->assertEquals([
            'id' => $id->value,
            'title' => $title,
            'completed' => $completed,
        ], $array);
    }

    public function testCompletedDefaultsToFalse(): void
    {
        $todo = new Todo(TodoId::v4(), 'Test Todo');
        $this->assertFalse($todo->completed);
        
        $array = $todo->toArray();
        $this->assertFalse($array['completed']);
    }

    public function testTodoWithCompletedTrue(): void
    {
        $todo = new Todo(TodoId::v4(), 'Test Todo', true);
        $this->assertTrue($todo->completed);
        
        $array = $todo->toArray();
        $this->assertTrue($array['completed']);
    }
}
