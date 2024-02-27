<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus\Serializer;

use Patchlevel\EventSourcing\EventBus\Header;
use Patchlevel\EventSourcing\Metadata\Message\AttributeMessageHeaderRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Message\MessageHeaderRegistry;
use Patchlevel\EventSourcing\Serializer\Encoder\Encoder;
use Patchlevel\EventSourcing\Serializer\Encoder\JsonEncoder;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\MetadataHydrator;

final class DefaultHeadersSerializer implements HeadersSerializer
{
    public function __construct(
        private readonly MessageHeaderRegistry $messageHeaderRegistry,
        private readonly Hydrator $hydrator,
        private readonly Encoder $encoder,
    ) {
    }

    /**
     * @param list<Header> $headers
     *
     * @return array<SerializedHeader>
     */
    public function serialize(array $headers): array
    {
        $serializedHeaders = [];
        foreach ($headers as $header) {
            $serializedHeaders[] = new SerializedHeader(
                $this->messageHeaderRegistry->headerName($header::class),
                $this->encoder->encode($this->hydrator->extract($header)),
            );
        }

        return $serializedHeaders;
    }

    /**
     * @param array<SerializedHeader> $serializedHeaders
     *
     * @return list<Header>
     */
    public function deserialize(array $serializedHeaders): array
    {
        $headers = [];
        foreach ($serializedHeaders as $serializedHeader) {
            $headers[] = $this->hydrator->hydrate($this->messageHeaderRegistry->headerClass($serializedHeader->name), $this->encoder->decode($serializedHeader->payload));
        }

        return $headers;
    }

    /** @param list<string> $paths */
    public static function createFromPaths(array $paths): static
    {
        return new self(
            (new AttributeMessageHeaderRegistryFactory())->create($paths),
            new MetadataHydrator(),
            new JsonEncoder(),
        );
    }
}
