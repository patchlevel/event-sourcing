<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine;

use LogicException;
use Patchlevel\EventSourcing\Subscription\Engine\CanRefreshSubscriptions;
use Patchlevel\EventSourcing\Subscription\Engine\CatchUpSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\Error;
use Patchlevel\EventSourcing\Subscription\Engine\ProcessedResult;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use Patchlevel\EventSourcing\Subscription\Subscription;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(CatchUpSubscriptionEngine::class)]
final class CatchUpSubscriptionEngineTest extends TestCase
{
    public function testSetup(): void
    {
        $parent = $this->createMock(SubscriptionEngine::class);

        $engine = new CatchUpSubscriptionEngine($parent);
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new Result();

        $parent->expects($this->once())->method('setup')->with($criteria, true)->willReturn($expectedResult);
        $result = $engine->setup($criteria, true);

        self::assertSame($expectedResult, $result);
    }

    public function testBootFinished(): void
    {
        $parent = $this->createMock(SubscriptionEngine::class);

        $engine = new CatchUpSubscriptionEngine($parent);
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new ProcessedResult(0);

        $parent->expects($this->exactly(1))->method('boot')->with($criteria, 42)->willReturn($expectedResult);
        $result = $engine->boot($criteria, 42);

        self::assertEquals($expectedResult, $result);
    }

    public function testBootSecondTime(): void
    {
        $parent = $this->createMock(SubscriptionEngine::class);

        $engine = new CatchUpSubscriptionEngine($parent);
        $criteria = new SubscriptionEngineCriteria();

        $error = new Error(
            'foo',
            'bar',
            new RuntimeException('baz'),
        );

        $parent->expects($this->exactly(2))->method('boot')->with($criteria, 42)->willReturn(
            new ProcessedResult(1),
            new ProcessedResult(0, true, [$error]),
        );

        $result = $engine->boot($criteria, 42);

        self::assertEquals(new ProcessedResult(1, true, [$error]), $result);
    }

    public function testBootLimit(): void
    {
        $parent = $this->createMock(SubscriptionEngine::class);

        $engine = new CatchUpSubscriptionEngine($parent, 2);
        $criteria = new SubscriptionEngineCriteria();

        $parent->expects($this->exactly(2))->method('boot')->with($criteria, 42)->willReturn(
            new ProcessedResult(1),
            new ProcessedResult(1),
        );

        $result = $engine->boot($criteria, 42);

        self::assertEquals(new ProcessedResult(2), $result);
    }

    public function testRunFinished(): void
    {
        $parent = $this->createMock(SubscriptionEngine::class);

        $engine = new CatchUpSubscriptionEngine($parent);
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new ProcessedResult(0);

        $parent->expects($this->once())->method('run')->with($criteria, 42)->willReturn($expectedResult);
        $result = $engine->run($criteria, 42);

        self::assertEquals($expectedResult, $result);
    }

    public function testRunSecondTime(): void
    {
        $parent = $this->createMock(SubscriptionEngine::class);

        $engine = new CatchUpSubscriptionEngine($parent);
        $criteria = new SubscriptionEngineCriteria();

        $error = new Error(
            'foo',
            'bar',
            new RuntimeException('baz'),
        );

        $parent->expects($this->exactly(2))->method('run')->with($criteria, 42)->willReturn(
            new ProcessedResult(1, true, [$error]),
            new ProcessedResult(0),
        );
        $result = $engine->run($criteria, 42);

        self::assertEquals(new ProcessedResult(1, false, [$error]), $result);
    }

    public function testRunLimit(): void
    {
        $parent = $this->createMock(SubscriptionEngine::class);

        $engine = new CatchUpSubscriptionEngine($parent, 2);
        $criteria = new SubscriptionEngineCriteria();

        $parent->expects($this->exactly(2))->method('run')->with($criteria, 42)->willReturn(
            new ProcessedResult(1),
            new ProcessedResult(1),
        );

        $result = $engine->run($criteria, 42);

        self::assertEquals(new ProcessedResult(2), $result);
    }

    public function testTeardown(): void
    {
        $parent = $this->createMock(SubscriptionEngine::class);

        $engine = new CatchUpSubscriptionEngine($parent);
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new Result();

        $parent->expects($this->once())->method('teardown')->with($criteria)->willReturn($expectedResult);
        $result = $engine->teardown($criteria);

        self::assertSame($expectedResult, $result);
    }

    public function testRemove(): void
    {
        $parent = $this->createMock(SubscriptionEngine::class);

        $engine = new CatchUpSubscriptionEngine($parent);
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new Result();

        $parent->expects($this->once())->method('remove')->with($criteria)->willReturn($expectedResult);
        $result = $engine->remove($criteria);

        self::assertSame($expectedResult, $result);
    }

    public function testReactivate(): void
    {
        $parent = $this->createMock(SubscriptionEngine::class);

        $engine = new CatchUpSubscriptionEngine($parent);
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new Result();

        $parent->expects($this->once())->method('reactivate')->with($criteria)->willReturn($expectedResult);
        $result = $engine->reactivate($criteria);

        self::assertSame($expectedResult, $result);
    }

    public function testPause(): void
    {
        $parent = $this->createMock(SubscriptionEngine::class);

        $engine = new CatchUpSubscriptionEngine($parent);
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new Result();

        $parent->expects($this->once())->method('pause')->with($criteria)->willReturn($expectedResult);
        $result = $engine->pause($criteria);

        self::assertSame($expectedResult, $result);
    }

    public function testSubscriptions(): void
    {
        $parent = $this->createMock(SubscriptionEngine::class);

        $engine = new CatchUpSubscriptionEngine($parent);
        $criteria = new SubscriptionEngineCriteria();

        $expectedSubscriptions = [new Subscription('foo')];

        $parent->expects($this->once())->method('subscriptions')->with($criteria)->willReturn($expectedSubscriptions);
        $subscriptions = $engine->subscriptions($criteria);

        self::assertEquals($expectedSubscriptions, $subscriptions);
    }

    public function testRefreshSubscriptions(): void
    {
        $parent = $this->createMockForIntersectionOfInterfaces([
            SubscriptionEngine::class,
            CanRefreshSubscriptions::class,
        ]);

        $engine = new CatchUpSubscriptionEngine($parent);
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new Result();

        $parent->expects($this->once())->method('refresh')->with($criteria)->willReturn($expectedResult);
        $result = $engine->refresh($criteria);

        self::assertSame($expectedResult, $result);
    }

    public function testRefreshSubscriptionsNotSupported(): void
    {
        $parent = $this->createMock(SubscriptionEngine::class);

        $engine = new CatchUpSubscriptionEngine($parent);

        $this->expectException(LogicException::class);
        $engine->refresh();
    }
}
