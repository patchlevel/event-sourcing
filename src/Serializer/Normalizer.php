<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer;

interface Normalizer
{
    public function normalize(mixed $value): mixed;

    public function denormalize(mixed $value): mixed;
}
