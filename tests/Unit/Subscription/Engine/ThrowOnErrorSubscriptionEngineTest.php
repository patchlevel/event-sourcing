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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ThrowOnErrorSubscriptionEngine::class)]
final class ThrowOnErrorSubscriptionEngineTest extends TestCase
{
    public function testSetupSuccess(): void
    {
        $parent = $this->createMock(SubscriptionEngine::class);

        $engine = new ThrowOnErrorSubscriptionEngine($parent);
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new Result();

        $parent->expects($this->once())->method('setup')->with($criteria, true)->willReturn($expectedResult);
        $result = $engine->setup($criteria, true);

        self::assertSame($expectedResult, $result);
    }

    public function testSetupError(): void
    {
        $this->expectException(ErrorDetected::class);

        $parent = $this->createMock(SubscriptionEngine::class);

        $engine = new ThrowOnErrorSubscriptionEngine($parent);
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new Result([
            new Error('id1', 'error1', new RuntimeException('error1')),
            new Error('id2', 'error2', new RuntimeException('error2')),
        ]);

        $parent->expects($this->once())->method('setup')->with($criteria, false)->willReturn($expectedResult);
        $engine->setup($criteria);
    }

    public function testBootSuccess(): void
    {
        $parent = $this->createMock(SubscriptionEngine::class);

        $engine = new ThrowOnErrorSubscriptionEngine($parent);
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new ProcessedResult(5);

        $parent->expects($this->once())->method('boot')->with($criteria, 10)->willReturn($expectedResult);
        $result = $engine->boot($criteria, 10);

        self::assertSame($expectedResult, $result);
    }

    public function testBootError(): void
    {
        $this->expectException(ErrorDetected::class);

        $parent = $this->createMock(SubscriptionEngine::class);

        $engine = new ThrowOnErrorSubscriptionEngine($parent);
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new ProcessedResult(5, false, [
            new Error('id1', 'error1', new RuntimeException('error1')),
            new Error('id2', 'error2', new RuntimeException('error2')),
        ]);

        $parent->expects($this->once())->method('boot')->with($criteria, 10)->willReturn($expectedResult);
        $engine->boot($criteria, 10);
    }

    public function testRunSuccess(): void
    {
        $parent = $this->createMock(SubscriptionEngine::class);

        $engine = new ThrowOnErrorSubscriptionEngine($parent);
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new ProcessedResult(5);

        $parent->expects($this->once())->method('run')->with($criteria, 10)->willReturn($expectedResult);
        $result = $engine->run($criteria, 10);

        self::assertSame($expectedResult, $result);
    }

    public function testRunError(): void
    {
        $this->expectException(ErrorDetected::class);

        $parent = $this->createMock(SubscriptionEngine::class);

        $engine = new ThrowOnErrorSubscriptionEngine($parent);
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new ProcessedResult(5, false, [
            new Error('id1', 'error1', new RuntimeException('error1')),
            new Error('id2', 'error2', new RuntimeException('error2')),
        ]);

        $parent->expects($this->once())->method('run')->with($criteria, 10)->willReturn($expectedResult);
        $engine->run($criteria, 10);
    }

    public function testTeardownSuccess(): void
    {
        $parent = $this->createMock(SubscriptionEngine::class);

        $engine = new ThrowOnErrorSubscriptionEngine($parent);
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new Result();

        $parent->expects($this->once())->method('teardown')->with($criteria)->willReturn($expectedResult);
        $result = $engine->teardown($criteria);

        self::assertSame($expectedResult, $result);
    }

    public function testTeardownError(): void
    {
        $this->expectException(ErrorDetected::class);

        $parent = $this->createMock(SubscriptionEngine::class);

        $engine = new ThrowOnErrorSubscriptionEngine($parent);
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new Result([
            new Error('id1', 'error1', new RuntimeException('error1')),
            new Error('id2', 'error2', new RuntimeException('error2')),
        ]);

        $parent->expects($this->once())->method('teardown')->with($criteria)->willReturn($expectedResult);
        $engine->teardown($criteria);
    }

    public function testRemoveSuccess(): void
    {
        $parent = $this->createMock(SubscriptionEngine::class);

        $engine = new ThrowOnErrorSubscriptionEngine($parent);
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new Result();

        $parent->expects($this->once())->method('remove')->with($criteria)->willReturn($expectedResult);
        $result = $engine->remove($criteria);

        self::assertSame($expectedResult, $result);
    }

    public function testRemoveError(): void
    {
        $this->expectException(ErrorDetected::class);

        $parent = $this->createMock(SubscriptionEngine::class);

        $engine = new ThrowOnErrorSubscriptionEngine($parent);
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new Result([
            new Error('id1', 'error1', new RuntimeException('error1')),
            new Error('id2', 'error2', new RuntimeException('error2')),
        ]);

        $parent->expects($this->once())->method('remove')->with($criteria)->willReturn($expectedResult);
        $engine->remove($criteria);
    }

    public function testReactivateSuccess(): void
    {
        $parent = $this->createMock(SubscriptionEngine::class);

        $engine = new ThrowOnErrorSubscriptionEngine($parent);
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new Result();

        $parent->expects($this->once())->method('reactivate')->with($criteria)->willReturn($expectedResult);
        $result = $engine->reactivate($criteria);

        self::assertSame($expectedResult, $result);
    }

    public function testReactivateError(): void
    {
        $this->expectException(ErrorDetected::class);

        $parent = $this->createMock(SubscriptionEngine::class);

        $engine = new ThrowOnErrorSubscriptionEngine($parent);
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new Result([
            new Error('id1', 'error1', new RuntimeException('error1')),
            new Error('id2', 'error2', new RuntimeException('error2')),
        ]);

        $parent->expects($this->once())->method('reactivate')->with($criteria)->willReturn($expectedResult);
        $engine->reactivate($criteria);
    }

    public function testPauseSuccess(): void
    {
        $parent = $this->createMock(SubscriptionEngine::class);

        $engine = new ThrowOnErrorSubscriptionEngine($parent);
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new Result();

        $parent->expects($this->once())->method('pause')->with($criteria)->willReturn($expectedResult);
        $result = $engine->pause($criteria);

        self::assertSame($expectedResult, $result);
    }

    public function testPauseError(): void
    {
        $this->expectException(ErrorDetected::class);

        $parent = $this->createMock(SubscriptionEngine::class);

        $engine = new ThrowOnErrorSubscriptionEngine($parent);
        $criteria = new SubscriptionEngineCriteria();

        $expectedResult = new Result([
            new Error('id1', 'error1', new RuntimeException('error1')),
            new Error('id2', 'error2', new RuntimeException('error2')),
        ]);

        $parent->expects($this->once())->method('pause')->with($criteria)->willReturn($expectedResult);
        $engine->pause($criteria);
    }

    public function testSubscriptions(): void
    {
        $parent = $this->createMock(SubscriptionEngine::class);

        $engine = new ThrowOnErrorSubscriptionEngine($parent);
        $criteria = new SubscriptionEngineCriteria();

        $parent->expects($this->once())->method('subscriptions')->with($criteria)->willReturn([]);
        $result = $engine->subscriptions($criteria);

        self::assertSame([], $result);
    }
}
