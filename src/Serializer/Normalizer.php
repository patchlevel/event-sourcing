<?php

namespace Patchlevel\EventSourcing\Serializer;

interface Normalizer
{
    public function normalize(mixed $price): mixed;

    public function denormalize(mixed $value): mixed;
}