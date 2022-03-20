<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Projection;

/**
 * @internal
 */
final class ProjectionMetadata
{
    /** @var array<class-string, ProjectionHandleMetadata> */
    public array $handleMethods = [];

    public ?string $createMethod = null;

    public ?string $dropMethod = null;
}
