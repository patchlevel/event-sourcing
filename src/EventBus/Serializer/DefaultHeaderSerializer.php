<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus\Serializer;

use Patchlevel\EventSourcing\EventBus\Header;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Metadata\Message\MessageHeaderRegistry;
use Patchlevel\EventSourcing\Serializer\Encoder\Encoder;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;

use Patchlevel\Hydrator\Hydrator;
use function is_array;

final class DefaultHeaderSerializer implements HeaderSerializer
{
    public function __construct(
        private readonly MessageHeaderRegistry $messageHeaderRegistry,
        private readonly Hydrator $hydrator,
        private readonly Encoder $encoder,
    ) {
    }

    public function serialize(Header $header): string
    {
        return $this->encoder->encode(
            [
                $this->messageHeaderRegistry->headerName($header::class) => $this->hydrator->extract($header),
            ],
        );
    }

    public function deserialize(string $content): Header
    {
        $headerData = $this->encoder->decode($content);

        $this->hydrator->hydrate($this->messageHeaderRegistry->headerClass($headerName), $headerData);

        return Message::createWithHeaders($event, $headers);
    }
}
