<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests;

use PHPUnit\Framework\Assert;

use function array_shift;

final class ReturnCallback
{
    /** @param list<array{0: list<mixed>, 1?: mixed}> $series */
    public function __construct(
        private array $series,
    ) {
    }

    public function __invoke(mixed ...$args): mixed
    {
        $paramReturnTuple = array_shift($this->series);
        Assert::assertNotNull($paramReturnTuple);
        Assert::assertEquals($paramReturnTuple[0], $args);

        return $paramReturnTuple[1] ?? null;
    }
}
