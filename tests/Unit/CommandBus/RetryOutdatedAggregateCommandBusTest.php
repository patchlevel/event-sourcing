<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\CommandBus;

use Patchlevel\EventSourcing\Attribute\RetryAggregateOutdated;
use Patchlevel\EventSourcing\CommandBus\CommandBus;
use Patchlevel\EventSourcing\CommandBus\RetryOutdatedAggregateCommandBus;
use Patchlevel\EventSourcing\Repository\AggregateOutdated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RetryOutdatedAggregateCommandBusTest extends TestCase
{
    public function testSuccess(): void
    {
        $command = new #[RetryAggregateOutdated(maxRetries: 3)]
        class {
            public function __construct()
            {
            }
        };

        $innerCommandBus = $this->createMock(CommandBus::class);
        $innerCommandBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($command);

        $retryCommandBus = new RetryOutdatedAggregateCommandBus($innerCommandBus);

        $retryCommandBus->dispatch($command);

        $this->assertTrue(true); // If no exception is thrown, the test passes
    }

    public function testDispatchRetriesUntilSuccess(): void
    {
        $command = new #[RetryAggregateOutdated(maxRetries: 3)]
        class {
            public function __construct()
            {
            }
        };

        $innerCommandBus = $this->createMock(CommandBus::class);
        $innerCommandBus
            ->expects($this->exactly(3))
            ->method('dispatch')
            ->with($command)
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new AggregateOutdated('profile', ProfileId::fromString('profile'))),
                $this->throwException(new AggregateOutdated('profile', ProfileId::fromString('profile'))),
                null, // Success on the third attempt
            );

        $retryCommandBus = new RetryOutdatedAggregateCommandBus($innerCommandBus);

        $retryCommandBus->dispatch($command);

        $this->assertTrue(true); // If no exception is thrown, the test passes
    }

    public function testDispatchThrowsAfterMaxRetries(): void
    {
        $command = new #[RetryAggregateOutdated(maxRetries: 2)]
        class {
            public function __construct()
            {
            }
        };

        $innerCommandBus = $this->createMock(CommandBus::class);
        $innerCommandBus
            ->expects($this->exactly(3))
            ->method('dispatch')
            ->with($command)
            ->willThrowException(
                new AggregateOutdated(
                    'profile',
                    ProfileId::fromString('profile'),
                ),
            );

        $retryCommandBus = new RetryOutdatedAggregateCommandBus($innerCommandBus);

        $this->expectException(AggregateOutdated::class);

        $retryCommandBus->dispatch($command);
    }

    public function testDispatchNotRetry(): void
    {
        $command = new class {
            public function __construct()
            {
            }
        };

        $innerCommandBus = $this->createMock(CommandBus::class);
        $innerCommandBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($command)
            ->willThrowException(
                new AggregateOutdated(
                    'profile',
                    ProfileId::fromString('profile'),
                ),
            );

        $retryCommandBus = new RetryOutdatedAggregateCommandBus($innerCommandBus);

        $this->expectException(AggregateOutdated::class);

        $retryCommandBus->dispatch($command);
    }

    public function testSkipOtherExceptions(): void
    {
        $command = new #[RetryAggregateOutdated(maxRetries: 2)]
        class {
            public function __construct()
            {
            }
        };

        $innerCommandBus = $this->createMock(CommandBus::class);
        $innerCommandBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($command)
            ->willThrowException(new RuntimeException('Some other exception'));

        $retryCommandBus = new RetryOutdatedAggregateCommandBus($innerCommandBus);

        $this->expectException(RuntimeException::class);

        $retryCommandBus->dispatch($command);
    }
}
