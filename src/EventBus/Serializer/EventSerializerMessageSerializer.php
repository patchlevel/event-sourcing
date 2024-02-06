<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus\Serializer;

use DateTimeImmutable;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;

use function base64_decode;
use function base64_encode;
use function is_array;
use function is_string;
use function serialize;
use function unserialize;

final class EventSerializerMessageSerializer implements MessageSerializer
{
    public function __construct(
        private readonly EventSerializer $eventSerializer,
    ) {
    }

    public function serialize(Message $message): string
    {
        $serializedEvent = $this->eventSerializer->serialize($message->event());

        return base64_encode(
            serialize(
                [
                    'serializedEvent' => $serializedEvent,
                    'headers' => $message->headers(),
                ],
            ),
        );
    }

    public function deserialize(string $content): Message
    {
        $decodedString = base64_decode($content, true);

        if (!is_string($decodedString)) {
            throw DeserializeFailed::decodeFailed();
        }

        $data = unserialize(
            $decodedString,
            [
                'allowed_classes' => [
                    SerializedEvent::class,
                    DateTimeImmutable::class,
                ],
            ],
        );

        if (
            !is_array($data)
            || !isset($data['serializedEvent'], $data['headers'])
            || !$data['serializedEvent'] instanceof SerializedEvent
            || !is_array($data['headers'])
        ) {
            throw DeserializeFailed::invalidData($data);
        }

        $event = $this->eventSerializer->deserialize($data['serializedEvent']);

        return Message::createWithHeaders($event, $data['headers']);
    }
}
