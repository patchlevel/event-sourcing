<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Message\Serializer;

use Patchlevel\EventSourcing\Message\MissingHeaders;
use Patchlevel\EventSourcing\Metadata\Message\AttributeMessageHeaderRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Message\HeaderNameNotRegistered;
use Patchlevel\EventSourcing\Metadata\Message\MessageHeaderRegistry;
use Patchlevel\EventSourcing\Serializer\Encoder\Encoder;
use Patchlevel\EventSourcing\Serializer\Encoder\JsonEncoder;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\MetadataHydrator;

use function in_array;
use function is_array;

final class DefaultHeadersSerializer implements HeadersSerializer
{
    private bool $handleAllHeadersGraceful;

    /** @param list<string> $gracefulMissingHeaders */
    public function __construct(
        private readonly MessageHeaderRegistry $messageHeaderRegistry,
        private readonly Hydrator $hydrator,
        private readonly Encoder $encoder,
        private readonly array $gracefulMissingHeaders = [],
    ) {
        $this->handleAllHeadersGraceful = in_array('*', $this->gracefulMissingHeaders, true);
    }

    /**
     * @param list<object>         $headers
     * @param array<string, mixed> $options
     */
    public function serialize(array $headers, array $options = []): string
    {
        $serializedHeaders = [];
        foreach ($headers as $header) {
            if ($header instanceof MissingHeaders) {
                foreach ($header->headers as $name => $payload) {
                    $serializedHeaders[$name] = $payload;
                }

                continue;
            }

            $serializedHeaders[$this->messageHeaderRegistry->headerName($header::class)] = $this->hydrator->extract($header);
        }

        return $this->encoder->encode($serializedHeaders, $options);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return list<object>
     */
    public function deserialize(string $string, array $options = []): array
    {
        $serializedHeaders = $this->encoder->decode($string, $options);

        $headers = [];
        $missingHeaders = [];

        foreach ($serializedHeaders as $headerName => $headerPayload) {
            if (!is_array($headerPayload)) {
                throw new InvalidArgument('header payload must be an array');
            }

            try {
                $headers[] = $this->hydrator->hydrate(
                    $this->messageHeaderRegistry->headerClass($headerName),
                    $headerPayload,
                );
            } catch (HeaderNameNotRegistered $exception) {
                if (!$this->handleAllHeadersGraceful && !in_array($headerName, $this->gracefulMissingHeaders, true)) {
                    throw $exception;
                }

                $missingHeaders[$headerName] = $headerPayload;
            }
        }

        if ($missingHeaders !== []) {
            $headers[] = new MissingHeaders($missingHeaders);
        }

        return $headers;
    }

    /**
     * @param list<string> $paths
     * @param list<string> $gracefulMissingHeaders
     */
    public static function createFromPaths(array $paths, array $gracefulMissingHeaders = []): static
    {
        return new self(
            (new AttributeMessageHeaderRegistryFactory())->create($paths),
            new MetadataHydrator(),
            new JsonEncoder(),
            $gracefulMissingHeaders,
        );
    }

    public static function createDefault(): static
    {
        return new self(
            MessageHeaderRegistry::createWithInternalHeaders(),
            new MetadataHydrator(),
            new JsonEncoder(),
            [],
        );
    }
}
