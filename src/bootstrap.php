<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Domain\TodoRepository;
use App\Repository\InMemoryTodoRepository;
use Cohete\Container\ContainerFactory;
use Cohete\HttpServer\Kernel;
use Cohete\HttpServer\ReactHttpServer;
use React\EventLoop\Loop;
use Rx\Scheduler;
use Rx\Scheduler\EventLoopScheduler;

$loop = Loop::get();

$scheduler = new EventLoopScheduler($loop);
Scheduler::setDefaultFactory(static fn () => $scheduler);

$routesPath = __DIR__ . '/../config/routes.json';

$container = ContainerFactory::create([
    'routes.path' => static fn () => $routesPath,
    TodoRepository::class => static fn () => new InMemoryTodoRepository(),
]);

$kernel = new Kernel($container, $routesPath);

ReactHttpServer::init(
    host: '0.0.0.0',
    port: '8080',
    kernel: $kernel,
    loop: $loop,
);

$loop->run();
