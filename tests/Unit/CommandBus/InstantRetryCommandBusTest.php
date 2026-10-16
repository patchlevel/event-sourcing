<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\CommandBus;

use Patchlevel\EventSourcing\Attribute\InstantRetry;
use Patchlevel\EventSourcing\CommandBus\CommandBus;
use Patchlevel\EventSourcing\CommandBus\InstantRetryCommandBus;
use Patchlevel\EventSourcing\Repository\AggregateOutdated;
use Patchlevel\EventSourcing\Store\AppendCondition;
use Patchlevel\EventSourcing\Store\AppendConditionNotMet;
use Patchlevel\EventSourcing\Store\Query;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
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
                $this->throwException(new AggregateOutdated(Profile::class, ProfileId::fromString('profile'))),
                $this->throwException(new AggregateOutdated(Profile::class, ProfileId::fromString('profile'))),
                null, // Success on the third attempt
            );

        $retryCommandBus = new InstantRetryCommandBus($innerCommandBus);

        $retryCommandBus->dispatch($command);
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
                    Profile::class,
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
                    Profile::class,
                    ProfileId::fromString('profile'),
                ),
            );

        $retryCommandBus = new InstantRetryCommandBus($innerCommandBus);

        $this->expectException(AggregateOutdated::class);
        $retryCommandBus->dispatch($command);
    }

    public function testDispatchRetriesWithoutAttribute(): void
    {
        $command = new class {
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
                $this->throwException(new AggregateOutdated(Profile::class, ProfileId::fromString('profile'))),
                $this->throwException(new AggregateOutdated(Profile::class, ProfileId::fromString('profile'))),
                null, // Success on the third attempt
            );

        $retryCommandBus = new InstantRetryCommandBus($innerCommandBus);
        $retryCommandBus->dispatch($command);
    }

    public function testDispatchThrowsAfterMaxRetriesWithoutAttribute(): void
    {
        $command = new class {
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
                    Profile::class,
                    ProfileId::fromString('profile'),
                ),
            );

        $retryCommandBus = new InstantRetryCommandBus($innerCommandBus);

        $this->expectException(AggregateOutdated::class);
        $retryCommandBus->dispatch($command);
    }

    public function testDispatchRetriesAppendConditionNotMetWithoutAttribute(): void
    {
        $command = new class {
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
                $this->throwException(new AppendConditionNotMet(new AppendCondition(new Query()))),
                $this->throwException(new AppendConditionNotMet(new AppendCondition(new Query()))),
                null, // Success on the third attempt
            );

        $retryCommandBus = new InstantRetryCommandBus($innerCommandBus);
        $retryCommandBus->dispatch($command);
    }

    public function testDispatchThrowsAppendConditionNotMetAfterMaxRetriesWithoutAttribute(): void
    {
        $command = new class {
            public function __construct()
            {
            }
        };

        $innerCommandBus = $this->createMock(CommandBus::class);
        $innerCommandBus
            ->expects($this->exactly(4))
            ->method('dispatch')
            ->with($command)
            ->willThrowException(new AppendConditionNotMet(new AppendCondition(new Query())));

        $retryCommandBus = new InstantRetryCommandBus($innerCommandBus);

        $this->expectException(AppendConditionNotMet::class);
        $retryCommandBus->dispatch($command);
    }

    public function testSkipOtherExceptionsWithoutAttribute(): void
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
            ->willThrowException(new RuntimeException('Some other exception'));

        $retryCommandBus = new InstantRetryCommandBus($innerCommandBus);

        $this->expectException(RuntimeException::class);
        $retryCommandBus->dispatch($command);
    }

    public function testDispatchWithoutAttributeUsesConfiguredDefaults(): void
    {
        $command = new class {
            public function __construct()
            {
            }
        };

        $innerCommandBus = $this->createMock(CommandBus::class);
        $innerCommandBus
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->with($command)
            ->willThrowException(new RuntimeException('Some other exception'));

        $retryCommandBus = new InstantRetryCommandBus($innerCommandBus, 1, [RuntimeException::class]);

        $this->expectException(RuntimeException::class);
        $retryCommandBus->dispatch($command);
    }

    public function testDispatchNotRetryWithoutAttributeAndDefaultExceptions(): void
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
                    Profile::class,
                    ProfileId::fromString('profile'),
                ),
            );

        $retryCommandBus = new InstantRetryCommandBus($innerCommandBus, 3, []);

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
    }
}
