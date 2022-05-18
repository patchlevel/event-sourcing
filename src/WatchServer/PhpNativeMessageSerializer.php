<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\WatchServer;

use DateTimeImmutable;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;

use function base64_decode;
use function base64_encode;
use function serialize;
use function unserialize;

/**
 * @psalm-import-type Headers from Message
 */
final class PhpNativeMessageSerializer implements MessageSerializer
{
    private EventSerializer $serializer;

    public function __construct(EventSerializer $serializer)
    {
        $this->serializer = $serializer;
    }

    public function serialize(Message $message): string
    {
        $event = $message->event();

        $data = [
            'event' => $event::class,
            'payload' => $this->serializer->serialize($event),
            'headers' => $message->headers(),
        ];

        return base64_encode(serialize($data));
    }

    public function deserialize(string $content): Message
    {
        /** @var array{event: class-string, payload: string, headers: Headers} $data */
        $data = unserialize(base64_decode($content), ['allowed_classes' => [DateTimeImmutable::class]]);

        return Message::createWithHeaders(
            $this->serializer->deserialize(new SerializedEvent($data['event'], $data['payload'])),
            $data['headers'],
        );
    }
}
