<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine;

use Patchlevel\EventSourcing\Subscription\Engine\Command\Setup;
use Patchlevel\EventSourcing\Subscription\Engine\Error;
use Patchlevel\EventSourcing\Subscription\Engine\ErrorDetected;
use Patchlevel\EventSourcing\Subscription\Engine\ProcessedResult;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use Patchlevel\EventSourcing\Subscription\Engine\ThrowOnErrorSubscriptionEngine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ThrowOnErrorSubscriptionEngine::class)]
final class ThrowOnErrorSubscriptionEngineTest extends TestCase
{
    public function testRunSuccess(): void
    {
        $parent = $this->createMock(SubscriptionEngine::class);

        $engine = new ThrowOnErrorSubscriptionEngine($parent);

        $expectedResult = new ProcessedResult(5);

        $command = new Setup();

        $parent->expects($this->once())->method('run')->with($command)->willReturn($expectedResult);
        $result = $engine->run($command);

        self::assertSame($expectedResult, $result);
    }

    public function testRunError(): void
    {
        $this->expectException(ErrorDetected::class);

        $parent = $this->createMock(SubscriptionEngine::class);

        $engine = new ThrowOnErrorSubscriptionEngine($parent);

        $command = new Setup();

        $expectedResult = new ProcessedResult(5, false, [
            new Error('id1', 'error1', new RuntimeException('error1')),
            new Error('id2', 'error2', new RuntimeException('error2')),
        ]);

        $parent->expects($this->once())->method('run')->with($command)->willReturn($expectedResult);
        $engine->run($command);
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
