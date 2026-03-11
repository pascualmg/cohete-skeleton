# Contributing to cohete-skeleton

cohete-skeleton is the official starter template for the Cohete framework.

## Requirements
- PHP 8.2+
- Composer
- Optional: Nix (use nix develop for a reproducible environment)

## Setup
composer install
php src/bootstrap.php

The server starts on port 8080.

## Running tests
vendor/bin/phpunit

## Project structure
config/routes.json   Route definitions
src/bootstrap.php    Entry point
src/Domain/          Domain entities and interfaces
src/Repository/      Repository implementations
src/Controller/      HTTP request handlers
tests/               PHPUnit test suite

## Adding a new endpoint
1. Create controller in src/Controller/ implementing Cohete\HttpServer\HttpRequestHandler
2. Add route to config/routes.json
3. PHP-DI autowiring handles injection
