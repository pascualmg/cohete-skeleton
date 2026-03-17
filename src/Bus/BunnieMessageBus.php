<?php

namespace App\Bus;

use Bunny\Channel;
use Bunny\Client;
use Bunny\Message as BunnyMessage;
use Cohete\Bus\Message;
use Cohete\Bus\MessageBus;

class BunnieMessageBus implements MessageBus
{
    private const EXCHANGE = 'cohete_events';
    private Channel $channel;

    public function __construct(array $options = [])
    {
        $client = new Client($options);
        $client->connect();
        $this->channel = $client->channel();
        $this->channel->exchangeDeclare(self::EXCHANGE, 'topic', false, true);
    }

    public function publish(Message $message): void
    {
        $payload = json_encode([
            'name' => $message->name,
            'payload' => $message->payload,
        ], JSON_THROW_ON_ERROR);

        $this->channel->publish($payload, [], self::EXCHANGE, $message->name);
    }

    public function subscribe(string $messageName, callable $listener): void
    {
        $ok = $this->channel->queueDeclare('', false, false, true);
        $this->channel->queueBind(self::EXCHANGE, $ok->queue, $messageName);

        $this->channel->consume(
            function (BunnyMessage $msg, Channel $ch) use ($listener) {
                $data = json_decode($msg->content, true, 512, JSON_THROW_ON_ERROR);
                $listener($data['payload']);
                $ch->ack($msg);
            },
            $ok->queue,
        );
    }
}
