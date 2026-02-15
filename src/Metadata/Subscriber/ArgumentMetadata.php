<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Subscriber;

use Symfony\Component\TypeInfo\Type;

final class ArgumentMetadata
{
    public function __construct(
        public readonly string $name,
        public readonly Type $type,
    ) {
    }
}
