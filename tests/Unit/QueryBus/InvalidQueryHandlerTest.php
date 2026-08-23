<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\QueryBus;

use Patchlevel\EventSourcing\QueryBus\InvalidQueryHandler;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\QueryProfile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(InvalidQueryHandler::class)]
final class InvalidQueryHandlerTest extends TestCase
{
    public function testNoHandler(): void
    {
        $exception = InvalidQueryHandler::noHandler(QueryProfile::class);

        self::assertSame(
            sprintf('No handler found for query %s', QueryProfile::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }

    public function testMultipleHandler(): void
    {
        $exception = InvalidQueryHandler::multipleHandler(QueryProfile::class);

        self::assertSame(
            sprintf('Multiple handlers found for query %s', QueryProfile::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
