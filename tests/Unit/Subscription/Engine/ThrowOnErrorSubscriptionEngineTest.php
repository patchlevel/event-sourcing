<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine;

use Patchlevel\EventSourcing\Subscription\Engine\Error;
use Patchlevel\EventSourcing\Subscription\Engine\ErrorDetected;
use Patchlevel\EventSourcing\Subscription\Engine\ProcessedResult;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use Patchlevel\EventSourcing\Subscription\Engine\ThrowOnErrorSubscriptionEngine;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use RuntimeException;

/** @covers \Patchlevel\EventSourcing\Subscription\Engine\ThrowOnErrorSubscriptionEngine */
final class ThrowOnErrorSubscriptionEngineTest extends TestCase
{
    use ProphecyTrait;

    public function testSetupSuccess(): void
    {
        $parent = $this->prophesize(SubscriptionEngine::class);

        $engine = new ThrowOnErrorSubscriptionEngine($parent->reveal());
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new Result();

        $parent->setup($criteria, true)->willReturn($expectedResult)->shouldBeCalledOnce();
        $result = $engine->setup($criteria, true);

        self::assertSame($expectedResult, $result);
    }

    public function testSetupError(): void
    {
        $this->expectException(ErrorDetected::class);

        $parent = $this->prophesize(SubscriptionEngine::class);

        $engine = new ThrowOnErrorSubscriptionEngine($parent->reveal());
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new Result([
            new Error('id1', 'error1', new RuntimeException('error1')),
            new Error('id2', 'error2', new RuntimeException('error2')),
        ]);

        $parent->setup($criteria, false)->willReturn($expectedResult)->shouldBeCalledOnce();
        $engine->setup($criteria);
    }

    public function testBootSuccess(): void
    {
        $parent = $this->prophesize(SubscriptionEngine::class);

        $engine = new ThrowOnErrorSubscriptionEngine($parent->reveal());
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new ProcessedResult(5);

        $parent->boot($criteria, 10)->willReturn($expectedResult)->shouldBeCalledOnce();
        $result = $engine->boot($criteria, 10);

        self::assertSame($expectedResult, $result);
    }

    public function testBootError(): void
    {
        $this->expectException(ErrorDetected::class);

        $parent = $this->prophesize(SubscriptionEngine::class);

        $engine = new ThrowOnErrorSubscriptionEngine($parent->reveal());
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new ProcessedResult(5, false, [
            new Error('id1', 'error1', new RuntimeException('error1')),
            new Error('id2', 'error2', new RuntimeException('error2')),
        ]);

        $parent->boot($criteria, 10)->willReturn($expectedResult)->shouldBeCalledOnce();
        $engine->boot($criteria, 10);
    }

    public function testRunSuccess(): void
    {
        $parent = $this->prophesize(SubscriptionEngine::class);

        $engine = new ThrowOnErrorSubscriptionEngine($parent->reveal());
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new ProcessedResult(5);

        $parent->run($criteria, 10)->willReturn($expectedResult)->shouldBeCalledOnce();
        $result = $engine->run($criteria, 10);

        self::assertSame($expectedResult, $result);
    }

    public function testRunError(): void
    {
        $this->expectException(ErrorDetected::class);

        $parent = $this->prophesize(SubscriptionEngine::class);

        $engine = new ThrowOnErrorSubscriptionEngine($parent->reveal());
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new ProcessedResult(5, false, [
            new Error('id1', 'error1', new RuntimeException('error1')),
            new Error('id2', 'error2', new RuntimeException('error2')),
        ]);

        $parent->run($criteria, 10)->willReturn($expectedResult)->shouldBeCalledOnce();
        $engine->run($criteria, 10);
    }

    public function testTeardownSuccess(): void
    {
        $parent = $this->prophesize(SubscriptionEngine::class);

        $engine = new ThrowOnErrorSubscriptionEngine($parent->reveal());
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new Result();

        $parent->teardown($criteria)->willReturn($expectedResult)->shouldBeCalledOnce();
        $result = $engine->teardown($criteria);

        self::assertSame($expectedResult, $result);
    }

    public function testTeardownError(): void
    {
        $this->expectException(ErrorDetected::class);

        $parent = $this->prophesize(SubscriptionEngine::class);

        $engine = new ThrowOnErrorSubscriptionEngine($parent->reveal());
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new Result([
            new Error('id1', 'error1', new RuntimeException('error1')),
            new Error('id2', 'error2', new RuntimeException('error2')),
        ]);

        $parent->teardown($criteria)->willReturn($expectedResult)->shouldBeCalledOnce();
        $engine->teardown($criteria);
    }

    public function testRemoveSuccess(): void
    {
        $parent = $this->prophesize(SubscriptionEngine::class);

        $engine = new ThrowOnErrorSubscriptionEngine($parent->reveal());
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new Result();

        $parent->remove($criteria)->willReturn($expectedResult)->shouldBeCalledOnce();
        $result = $engine->remove($criteria);

        self::assertSame($expectedResult, $result);
    }

    public function testRemoveError(): void
    {
        $this->expectException(ErrorDetected::class);

        $parent = $this->prophesize(SubscriptionEngine::class);

        $engine = new ThrowOnErrorSubscriptionEngine($parent->reveal());
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new Result([
            new Error('id1', 'error1', new RuntimeException('error1')),
            new Error('id2', 'error2', new RuntimeException('error2')),
        ]);

        $parent->remove($criteria)->willReturn($expectedResult)->shouldBeCalledOnce();
        $engine->remove($criteria);
    }

    public function testReactivateSuccess(): void
    {
        $parent = $this->prophesize(SubscriptionEngine::class);

        $engine = new ThrowOnErrorSubscriptionEngine($parent->reveal());
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new Result();

        $parent->reactivate($criteria)->willReturn($expectedResult)->shouldBeCalledOnce();
        $result = $engine->reactivate($criteria);

        self::assertSame($expectedResult, $result);
    }

    public function testReactivateError(): void
    {
        $this->expectException(ErrorDetected::class);

        $parent = $this->prophesize(SubscriptionEngine::class);

        $engine = new ThrowOnErrorSubscriptionEngine($parent->reveal());
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new Result([
            new Error('id1', 'error1', new RuntimeException('error1')),
            new Error('id2', 'error2', new RuntimeException('error2')),
        ]);

        $parent->reactivate($criteria)->willReturn($expectedResult)->shouldBeCalledOnce();
        $engine->reactivate($criteria);
    }

    public function testPauseSuccess(): void
    {
        $parent = $this->prophesize(SubscriptionEngine::class);

        $engine = new ThrowOnErrorSubscriptionEngine($parent->reveal());
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new Result();

        $parent->pause($criteria)->willReturn($expectedResult)->shouldBeCalledOnce();
        $result = $engine->pause($criteria);

        self::assertSame($expectedResult, $result);
    }

    public function testPauseError(): void
    {
        $this->expectException(ErrorDetected::class);

        $parent = $this->prophesize(SubscriptionEngine::class);

        $engine = new ThrowOnErrorSubscriptionEngine($parent->reveal());
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new Result([
            new Error('id1', 'error1', new RuntimeException('error1')),
            new Error('id2', 'error2', new RuntimeException('error2')),
        ]);

        $parent->pause($criteria)->willReturn($expectedResult)->shouldBeCalledOnce();
        $engine->pause($criteria);
    }

    public function testSubscriptions(): void
    {
        $parent = $this->prophesize(SubscriptionEngine::class);

        $engine = new ThrowOnErrorSubscriptionEngine($parent->reveal());
        $criteria = new SubscriptionEngineCriteria();

        $parent->subscriptions($criteria)->willReturn([])->shouldBeCalledOnce();
        $result = $engine->subscriptions($criteria);

        self::assertSame([], $result);
    }
}
