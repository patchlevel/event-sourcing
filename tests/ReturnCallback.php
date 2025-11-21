<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests;

use PHPUnit\Framework\Assert;

use function array_shift;

final class ReturnCallback
{
    /** @param list<array{list<mixed>, mixed}> $series */
    public function __construct(
        private array $series,
    ) {
    }

    public function __invoke(mixed ...$args): mixed
    {
        [$expectedArgs, $return] = array_shift($this->series);
        Assert::assertEquals($expectedArgs, $args);

        return $return;
    }
}
