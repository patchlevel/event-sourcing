<?php

namespace Patchlevel\EventSourcing\Metadata\Event;

use Patchlevel\EventSourcing\Serializer\Normalizer;
use ReflectionProperty;

class EventPropertyMetadata
{
    public string $fieldName;

    public ReflectionProperty $reflection;

    public ?Normalizer $normalizer = null;
}