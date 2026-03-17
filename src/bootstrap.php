<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Bus\BunnieMessageBus;
use App\Domain\TodoRepository;
use App\Repository\InMemoryTodoRepository;
use App\Repository\MysqlTodoRepository;
use App\Subscriber\TodoCreatedSubscriber;
use Cohete\Bus\MessageBus;
use Cohete\Container\ContainerFactory;
use Cohete\HttpServer\Kernel;
use Cohete\HttpServer\ReactHttpServer;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use React\Mysql\MysqlClient;
use Rx\Scheduler;
use Rx\Scheduler\EventLoopScheduler;

// Load .env if present
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line[0] !== '#') {
            putenv($line);
        }
    }
}

$loop = Loop::get();

$scheduler = new EventLoopScheduler($loop);
Scheduler::setDefaultFactory(static fn () => $scheduler);

$routesPath = __DIR__ . '/../config/routes.json';

// Infrastructure switches: set env vars to enable MySQL and/or RabbitMQ.
// Without them, everything runs in-memory (zero external dependencies).
$useMysql = (bool)getenv('MYSQL_HOST');
$useRabbit = (bool)getenv('RABBITMQ_HOST');

$definitions = [
    'routes.path' => static fn () => $routesPath,
];

// -- Message Bus: RabbitMQ or in-memory ReactMessageBus (framework default) --
if ($useRabbit) {
    $definitions[MessageBus::class] = static fn () => new BunnieMessageBus([
        'host'     => getenv('RABBITMQ_HOST'),
        'port'     => (int)(getenv('RABBITMQ_PORT') ?: 5672),
        'user'     => getenv('RABBITMQ_USER') ?: 'guest',
        'password' => getenv('RABBITMQ_PASSWORD') ?: 'guest',
        'vhost'    => getenv('RABBITMQ_VHOST') ?: '/',
    ]);
}

// -- Repository: MySQL or InMemory --
if ($useMysql) {
    $definitions[MysqlClient::class] = static fn () => new MysqlClient(
        sprintf(
            '%s:%s@%s:%s/%s',
            getenv('MYSQL_USER'),
            getenv('MYSQL_PASSWORD'),
            getenv('MYSQL_HOST'),
            getenv('MYSQL_PORT') ?: '3306',
            getenv('MYSQL_DATABASE'),
        )
    );
    $definitions[TodoRepository::class] = static function (ContainerInterface $c) {
        return new MysqlTodoRepository(
            $c->get(MysqlClient::class),
            $c->get(MessageBus::class),
        );
    };
} else {
    $definitions[TodoRepository::class] = static function (ContainerInterface $c) {
        return new InMemoryTodoRepository($c->get(MessageBus::class));
    };
}

$container = ContainerFactory::create($definitions);

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

$staticRoot = __DIR__ . '/../public';

ReactHttpServer::init(
    host: '0.0.0.0',
    port: '8080',
    kernel: $kernel,
    loop: $loop,
    staticRoot: $staticRoot,
);

$loop->run();
