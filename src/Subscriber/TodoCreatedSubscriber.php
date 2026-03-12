<?php

namespace App\Subscriber;

use Psr\Log\LoggerInterface;

class TodoCreatedSubscriber
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(array $payload): void
    {
        $this->logger->info('Todo created', [
            'id' => $payload['id'] ?? 'unknown',
            'title' => $payload['title'] ?? 'unknown',
        ]);
    }
}
