<?php

namespace Patchlevel\EventSourcing\Repository\MessageDecorator;

final class Trace
{
    public function __construct(
        public readonly string $name,
        public readonly string $category,
    )
    {
    }
}