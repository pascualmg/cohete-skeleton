<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Domain\TodoRepository;
use App\Repository\InMemoryTodoRepository;
use App\Subscriber\TodoCreatedSubscriber;
use Cohete\Bus\MessageBus;
use Cohete\Container\ContainerFactory;
use Cohete\HttpServer\Kernel;
use Cohete\HttpServer\ReactHttpServer;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use Rx\Scheduler;
use Rx\Scheduler\EventLoopScheduler;

$loop = Loop::get();

$scheduler = new EventLoopScheduler($loop);
Scheduler::setDefaultFactory(static fn () => $scheduler);

$routesPath = __DIR__ . '/../config/routes.json';

$container = ContainerFactory::create([
    'routes.path' => static fn () => $routesPath,
    TodoRepository::class => static function (ContainerInterface $c) {
        /** @var MessageBus $bus */
        $bus = $c->get(MessageBus::class);
        return new InMemoryTodoRepository($bus);
    },
]);

// Wire domain event subscribers
/** @var MessageBus $bus */
$bus = $container->get(MessageBus::class);
/** @var LoggerInterface $logger */
$logger = $container->get(LoggerInterface::class);

$bus->subscribe(
    'domain_event.todo_created',
    new TodoCreatedSubscriber($logger)
);

$kernel = new Kernel($container, $routesPath);

ReactHttpServer::init(
    host: '0.0.0.0',
    port: '8080',
    kernel: $kernel,
    loop: $loop,
);

$loop->run();
