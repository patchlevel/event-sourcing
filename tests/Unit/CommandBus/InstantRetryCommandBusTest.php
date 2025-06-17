<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\CommandBus;

use Patchlevel\EventSourcing\Attribute\InstantRetry;
use Patchlevel\EventSourcing\CommandBus\CommandBus;
use Patchlevel\EventSourcing\CommandBus\InstantRetryCommandBus;
use Patchlevel\EventSourcing\Repository\AggregateOutdated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InstantRetryCommandBusTest extends TestCase
{
    public function testSuccess(): void
    {
        $command = new #[InstantRetry]
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

        $retryCommandBus = new InstantRetryCommandBus($innerCommandBus);

        $retryCommandBus->dispatch($command);

        $this->assertTrue(true); // If no exception is thrown, the test passes
    }

    public function testDispatchRetriesUntilSuccess(): void
    {
        $command = new #[InstantRetry]
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

        $retryCommandBus = new InstantRetryCommandBus($innerCommandBus);

        $retryCommandBus->dispatch($command);

        $this->assertTrue(true); // If no exception is thrown, the test passes
    }

    public function testDispatchThrowsAfterMaxRetries(): void
    {
        $command = new #[InstantRetry]
        class {
            public function __construct()
            {
            }
        };

        $innerCommandBus = $this->createMock(CommandBus::class);
        $innerCommandBus
            ->expects($this->exactly(4))
            ->method('dispatch')
            ->with($command)
            ->willThrowException(
                new AggregateOutdated(
                    'profile',
                    ProfileId::fromString('profile'),
                ),
            );

        $retryCommandBus = new InstantRetryCommandBus($innerCommandBus);

        $this->expectException(AggregateOutdated::class);

        $retryCommandBus->dispatch($command);
    }

    public function testDispatchThrowsAfterMaxRetriesWithOverride(): void
    {
        $command = new #[InstantRetry(maxRetries: 2)]
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

        $retryCommandBus = new InstantRetryCommandBus($innerCommandBus);

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

        $retryCommandBus = new InstantRetryCommandBus($innerCommandBus);

        $this->expectException(AggregateOutdated::class);

        $retryCommandBus->dispatch($command);
    }

    public function testSkipOtherExceptions(): void
    {
        $command = new #[InstantRetry(maxRetries: 2)]
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

        $retryCommandBus = new InstantRetryCommandBus($innerCommandBus);

        $this->expectException(RuntimeException::class);

        $retryCommandBus->dispatch($command);
    }

    public function testOverrideException(): void
    {
        $command = new #[InstantRetry(exceptions: [RuntimeException::class])]
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
                $this->throwException(new RuntimeException()),
                $this->throwException(new RuntimeException()),
                null, // Success on the third attempt
            );

        $retryCommandBus = new InstantRetryCommandBus($innerCommandBus);

        $retryCommandBus->dispatch($command);

        $this->assertTrue(true); // If no exception is thrown, the test passes
    }
}
