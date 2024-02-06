<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus\Serializer;

use Patchlevel\EventSourcing\EventBus\Message;

use function base64_decode;
use function base64_encode;
use function is_string;
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
        $decodedString = base64_decode($content, true);

        if (!is_string($decodedString)) {
            throw DeserializeFailed::decodeFailed();
        }

        $message = unserialize(
            $decodedString,
            ['allowed_classes' => true],
        );

        if (!$message instanceof Message) {
            throw DeserializeFailed::invalidMessage($message);
        }

        return $message;
    }
}
