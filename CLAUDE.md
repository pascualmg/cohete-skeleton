# Cohete Skeleton - Agent Instructions

You are working on a Cohete project. Cohete is an async PHP framework built on ReactPHP. Everything is non-blocking. There is ONE event loop, ONE process, and it handles HTTP, MySQL, RabbitMQ, and WebSockets concurrently.

**Do not treat this like Laravel, Symfony, or any traditional PHP framework.** There is no request lifecycle, no PHP-FPM, no Apache. The server is a long-running PHP process.

## Skeleton Philosophy

Cohete is NOT an opinionated framework. But the skeleton IS opinionated -- it ships with batteries included so you can start building immediately: MySQL, RabbitMQ, Web Components, and MCP are all wired up out of the box.

If you don't need something, remove it. If you leave it unused, it doesn't affect performance -- unused infrastructure (MySQL, RabbitMQ, MCP) is only loaded when env vars activate it. The skeleton is a starting point, not a prison.

## The Golden Rule

**Every I/O operation returns a `PromiseInterface`.** Never block. Never use `sleep()`, `file_get_contents()`, or synchronous database calls. If you need to do I/O, use the async version and chain with `->then()`.

## Architecture

```
src/
├── Controller/          # HTTP handlers (implement HttpRequestHandler)
├── Domain/              # Entities, Value Objects, Repository interfaces, Events
│   └── Event/           # Domain events (implement DomainEvent)
├── Repository/          # Infrastructure: InMemory, MySQL implementations
├── Bus/                 # Message bus implementations (BunnieMessageBus)
├── MCP/                 # MCP tool handlers (shared by stdio and SSE transports)
├── Subscriber/          # Event handlers (callables for MessageBus)
├── bootstrap.php        # Entry point: event loop, DI, infra switching, HTTP server
└── mcp-server.php       # MCP stdio server (for local dev with AI agents)
public/
├── index.html           # Frontend entry point
└── js/components/       # Web Components (vanilla JS, Shadow DOM, ES modules)
```

Domain is on top. Framework is below. Controllers and Repositories are infrastructure. The domain NEVER imports framework classes.

## How to add a new feature (step by step)

### 1. Domain entity (Aggregate Root)

```php
namespace App\Domain;

use Cohete\DDD\Aggregate\AggregateRoot;

class Product extends AggregateRoot
{
    private function __construct(
        public readonly ProductId $id,
        public readonly string $name,
        public readonly int $price,
    ) {}

    public static function create(ProductId $id, string $name, int $price): self
    {
        $product = new self($id, $name, $price);
        $product->record(new ProductCreated($id->value, $name, $price));
        return $product;
    }

    public static function reconstitute(ProductId $id, string $name, int $price): self
    {
        return new self($id, $name, $price);
    }

    public function toArray(): array
    {
        return ['id' => $this->id->value, 'name' => $this->name, 'price' => $this->price];
    }
}
```

Key patterns:
- Constructor is **private**. Use `create()` for new entities (records domain events) and `reconstitute()` for loading from DB (no events).
- Extends `AggregateRoot` which provides `record()` and `pullDomainEvents()`.
- Immutable. Use `withName()` / `withPrice()` methods that return new instances.

### 2. Value Object (ID)

```php
namespace App\Domain;

use Cohete\DDD\ValueObject\UuidValueObject;

class ProductId extends UuidValueObject {}
```

`UuidValueObject` provides `::v4()` (generate), `::from(string)` (validate + wrap), and `->value` (string getter).

### 3. Domain Event

```php
namespace App\Domain\Event;

use Cohete\DDD\Event\DomainEvent;

class ProductCreated implements DomainEvent
{
    public function __construct(
        private readonly string $productId,
        private readonly string $name,
        private readonly int $price,
        private readonly \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}

    public function eventName(): string { return 'domain_event.product_created'; }
    public function payload(): array { return ['id' => $this->productId, 'name' => $this->name, 'price' => $this->price]; }
    public function occurredOn(): \DateTimeImmutable { return $this->occurredAt; }
}
```

Convention: event names are `domain_event.<entity>_<past_tense_verb>`.

### 4. Repository Interface (Domain layer)

```php
namespace App\Domain;

use React\Promise\PromiseInterface;

interface ProductRepository
{
    /** @return PromiseInterface<Product[]> */
    public function findAll(): PromiseInterface;

    /** @return PromiseInterface<Product|null> */
    public function findById(ProductId $id): PromiseInterface;

    /** @return PromiseInterface<Product> */
    public function save(Product $product): PromiseInterface;

    /** @return PromiseInterface<void> */
    public function delete(ProductId $id): PromiseInterface;
}
```

**All methods return `PromiseInterface`.** This is the async contract.

### 5. Repository Implementation (InMemory)

```php
namespace App\Repository;

use App\Domain\Product;
use App\Domain\ProductId;
use App\Domain\ProductRepository;
use Cohete\Bus\Message;
use Cohete\Bus\MessageBus;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;

class InMemoryProductRepository implements ProductRepository
{
    private array $products = [];

    public function __construct(private readonly MessageBus $messageBus) {}

    public function findAll(): PromiseInterface
    {
        return resolve(array_values($this->products));
    }

    public function findById(ProductId $id): PromiseInterface
    {
        return resolve($this->products[$id->value] ?? null);
    }

    public function save(Product $product): PromiseInterface
    {
        $this->products[$product->id->value] = $product;
        foreach ($product->pullDomainEvents() as $event) {
            $this->messageBus->publish(new Message($event->eventName(), $event->payload()));
        }
        return resolve($product);
    }

    public function delete(ProductId $id): PromiseInterface
    {
        unset($this->products[$id->value]);
        return resolve(null);
    }
}
```

The repository publishes domain events after save. Use `resolve()` from `react/promise` for immediate values.

### 6. Repository Implementation (MySQL)

```php
namespace App\Repository;

use App\Domain\Product;
use App\Domain\ProductId;
use App\Domain\ProductRepository;
use Cohete\Bus\Message;
use Cohete\Bus\MessageBus;
use React\Mysql\MysqlClient;
use React\Mysql\MysqlResult;
use React\Promise\PromiseInterface;
use Rx\Observable;

class MysqlProductRepository implements ProductRepository
{
    public function __construct(
        private readonly MysqlClient $mysqlClient,
        private readonly MessageBus $messageBus,
    ) {}

    public function findAll(): PromiseInterface
    {
        return Observable::fromPromise(
            $this->mysqlClient->query('SELECT * FROM product ORDER BY name')
        )->map(
            fn (MysqlResult $r) => array_map([self::class, 'hydrate'], $r->resultRows)
        )->toPromise();
    }

    public function findById(ProductId $id): PromiseInterface
    {
        return Observable::fromPromise(
            $this->mysqlClient->query('SELECT * FROM product WHERE id = ?', [$id->value])
        )->map(
            fn (MysqlResult $r) => isset($r->resultRows[0]) ? self::hydrate($r->resultRows[0]) : null
        )->toPromise();
    }

    public function save(Product $product): PromiseInterface
    {
        return $this->mysqlClient->query(
            'INSERT INTO product (id, name, price) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), price = VALUES(price)',
            [$product->id->value, $product->name, $product->price]
        )->then(function () use ($product): Product {
            foreach ($product->pullDomainEvents() as $event) {
                $this->messageBus->publish(new Message($event->eventName(), $event->payload()));
            }
            return $product;
        });
    }

    public function delete(ProductId $id): PromiseInterface
    {
        return $this->mysqlClient->query('DELETE FROM product WHERE id = ?', [$id->value])
            ->then(fn () => null);
    }

    private static function hydrate(array $row): Product
    {
        return Product::reconstitute(
            ProductId::from($row['id']),
            $row['name'],
            (int)$row['price'],
        );
    }
}
```

Pattern: `Observable::fromPromise(query) -> map(hydrate) -> toPromise()`. Use parameterized queries (`?` placeholders) always.

### 7. Controller

```php
namespace App\Controller;

use App\Domain\Product;
use App\Domain\ProductId;
use App\Domain\ProductRepository;
use Cohete\HttpServer\HttpRequestHandler;
use Cohete\HttpServer\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;

class CreateProductController implements HttpRequestHandler
{
    public function __construct(private readonly ProductRepository $productRepository) {}

    public function __invoke(
        ServerRequestInterface $request,
        ?array $routeParams
    ): ResponseInterface|PromiseInterface {
        $body = json_decode($request->getBody()->getContents(), true);

        $product = Product::create(
            ProductId::v4(),
            $body['name'] ?? '',
            (int)($body['price'] ?? 0),
        );

        return $this->productRepository->save($product)
            ->then(fn (Product $p) => JsonResponse::create(201, $p->toArray()));
    }
}
```

Controllers implement `HttpRequestHandler`. Return `ResponseInterface` for sync or `PromiseInterface` for async. Use `JsonResponse::create(status, data)` or `JsonResponse::withPayload(data)`.

Route params come from `$routeParams` array (e.g., `$routeParams['id']` for `/products/{id}`).

### 8. Route

Add to `config/routes.json`:

```json
{
  "method": "POST",
  "path": "/products",
  "handler": "App\\Controller\\CreateProductController"
}
```

### 9. Register in DI container (bootstrap.php)

```php
$definitions[ProductRepository::class] = static function (ContainerInterface $c) {
    return new InMemoryProductRepository($c->get(MessageBus::class));
};
```

PHP-DI autowires controller constructor dependencies. You only need to register interfaces that have multiple implementations.

### 10. Subscriber (optional)

```php
namespace App\Subscriber;

use Psr\Log\LoggerInterface;

class ProductCreatedSubscriber
{
    public function __construct(private readonly LoggerInterface $logger) {}

    public function __invoke(array $payload): void
    {
        $this->logger->info('Product created', $payload);
    }
}
```

Wire in bootstrap.php:

```php
$bus->subscribe('domain_event.product_created', new ProductCreatedSubscriber($logger));
```

## Infrastructure Switching

Everything is controlled by env vars. Without them, zero external dependencies.

| Variable | Effect |
|----------|--------|
| `MYSQL_HOST` | Switches to MySQL repository |
| `RABBITMQ_HOST` | Switches to RabbitMQ message bus |
| Neither | InMemory repo + ReactMessageBus (default) |

The switch happens in `bootstrap.php`. Domain code never changes.

## Message Bus

Two implementations of the same `MessageBus` interface:

- **ReactMessageBus** (framework default): in-memory EventEmitter. Events are local to the process.
- **BunnieMessageBus** (`src/Bus/`): RabbitMQ via bunny/bunny 0.6. Events travel over AMQP.

### Bunny 0.6 gotchas

1. **`Bunny\Async\Client` does not exist.** Use `Bunny\Client`. It looks synchronous but uses Fibers + ReactPHP internally.
2. **`queueBind($exchange, $queue, $routingKey)`** -- exchange first. Most AMQP clients put queue first. If reversed, you get `NOT_FOUND - no exchange 'amq.gen-xxx'`.

## Response Helpers

```php
// JSON responses
JsonResponse::create(201, ['id' => '...']);          // status + data
JsonResponse::withPayload($data);                     // 200 + data
JsonResponse::withError($throwable);                   // 500 + error message
JsonResponse::notFound(Product::class);                // 404

// Raw responses
new Response(204);                                     // No content
new Response(200, ['Content-Type' => 'text/plain'], 'OK');
```

## Testing

Tests use PHPUnit 11. Controllers are tested by mocking repository interfaces.

```php
class CreateProductControllerTest extends TestCase
{
    public function testValidRequestReturns201(): void
    {
        $repo = $this->createMock(ProductRepository::class);
        $repo->expects($this->once())
            ->method('save')
            ->willReturnCallback(fn(Product $p) => resolve($p));

        $controller = new CreateProductController($repo);
        $request = /* mock ServerRequestInterface with body */;

        $response = $controller->__invoke($request, null);

        // If response is a Promise, resolve it
        if ($response instanceof PromiseInterface) {
            $response->then(function ($r) use (&$response) { $response = $r; });
        }

        $this->assertEquals(201, $response->getStatusCode());
    }
}
```

Run tests: `vendor/bin/phpunit` or `nix develop --command bash -c 'cohete-test'`.

## Running the Application

```bash
# Development (Nix)
nix develop
cohete-serve              # starts on :8080

# Development (manual)
composer install
php src/bootstrap.php

# Docker
cp .env.example .env
docker compose up -d

# Tests
cohete-test               # or vendor/bin/phpunit
```

## Key Dependencies

| Package | Purpose |
|---------|---------|
| `cohete/framework` | HTTP server, Kernel, DI container, ReactMessageBus, JsonResponse |
| `cohete/ddd` | AggregateRoot, DomainEvent, UuidValueObject |
| `react/mysql` | Async MySQL client |
| `bunny/bunny` | AMQP/RabbitMQ client (0.6, Fiber-based) |
| `reactivex/rxphp` | Observable streams for query result mapping |

## MCP (Model Context Protocol)

The skeleton ships with MCP support so AI agents can interact with your domain directly. Two transports, same tool handlers:

### stdio (local development)

For your local AI agent (Claude Code, Cursor). Runs as a subprocess, talks via stdin/stdout. Full access to your domain -- internal operations, DB queries, whatever you need during development.

```bash
php src/mcp-server.php
```

Configure in your agent (e.g., `.claude/settings.json`):
```json
{
  "mcpServers": {
    "my-app": {
      "command": "php",
      "args": ["src/mcp-server.php"]
    }
  }
}
```

### SSE/HTTP (remote, production)

For external agents. Exposes only the tools you want to be public, with authentication if needed. Integrated into the same HTTP server (no separate process, no extra port).

Routes: `GET /mcp/sse` (opens stream) + `POST /mcp/message` (receives JSON-RPC).

**Not included in the skeleton by default** -- add it when you need external agent access. See the [Cohete blog](https://github.com/pascualmg/cohete) for the full SSE transport implementation.

### Tool handlers

Tools live in `src/MCP/TodoToolHandlers.php`. They use `await()` to bridge async domain operations to the sync MCP handler API:

```php
#[McpTool(name: 'list_todos')]
public function listTodos(): array
{
    $todos = await($this->todoRepository->findAll());
    return array_map(fn (Todo $t) => $t->toArray(), $todos);
}
```

### Adding a new MCP tool

1. Add a public method to your tool handler class with `#[McpTool]` attribute.
2. Method parameters become the tool's input schema (via PHP reflection).
3. Register it in `mcp-server.php`:

```php
->withTool([TodoToolHandlers::class, 'myNewTool'], 'my_tool', 'Description of the tool')
```

### Skeleton tools

| Tool | Description |
|------|-------------|
| `list_todos` | List all todos |
| `get_todo` | Get a todo by UUID |
| `create_todo` | Create a new todo |
| `update_todo` | Update title/completed |
| `delete_todo` | Delete a todo |

## Frontend

Cohete uses **vanilla JavaScript with Web Components**. No React, no Vue, no build step, no bundler. ES modules loaded directly by the browser.

### Philosophy

- **Web Components with Shadow DOM**: encapsulated, reusable, framework-free.
- **Atomic Design**: atoms (TodoItem) compose into organisms (TodoApp).
- **ES modules**: `import` / `export` natively. No webpack, no vite, no transpilation.
- **Styles in Shadow DOM**: each component owns its CSS. No global stylesheet conflicts.

### Structure

```text
public/
├── index.html                    # Entry point (served by IndexController at /)
└── js/
    └── components/
        ├── TodoApp.js            # Organism: full todo CRUD, fetches API
        └── TodoItem.js           # Atom: single todo row with toggle/delete
```

### Static File Serving

The framework's `ReactHttpServer` has a built-in static file middleware. Activated by passing `staticRoot` in bootstrap.php:

```php
$staticRoot = __DIR__ . '/../public';
ReactHttpServer::init(
    host: '0.0.0.0', port: '8080',
    kernel: $kernel, loop: $loop,
    staticRoot: $staticRoot,
);
```

Any request with a file extension (`.js`, `.css`, `.png`, etc.) is checked against `public/`. If found, served directly with correct MIME type and cache headers. If not found, falls through to the router.

### How to add a Web Component

1. Create `public/js/components/MyComponent.js`:

```js
const template = document.createElement('template');
template.innerHTML = `
<style>
  :host { display: block; }
  /* component-scoped CSS */
</style>
<div class="root"></div>
`;

class MyComponent extends HTMLElement {
  constructor() {
    super();
    this.attachShadow({ mode: 'open' });
    this.shadowRoot.appendChild(template.content.cloneNode(true));
  }

  connectedCallback() {
    // fetch API, add listeners, render
  }
}

customElements.define('my-component', MyComponent);
export default MyComponent;
```

2. Use it in HTML:

```html
<my-component></my-component>
<script type="module" src="/js/components/MyComponent.js"></script>
```

### Patterns

- **API calls**: Use `fetch()` against the same origin. The server serves both API and frontend.
- **Events between components**: Use `CustomEvent` with `bubbles: true, composed: true` to cross Shadow DOM boundaries.
- **No state library**: Components manage their own state. For shared state, use events or a simple pub/sub.

## Common Mistakes

1. **Blocking I/O**: Never use `file_get_contents()`, `PDO`, `mysqli`, or any synchronous I/O. It freezes the entire server.
2. **Forgetting `->then()`**: Repository methods return promises. You must chain with `->then()` to get the actual value.
3. **Not returning from controller**: Controllers must return `ResponseInterface` or `PromiseInterface`. If you forget the `return`, the client gets no response.
4. **Creating entities with `new` instead of `::create()`**: Only `create()` records domain events. Use `reconstitute()` when loading from DB.
5. **Publishing events before save succeeds**: Always publish domain events inside the save method, after the actual persist succeeds.
6. **Using `sleep()` or `usleep()`**: Use `$loop->addTimer()` or `React\Promise\Timer\sleep()` instead.
