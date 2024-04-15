<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine;

use Patchlevel\EventSourcing\Subscription\Engine\CatchUpSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\Error;
use Patchlevel\EventSourcing\Subscription\Engine\ProcessedResult;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use Patchlevel\EventSourcing\Subscription\Subscription;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use RuntimeException;

/** @covers \Patchlevel\EventSourcing\Subscription\Engine\CatchUpSubscriptionEngine */
final class CatchUpSubscriptionEngineTest extends TestCase
{
    use ProphecyTrait;

    public function testSetup(): void
    {
        $parent = $this->prophesize(SubscriptionEngine::class);

        $engine = new CatchUpSubscriptionEngine($parent->reveal());
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new Result();

        $parent->setup($criteria, true)->willReturn($expectedResult)->shouldBeCalledOnce();
        $result = $engine->setup($criteria, true);

        self::assertSame($expectedResult, $result);
    }

    public function testBootFinished(): void
    {
        $parent = $this->prophesize(SubscriptionEngine::class);

        $engine = new CatchUpSubscriptionEngine($parent->reveal());
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new ProcessedResult(0);

        $parent->boot($criteria, 42)->willReturn($expectedResult)->shouldBeCalledTimes(1);
        $result = $engine->boot($criteria, 42);

        self::assertEquals($expectedResult, $result);
    }

    public function testBootSecondTime(): void
    {
        $parent = $this->prophesize(SubscriptionEngine::class);

        $engine = new CatchUpSubscriptionEngine($parent->reveal());
        $criteria = new SubscriptionEngineCriteria();

        $error = new Error(
            'foo',
            'bar',
            new RuntimeException('baz'),
        );

        $parent->boot($criteria, 42)->willReturn(
            new ProcessedResult(1),
            new ProcessedResult(0, true, [$error]),
        )->shouldBeCalledTimes(2);

        $result = $engine->boot($criteria, 42);

        self::assertEquals(new ProcessedResult(1, true, [$error]), $result);
    }

    public function testBootLimit(): void
    {
        $parent = $this->prophesize(SubscriptionEngine::class);

        $engine = new CatchUpSubscriptionEngine($parent->reveal(), 2);
        $criteria = new SubscriptionEngineCriteria();

        $parent->boot($criteria, 42)->willReturn(
            new ProcessedResult(1),
            new ProcessedResult(1),
        )->shouldBeCalledTimes(2);

        $result = $engine->boot($criteria, 42);

        self::assertEquals(new ProcessedResult(2), $result);
    }

    public function testRunFinished(): void
    {
        $parent = $this->prophesize(SubscriptionEngine::class);

        $engine = new CatchUpSubscriptionEngine($parent->reveal());
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new ProcessedResult(0);

        $parent->run($criteria, 42)->willReturn($expectedResult)->shouldBeCalledOnce();
        $result = $engine->run($criteria, 42);

        self::assertEquals($expectedResult, $result);
    }

    public function testRunSecondTime(): void
    {
        $parent = $this->prophesize(SubscriptionEngine::class);

        $engine = new CatchUpSubscriptionEngine($parent->reveal());
        $criteria = new SubscriptionEngineCriteria();

        $error = new Error(
            'foo',
            'bar',
            new RuntimeException('baz'),
        );

        $parent->run($criteria, 42)->willReturn(
            new ProcessedResult(1, true, [$error]),
            new ProcessedResult(0),
        )->shouldBeCalledTimes(2);
        $result = $engine->run($criteria, 42);

        self::assertEquals(new ProcessedResult(1, false, [$error]), $result);
    }

    public function testRunLimit(): void
    {
        $parent = $this->prophesize(SubscriptionEngine::class);

        $engine = new CatchUpSubscriptionEngine($parent->reveal(), 2);
        $criteria = new SubscriptionEngineCriteria();

        $parent->run($criteria, 42)->willReturn(
            new ProcessedResult(1),
            new ProcessedResult(1),
        )->shouldBeCalledTimes(2);

        $result = $engine->run($criteria, 42);

        self::assertEquals(new ProcessedResult(2), $result);
    }

    public function testTeardown(): void
    {
        $parent = $this->prophesize(SubscriptionEngine::class);

        $engine = new CatchUpSubscriptionEngine($parent->reveal());
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new Result();

        $parent->teardown($criteria)->willReturn($expectedResult)->shouldBeCalledOnce();
        $result = $engine->teardown($criteria);

        self::assertSame($expectedResult, $result);
    }

    public function testRemove(): void
    {
        $parent = $this->prophesize(SubscriptionEngine::class);

        $engine = new CatchUpSubscriptionEngine($parent->reveal());
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new Result();

        $parent->remove($criteria)->willReturn($expectedResult)->shouldBeCalledOnce();
        $result = $engine->remove($criteria);

        self::assertSame($expectedResult, $result);
    }

    public function testReactivate(): void
    {
        $parent = $this->prophesize(SubscriptionEngine::class);

        $engine = new CatchUpSubscriptionEngine($parent->reveal());
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new Result();

        $parent->reactivate($criteria)->willReturn($expectedResult)->shouldBeCalledOnce();
        $result = $engine->reactivate($criteria);

        self::assertSame($expectedResult, $result);
    }

    public function testPause(): void
    {
        $parent = $this->prophesize(SubscriptionEngine::class);

        $engine = new CatchUpSubscriptionEngine($parent->reveal());
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new Result();

        $parent->pause($criteria)->willReturn($expectedResult)->shouldBeCalledOnce();
        $result = $engine->pause($criteria);

        self::assertSame($expectedResult, $result);
    }

    public function testSubscriptions(): void
    {
        $parent = $this->prophesize(SubscriptionEngine::class);

        $engine = new CatchUpSubscriptionEngine($parent->reveal());
        $criteria = new SubscriptionEngineCriteria();

        $expectedSubscriptions = [new Subscription('foo')];

        $parent->subscriptions($criteria)->willReturn($expectedSubscriptions)->shouldBeCalledOnce();
        $subscriptions = $engine->subscriptions($criteria);

        self::assertEquals($expectedSubscriptions, $subscriptions);
    }
}
