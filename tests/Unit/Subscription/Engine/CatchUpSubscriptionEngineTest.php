<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine;

use Patchlevel\EventSourcing\Subscription\Engine\CatchUpSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Run;
use Patchlevel\EventSourcing\Subscription\Engine\Error;
use Patchlevel\EventSourcing\Subscription\Engine\ProcessedResult;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use Patchlevel\EventSourcing\Subscription\Subscription;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(CatchUpSubscriptionEngine::class)]
final class CatchUpSubscriptionEngineTest extends TestCase
{
    public function testRunFinished(): void
    {
        $parent = $this->createMock(SubscriptionEngine::class);

        $engine = new CatchUpSubscriptionEngine($parent);

        $expectedResult = new ProcessedResult(0);
        $command = new Run();

        $parent->expects($this->once())->method('execute')->with($command)->willReturn($expectedResult);
        $result = $engine->execute($command);

        self::assertEquals($expectedResult, $result);
    }

    public function testRunSecondTime(): void
    {
        $parent = $this->createMock(SubscriptionEngine::class);

        $engine = new CatchUpSubscriptionEngine($parent);
        $command = new Run();

        $error = new Error(
            'foo',
            'bar',
            new RuntimeException('baz'),
        );

        $parent->expects($this->exactly(2))->method('execute')->with($command)->willReturn(
            new ProcessedResult(1, true, [$error]),
            new ProcessedResult(0),
        );
        $result = $engine->execute($command);

        self::assertEquals(new ProcessedResult(1, false, [$error]), $result);
    }

    public function testRunLimit(): void
    {
        $parent = $this->createMock(SubscriptionEngine::class);

        $engine = new CatchUpSubscriptionEngine($parent, 2);
        $command = new Run();

        $parent->expects($this->exactly(2))->method('execute')->with($command)->willReturn(
            new ProcessedResult(1),
            new ProcessedResult(1),
        );

        $result = $engine->execute($command);

        self::assertEquals(new ProcessedResult(2), $result);
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
}
