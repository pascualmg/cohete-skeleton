<?php

namespace App\Tests\Controller;

use App\Controller\HealthController;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

class HealthControllerTest extends TestCase
{
    public function testInvokeReturns200WithBodyOk(): void
    {
        $controller = new HealthController();
        $request = $this->createMock(ServerRequestInterface::class);

        $response = $controller->__invoke($request, null);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', (string) $response->getBody());
        $this->assertEquals(['text/plain'], $response->getHeader('Content-Type'));
    }
}
