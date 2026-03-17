# Cohete Skeleton

Starter project for Cohete async PHP.

## Quick Start

```bash
git clone <repository-url>
cd cohete-skeleton
composer install
make run
curl localhost:8080/health
```

Sin dependencias externas. Arranca con almacenamiento en memoria y bus de eventos local. Listo para desarrollar.

## Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET    | `/health` | Health check |
| GET    | `/todos` | List all todos |
| POST   | `/todos` | Create a new todo |
| GET    | `/todos/{id}` | Get a todo by ID |
| PUT    | `/todos/{id}` | Update a todo |
| DELETE | `/todos/{id}` | Delete a todo |

## Architecture

El skeleton demuestra como construir una app async con infraestructura intercambiable. El dominio no sabe que base de datos ni que bus de mensajes usa -- todo se decide en `bootstrap.php` via variables de entorno.

```text
                     ┌─────────────────────────────┐
                     │          Domain              │
                     │  Todo, TodoId, TodoRepository│
                     │  TodoCreated (domain event)  │
                     └──────────┬──────────────────┘
                                │ interfaces
                ┌───────────────┼───────────────┐
                │               │               │
     ┌──────────▼───┐  ┌───────▼──────┐  ┌─────▼──────────┐
     │  Repository   │  │  Message Bus  │  │   Controller   │
     │  (storage)    │  │  (events)     │  │   (HTTP)       │
     └──────┬───────┘  └──────┬────────┘  └────────────────┘
            │                 │
     ┌──────┴───────┐  ┌─────┴──────────┐
     │ InMemory     │  │ ReactMessageBus│ ← default (framework)
     │ MySQL        │  │ BunnieMessageBus│ ← RabbitMQ
     └──────────────┘  └────────────────┘
```

### Infrastructure Switching

Todo se controla con variables de entorno. Sin ellas, todo corre in-memory:

| Variable | Que activa |
|----------|-----------|
| `MYSQL_HOST` | `MysqlTodoRepository` en lugar de `InMemoryTodoRepository` |
| `RABBITMQ_HOST` | `BunnieMessageBus` en lugar de `ReactMessageBus` |

Ejemplo: solo MySQL, bus in-memory:
```bash
echo "MYSQL_HOST=127.0.0.1" > .env
echo "MYSQL_USER=cohete" >> .env
echo "MYSQL_PASSWORD=cohete" >> .env
echo "MYSQL_DATABASE=cohete_skeleton" >> .env
make run
```

Ejemplo: MySQL + RabbitMQ:
```bash
cp .env.example .env
# descomenta las lineas de RABBITMQ
make run
```

Sin `.env`: todo in-memory, zero dependencias externas.

## Message Bus

El bus de mensajes transporta domain events. Cuando un Todo se crea, el repository publica un `TodoCreated` event. Los subscribers reaccionan (logear, notificar, lo que sea).

### Interfaz comun

Ambas implementaciones cumplen la misma interfaz del framework:

```php
interface MessageBus
{
    public function publish(Message $message): void;
    public function subscribe(string $messageName, callable $listener): void;
}
```

### ReactMessageBus (default, in-memory)

Viene con `cohete/framework`. Usa `EventEmitter` + `futureTick()`. Los eventos viajan dentro del mismo proceso. Si el proceso muere, se pierden. Perfecto para desarrollo y apps simples.

No necesita configuracion. El `ContainerFactory` del framework lo registra automaticamente.

### BunnieMessageBus (RabbitMQ)

Los eventos viajan por AMQP a traves de RabbitMQ. Varios procesos pueden subscribirse al mismo exchange. Si un consumer muere, los mensajes esperan en la cola. Para produccion real.

Se activa poniendo `RABBITMQ_HOST` en el entorno. El bootstrap sobreescribe `MessageBus::class` en el container:

```php
if ($useRabbit) {
    $definitions[MessageBus::class] = static fn () => new BunnieMessageBus([
        'host'     => getenv('RABBITMQ_HOST'),
        'port'     => (int)(getenv('RABBITMQ_PORT') ?: 5672),
        'user'     => getenv('RABBITMQ_USER') ?: 'guest',
        'password' => getenv('RABBITMQ_PASSWORD') ?: 'guest',
        'vhost'    => getenv('RABBITMQ_VHOST') ?: '/',
    ]);
}
```

### Como funciona el async (bunny 0.6)

Bunny 0.6 usa `React\Socket\ConnectionInterface` internamente. El API parece sincrono pero por debajo usa Fibers y el event loop de ReactPHP:

```php
// Parece bloqueante, pero NO lo es:
$client = new Client($options);
$client->connect();           // internamente: await(promesa del handshake AMQP)
$channel = $client->channel();
```

Cuando llamas a `connect()`:
1. Abre un socket TCP via ReactPHP (no-bloqueante)
2. `await()` suspende la Fiber actual
3. El event loop procesa el handshake AMQP
4. La Fiber se resume y `connect()` retorna

Tu codigo escribe como si fuera sincrono. El event loop sigue vivo procesando HTTP requests mientras tanto.

Para `consume()`: registra un callback en la conexion. Cada vez que llega un mensaje por el socket, el event loop lo lee, bunny lo parsea, y ejecuta tu callback. No hay polling. El mismo loop que sirve HTTP sirve AMQP.

### Flujo de un domain event

```text
POST /todos
    → CreateTodoController
    → Todo::create() graba TodoCreated event en el aggregate
    → Repository::save() hace pullDomainEvents()
    → MessageBus::publish(TodoCreated)
    → [ReactMessageBus]  EventEmitter::emit() en el proximo tick
      [BunnieMessageBus] channel->publish() al exchange "cohete_events"
                         routing key: "domain_event.todo_created"
    → RabbitMQ rutea al queue del subscriber
    → consume() callback → TodoCreatedSubscriber
    → Logger: "Todo created {id, title}"
```

### Gotcha: queueBind en bunny 0.6

La firma es `queueBind($exchange, $queue, $routingKey)` -- exchange primero. En la mayoria de clientes AMQP es al reves. Si los inviertes, RabbitMQ dice `NOT_FOUND - no exchange 'amq.gen-xxxxx'`.

## MySQL Mode

Se activa con `MYSQL_HOST`. El bootstrap crea un `MysqlClient` (react/mysql, async) y registra `MysqlTodoRepository`:

```php
$definitions[MysqlClient::class] = static fn () => new MysqlClient(
    sprintf('%s:%s@%s:%s/%s', $user, $pass, $host, $port, $db)
);
$definitions[TodoRepository::class] = static function (ContainerInterface $c) {
    return new MysqlTodoRepository(
        $c->get(MysqlClient::class),
        $c->get(MessageBus::class),
    );
};
```

El schema se crea con `schema.sql` (auto-loaded por docker compose).

## Docker Compose

Levanta la app con MySQL y RabbitMQ:

```bash
cp .env.example .env
docker compose up -d
```

Servicios incluidos:
- **cohete**: la app PHP (puerto 8080)
- **mysql**: MySQL 8.0 (puerto 3306, schema auto-loaded)
- **rabbitmq**: RabbitMQ 3 + management UI (puertos 5672/15672)

## Project Structure

```text
.
├── config/
│   └── routes.json              # HTTP routing
├── src/
│   ├── Bus/
│   │   └── BunnieMessageBus.php # RabbitMQ implementation
│   ├── Controller/              # HTTP request handlers
│   ├── Domain/                  # Entities, Value Objects, interfaces
│   │   ├── Todo.php             # Aggregate root
│   │   ├── TodoId.php           # UUID value object
│   │   ├── TodoRepository.php   # Repository interface
│   │   └── Event/
│   │       └── TodoCreated.php  # Domain event
│   ├── Repository/
│   │   ├── InMemoryTodoRepository.php  # Default (no deps)
│   │   └── MysqlTodoRepository.php     # Async MySQL
│   ├── Subscriber/
│   │   └── TodoCreatedSubscriber.php   # Event handler
│   └── bootstrap.php            # Entry point, DI, infra switches
├── schema.sql                   # MySQL schema
├── .env.example                 # Config template
├── docker-compose.yml           # App + MySQL + RabbitMQ
├── Dockerfile                   # Multi-stage production image
├── Makefile                     # Common tasks
└── composer.json                # Dependencies
```

## How to add a new endpoint

1. **Create a Controller** in `src/Controller/` implementing `Cohete\HttpServer\HttpRequestHandler`.
2. **Register the Route** in `config/routes.json`.
3. **Register Dependencies** in `ContainerFactory::create()` call in `src/bootstrap.php`.

## Links

- [cohete/framework](https://github.com/pascualmg/cohete-framework)
- [cohete/ddd](https://github.com/pascualmg/cohete-ddd)

## License

MIT License - see [LICENSE](LICENSE).
