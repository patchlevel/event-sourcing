<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus\Serializer;

use Patchlevel\EventSourcing\EventBus\Message;

use function base64_decode;
use function base64_encode;
use function serialize;
use function unserialize;

final class PhpNativeMessageSerializer implements MessageSerializer
{
    public function serialize(Message $message): string
    {
        return base64_encode(serialize($message));
    }

    public function deserialize(string $content): Message
    {
        return unserialize(
            base64_decode($content),
            ['allowed_classes' => true],
        );
    }
}
