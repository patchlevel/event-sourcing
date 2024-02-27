<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus\Serializer;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Serializer\Encoder\Encoder;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;

use function array_map;
use function is_array;
use function is_string;

/** @psalm-type EncodedData array{serializedEvent: array{name: string, payload: string}, headers: array{name: string, payload: string}} */
final class EventSerializerMessageSerializer implements MessageSerializer
{
    public function __construct(
        private readonly EventSerializer $eventSerializer,
        private readonly HeadersSerializer $headersSerializer,
        private readonly Encoder $encoder,
    ) {
    }

    public function serialize(Message $message): string
    {
        return $this->encoder->encode(
            [
                'serializedEvent' => $this->eventSerializer->serialize($message->event()),
                'headers' => $this->headersSerializer->serialize($message->headers()),
            ],
        );
    }

    public function deserialize(string $content): Message
    {
        $messageData = $this->encoder->decode($content);

        if (
            !isset($messageData['serializedEvent'], $messageData['headers'])
            || !is_array($messageData['serializedEvent'])
            || !is_array($messageData['headers'])
            || !isset($messageData['serializedEvent']['name'], $messageData['serializedEvent']['payload'])
            || !is_string($messageData['serializedEvent']['name'])
            || !is_string($messageData['serializedEvent']['payload'])
        ) {
            throw DeserializeFailed::invalidData($messageData);
        }

        $event = $this->eventSerializer->deserialize(new SerializedEvent($messageData['serializedEvent']['name'], $messageData['serializedEvent']['payload']));
        $headers = $this->headersSerializer->deserialize(array_map(
            /** @param array{name: string, payload: string} $headerData */
            static fn (array $headerData) => new SerializedHeader($headerData['name'], $headerData['payload']),
            $messageData['headers'],
        ));

        return Message::createWithHeaders($event, $headers);
    }
}
