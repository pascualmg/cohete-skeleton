<?php

declare(strict_types=1);

namespace App\MCP;

use PhpMcp\Server\Server;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class McpServerFactory
{
    public static function create(
        ContainerInterface $container,
        LoggerInterface $logger,
        CoheteTransport $transport,
    ): CoheteTransport {
        $server = Server::make()
            ->withServerInfo('cohete-skeleton', '1.0.0')
            ->withLogger($logger)
            ->withContainer($container)
            ->withTool([TodoToolHandlers::class, 'listTodos'], 'list_todos', 'List all todos')
            ->withTool([TodoToolHandlers::class, 'getTodo'], 'get_todo', 'Get a todo by UUID')
            ->withTool([TodoToolHandlers::class, 'createTodo'], 'create_todo', 'Create a new todo')
            ->withTool([TodoToolHandlers::class, 'updateTodo'], 'update_todo', 'Update a todo')
            ->withTool([TodoToolHandlers::class, 'deleteTodo'], 'delete_todo', 'Delete a todo')
            ->build();

        $protocol = $server->getProtocol();
        $protocol->bindTransport($transport);
        $transport->listen();

        return $transport;
    }
}
