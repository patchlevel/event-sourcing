<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Projection;

final class BatchProfileState
{
    /** @var array<string, string> */
    public array $nameChanged = [];
}
