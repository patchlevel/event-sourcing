<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Telemetry;

use Patchlevel\EventSourcing\Subscription\Engine\Command\Boot;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Run;
use Patchlevel\EventSourcing\Subscription\Engine\Error;
use Patchlevel\EventSourcing\Subscription\Engine\ProcessedResult;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Telemetry\TraceableSubscriptionEngine;
use Patchlevel\EventSourcing\Telemetry\TraceAttributes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(TraceableSubscriptionEngine::class)]
final class TraceableSubscriptionEngineTest extends TestCase
{
    use InMemoryTracer;

    public function testExecuteRunAddsProcessedResultAttributes(): void
    {
        $command = new Run(['profile_projection'], ['reporting']);
        $result = new ProcessedResult(5, true);

        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->once())
            ->method('execute')
            ->with($command)
            ->willReturn($result);

        $traceableEngine = new TraceableSubscriptionEngine($engine, $this->createTracerProvider());

        self::assertSame($result, $traceableEngine->execute($command));

        $span = $this->span();
        $attributes = $span->getAttributes();

        self::assertSame('event_sourcing.subscription.run', $span->getName());
        self::assertSame('profile_projection', $attributes->get(TraceAttributes::SUBSCRIPTION_ID));
        self::assertSame('reporting', $attributes->get(TraceAttributes::SUBSCRIPTION_GROUP));
        self::assertSame(5, $attributes->get(TraceAttributes::SUBSCRIPTION_PROCESSED_MESSAGES));
        self::assertTrue($attributes->get(TraceAttributes::SUBSCRIPTION_FINISHED));
        self::assertSame(0, $attributes->get(TraceAttributes::ERROR_COUNT));
    }

    public function testExecuteBootCountsErrors(): void
    {
        $command = new Boot();
        $result = new Result([new Error('profile_projection', 'ERROR', new RuntimeException('ERROR'))]);

        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->once())
            ->method('execute')
            ->with($command)
            ->willReturn($result);

        $traceableEngine = new TraceableSubscriptionEngine($engine, $this->createTracerProvider());
        $traceableEngine->execute($command);

        $span = $this->span();

        self::assertSame('event_sourcing.subscription.boot', $span->getName());
        self::assertSame(1, $span->getAttributes()->get(TraceAttributes::ERROR_COUNT));
        self::assertFalse($span->getAttributes()->has(TraceAttributes::SUBSCRIPTION_PROCESSED_MESSAGES));
    }

    public function testSubscriptions(): void
    {
        $criteria = new SubscriptionEngineCriteria();
        $subscriptions = [new Subscription('profile_projection')];

        $engine = $this->createMock(SubscriptionEngine::class);
        $engine
            ->expects($this->once())
            ->method('subscriptions')
            ->with($criteria)
            ->willReturn($subscriptions);

        $traceableEngine = new TraceableSubscriptionEngine($engine, $this->createTracerProvider());

        self::assertSame($subscriptions, $traceableEngine->subscriptions($criteria));
        self::assertSame('event_sourcing.subscription.subscriptions', $this->span()->getName());
    }
}
