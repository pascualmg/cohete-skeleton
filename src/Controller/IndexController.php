<?php

namespace App\Controller;

use Cohete\HttpServer\HttpRequestHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Promise\PromiseInterface;

class IndexController implements HttpRequestHandler
{
    public function __invoke(
        ServerRequestInterface $request,
        ?array $routeParams
    ): ResponseInterface|PromiseInterface {
        $html = file_get_contents(__DIR__ . '/../../public/index.html');

        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }
}
