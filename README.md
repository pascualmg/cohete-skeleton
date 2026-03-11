# Cohete Skeleton

starter project for Cohete async PHP

## Quick Start

1. Clone the repository:
   ```bash
   git clone <repository-url>
   cd cohete-skeleton
   ```

2. Install dependencies:
   ```bash
   composer install
   ```
   or
   ```bash
   make install
   ```

3. Run the application:
   ```bash
   make run
   ```

4. Check health:
   ```bash
   curl localhost:8080/health
   ```

## Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET    | `/health` | Health check |
| GET    | `/todos` | List all todos |
| POST   | `/todos` | Create a new todo |
| GET    | `/todos/{id}` | Get a todo by ID |
| PUT    | `/todos/{id}` | Update a todo |
| DELETE | `/todos/{id}` | Delete a todo |

## Project Structure

```text
.
├── config/
│   └── routes.json          # HTTP Routing configuration
├── src/
│   ├── Controller/          # Request handlers
│   ├── Domain/              # Entities, Value Objects and Repository interfaces
│   ├── Repository/          # Infrastructure implementations of repositories
│   └── bootstrap.php        # Application entry point and DI configuration
├── Makefile                 # Common tasks
└── composer.json            # Project dependencies
```

## How to add a new endpoint

1. **Create a Controller**: Create a new class in `src/Controller` that implements `Cohete\HttpServer\HttpRequestHandler`.
2. **Register the Route**: Add the new endpoint to `config/routes.json`.
3. **Register Dependencies**: If your controller has dependencies, add them to the `ContainerFactory::create()` call in `src/bootstrap.php`.

## Links

- [cohete/framework](https://github.com/pascualmg/cohete-framework)
- [cohete/ddd](https://github.com/pascualmg/cohete-ddd)

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
