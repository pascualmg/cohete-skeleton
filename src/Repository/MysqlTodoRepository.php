<?php

namespace App\Repository;

use App\Domain\Todo;
use App\Domain\TodoId;
use App\Domain\TodoRepository;
use Cohete\Bus\Message;
use Cohete\Bus\MessageBus;
use React\Mysql\MysqlClient;
use React\Mysql\MysqlResult;
use React\Promise\PromiseInterface;
use Rx\Observable;

class MysqlTodoRepository implements TodoRepository
{
    public function __construct(
        private readonly MysqlClient $mysqlClient,
        private readonly MessageBus $messageBus,
    ) {
    }

    public function findAll(): PromiseInterface
    {
        return Observable::fromPromise(
            $this->mysqlClient->query('SELECT * FROM todo ORDER BY title ASC')
        )->map(
            fn (MysqlResult $result) => array_map([self::class, 'hydrate'], $result->resultRows)
        )->toPromise();
    }

    public function findById(TodoId $id): PromiseInterface
    {
        return Observable::fromPromise(
            $this->mysqlClient->query('SELECT * FROM todo WHERE id = ?', [$id->value])
        )->map(
            fn (MysqlResult $result) => isset($result->resultRows[0])
                ? self::hydrate($result->resultRows[0])
                : null
        )->toPromise();
    }

    public function save(Todo $todo): PromiseInterface
    {
        return $this->mysqlClient->query(
            'INSERT INTO todo (id, title, completed) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE title = VALUES(title), completed = VALUES(completed)',
            [$todo->id->value, $todo->title, $todo->completed ? 1 : 0]
        )->then(
            function (MysqlResult $result) use ($todo): Todo {
                foreach ($todo->pullDomainEvents() as $event) {
                    $this->messageBus->publish(
                        new Message($event->eventName(), $event->payload())
                    );
                }
                return $todo;
            }
        );
    }

    public function delete(TodoId $id): PromiseInterface
    {
        return $this->mysqlClient->query(
            'DELETE FROM todo WHERE id = ?',
            [$id->value]
        )->then(fn () => null);
    }

    private static function hydrate(array $row): Todo
    {
        return Todo::reconstitute(
            TodoId::from($row['id']),
            $row['title'],
            (bool)$row['completed'],
        );
    }
}
