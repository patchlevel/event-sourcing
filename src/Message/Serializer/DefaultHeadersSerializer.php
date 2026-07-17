<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Message\Serializer;

use Patchlevel\EventSourcing\Metadata\Message\AttributeMessageHeaderRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Message\MessageHeaderRegistry;
use Patchlevel\EventSourcing\Serializer\Encoder\Encoder;
use Patchlevel\EventSourcing\Serializer\Encoder\JsonEncoder;
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\StackHydratorBuilder;

use function is_array;

final class DefaultHeadersSerializer implements HeadersSerializer
{
    private readonly Hydrator $hydrator;

    public function __construct(
        private readonly MessageHeaderRegistry $messageHeaderRegistry,
        Hydrator|null $hydrator = null,
        private readonly Encoder $encoder = new JsonEncoder(),
    ) {
        $this->hydrator = $hydrator ?? self::defaultHydrator();
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

            $headers[] = $this->hydrator->hydrate(
                $this->messageHeaderRegistry->headerClass($headerName),
                $headerPayload,
            );
        }

        return $headers;
    }

    /** @param list<string> $paths */
    public static function createFromPaths(
        array $paths,
        Hydrator|null $hydrator = null,
    ): static {
        return new self(
            (new AttributeMessageHeaderRegistryFactory())->create($paths),
            $hydrator,
            new JsonEncoder(),
        );
    }

    public static function createDefault(Hydrator|null $hydrator = null): static
    {
        return new self(
            MessageHeaderRegistry::createWithInternalHeaders(),
            $hydrator,
            new JsonEncoder(),
        );
    }

    private static function defaultHydrator(): Hydrator
    {
        return (new StackHydratorBuilder())
            ->useExtension(new CoreExtension())
            ->build();
    }
}
