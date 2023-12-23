<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projector;

use Patchlevel\EventSourcing\Attribute\Projection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;
use ReflectionClass;

abstract class BasicProjector implements Projector
{
    private ProjectionId|null $projectionId = null;

    public function targetProjection(): ProjectionId
    {
        if ($this->projectionId) {
            return $this->projectionId;
        }

        $reflection = new ReflectionClass($this);
        $attributes = $reflection->getAttributes(Projection::class);

        if ($attributes === []) {
            throw new ProjectionAttributeNotFound($reflection->getName());
        }

        $attribute = $attributes[0]->newInstance();

        $this->projectionId = new ProjectionId(
            $attribute->name(),
            $attribute->version(),
        );

        return $this->projectionId;
    }
}
