<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Message\Serializer;

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
     * @param list<object>         $headers
     * @param array<string, mixed> $options
     *
     * @return array<string, string>
     */
    public function serialize(array $headers, array $options = []): array
    {
        $serializedHeaders = [];
        foreach ($headers as $header) {
            $serializedHeaders[$this->messageHeaderRegistry->headerName($header::class)] = $this->encoder->encode($this->hydrator->extract($header), $options);
        }

        return $serializedHeaders;
    }

    /**
     * @param array<string, string> $serializedHeaders
     *
     * @return list<object>
     */
    public function deserialize(array $serializedHeaders): array
    {
        $headers = [];
        foreach ($serializedHeaders as $headerName => $headerPayload) {
            $headers[] = $this->hydrator->hydrate($this->messageHeaderRegistry->headerClass($headerName), $this->encoder->decode($headerPayload));
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
