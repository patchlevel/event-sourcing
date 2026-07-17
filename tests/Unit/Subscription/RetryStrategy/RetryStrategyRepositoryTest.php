<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\RetryStrategy;

use Patchlevel\EventSourcing\Subscription\RetryStrategy\NoRetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategyNotFound;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategyRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RetryStrategyRepository::class)]
final class RetryStrategyRepositoryTest extends TestCase
{
    public function testRetryStrategyNotFound(): void
    {
        $this->expectException(RetryStrategyNotFound::class);

        $repository = new RetryStrategyRepository([]);
        $repository->get('notfound');
    }

    public function testDefaultRetryStrategyNotFound(): void
    {
        $this->expectException(RetryStrategyNotFound::class);

        $repository = new RetryStrategyRepository([]);
        $repository->getDefaultRetryStrategy();
    }

    public function testDefaultRetryStrategy(): void
    {
        $strategy = $this->createMock(RetryStrategy::class);

        $repository = new RetryStrategyRepository(
            ['default' => $strategy],
        );

        self::assertSame($strategy, $repository->getDefaultRetryStrategy());
    }

    public function testGetRetryStrategy(): void
    {
        $strategy = $this->createMock(RetryStrategy::class);

        $repository = new RetryStrategyRepository(
            ['test' => $strategy],
        );

        self::assertSame($strategy, $repository->get('test'));
    }

    public function testWithDefault(): void
    {
        $strategy = new NoRetryStrategy();

        $repository = RetryStrategyRepository::withDefault($strategy);

        self::assertSame($strategy, $repository->getDefaultRetryStrategy());
    }

}
