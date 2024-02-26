<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus\Serializer;

use DateTimeImmutable;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Metadata\Message\MessageHeaderRegistry;
use Patchlevel\EventSourcing\Serializer\Encoder\Encoder;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;

use Patchlevel\Hydrator\Hydrator;
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
        private readonly HeadersSerializer $headersSerializer,
        private readonly Hydrator $hydrator,
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
            !is_array($messageData)
            || !isset($messageData['serializedEvent'], $messageData['headers'])
            || !is_array($messageData['serializedEvent'])
            || !is_array($messageData['headers'])
        ) {
            throw DeserializeFailed::invalidData($messageData);
        }

        $event = $this->eventSerializer->deserialize(new SerializedEvent($messageData['serializedEvent']['name'], $messageData['serializedEvent']['payload']));
        $headers = $this->headersSerializer->deserialize(array_map(fn (array $headerData) => new SerializedHeader($headerData['name'], $headerData['payload']), $messageData['headers']));

        return Message::createWithHeaders($event, $headers);
    }
}
