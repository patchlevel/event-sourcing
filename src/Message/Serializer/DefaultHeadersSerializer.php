<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Message\Serializer;

use Patchlevel\EventSourcing\Metadata\Message\AttributeMessageHeaderRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Message\MessageHeaderRegistry;
use Patchlevel\EventSourcing\Serializer\Encoder\Encoder;
use Patchlevel\EventSourcing\Serializer\Encoder\JsonEncoder;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\MetadataHydrator;
use Psr\Log\LoggerInterface;
use Throwable;

use function is_array;
use function sprintf;

final class DefaultHeadersSerializer implements HeadersSerializer
{
    public function __construct(
        private readonly MessageHeaderRegistry $messageHeaderRegistry,
        private readonly Hydrator $hydrator,
        private readonly Encoder $encoder,
        private readonly bool $throwOnUnknownHeaders = true,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    /**
     * @param list<object>         $headers
     * @param array<string, mixed> $options
     */
    public function serialize(array $headers, array $options = []): string
    {
        $serializedHeaders = [];
        foreach ($headers as $header) {
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
        foreach ($serializedHeaders as $headerName => $headerPayload) {
            if (!is_array($headerPayload)) {
                throw new InvalidArgument('header payload must be an array');
            }

            try {
                $headerClass = $this->messageHeaderRegistry->headerClass($headerName);

                $headers[] = $this->hydrator->hydrate(
                    $headerClass,
                    $headerPayload,
                );
            } catch (Throwable $exception) {
                if ($this->throwOnUnknownHeaders) {
                    throw $exception;
                }

                $this->logger?->error(
                    sprintf(
                        'header %s could not be deserialized: %s',
                        $headerName,
                        $exception->getMessage(),
                    ),
                );

                $headers[] = new class ($headerName, $headerPayload) implements UnknownHeader {
                    /** @param array<array-key, mixed> $payload */
                    public function __construct(
                        private readonly string $name,
                        private readonly array $payload,
                    ) {
                    }

                    public function name(): string
                    {
                        return $this->name;
                    }

                    /** @return array<array-key, mixed> */
                    public function payload(): array
                    {
                        return $this->payload;
                    }
                };
            }
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

    public static function createDefault(): static
    {
        return new self(
            MessageHeaderRegistry::createWithInternalHeaders(),
            new MetadataHydrator(),
            new JsonEncoder(),
        );
    }
}
