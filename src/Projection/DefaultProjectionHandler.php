<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\EventBus\Message;

use function array_key_exists;

final class DefaultProjectionHandler implements ProjectionHandler
{
    /** @var iterable<Projection> */
    private iterable $projections;

    private ProjectionMetadataFactory $metadataFactor;

    /**
     * @param iterable<Projection> $projections
     */
    public function __construct(iterable $projections, ?ProjectionMetadataFactory $metadataFactory = null)
    {
        $this->projections = $projections;
        $this->metadataFactor = $metadataFactory ?? new AttributeProjectionMetadataFactory();
    }

    public function handle(Message $message): void
    {
        $event = $message->event();

        foreach ($this->projections as $projection) {
            $metadata = $this->metadataFactor->metadata($projection);

            if (!array_key_exists($event::class, $metadata->handleMethods)) {
                continue;
            }

            $handleMetadata = $metadata->handleMethods[$event::class];
            $method = $handleMetadata->methodName;

            if ($handleMetadata->passMessage) {
                $projection->$method($message);

                continue;
            }

            $projection->$method($event);
        }
    }

    public function create(): void
    {
        foreach ($this->projections as $projection) {
            $metadata = $this->metadataFactor->metadata($projection);
            $method = $metadata->createMethod;

            if (!$method) {
                continue;
            }

            $projection->$method();
        }
    }

    public function drop(): void
    {
        foreach ($this->projections as $projection) {
            $metadata = $this->metadataFactor->metadata($projection);
            $method = $metadata->dropMethod;

            if (!$method) {
                continue;
            }

            $projection->$method();
        }
    }

    /**
     * @return iterable<Projection>
     */
    public function projections(): iterable
    {
        return $this->projections;
    }

    public function metadataFactory(): ProjectionMetadataFactory
    {
        return $this->metadataFactor;
    }
}
