#!/usr/bin/env php
<?php

/**
 * MCP Server (stdio transport) - for local development with AI agents.
 *
 * Run: php src/mcp-server.php
 *
 * This exposes your domain operations as MCP tools that any AI agent
 * (Claude Code, Cursor, etc.) can call directly. Same tool handlers
 * as the SSE transport, different transport layer.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Domain\TodoRepository;
use App\MCP\TodoToolHandlers;
use App\Repository\InMemoryTodoRepository;
use Cohete\Bus\MessageBus;
use Cohete\Container\ContainerFactory;
use PhpMcp\Server\Server;
use PhpMcp\Server\Transports\StdioServerTransport;
use Psr\Container\ContainerInterface;
use Psr\Log\AbstractLogger;

// Stderr logger (stdout is reserved for MCP protocol)
$logger = new class extends AbstractLogger {
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        fwrite(STDERR, sprintf("[%s][%s] %s\n", date('H:i:s'), strtoupper($level), $message));
    }
};

try {
    // Load .env if present
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if ($line[0] !== '#') {
                putenv($line);
            }
        }
    }

    $container = ContainerFactory::create([
        TodoRepository::class => static function (ContainerInterface $c) {
            return new InMemoryTodoRepository($c->get(MessageBus::class));
        },
    ]);

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

    $transport = new StdioServerTransport();
    $server->listen($transport);
} catch (\Throwable $e) {
    fwrite(STDERR, "[CRITICAL] " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
}
