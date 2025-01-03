<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Test;

use Closure;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Constraint\Exception as ExceptionConstraint;
use PHPUnit\Framework\Constraint\ExceptionMessageIsOrContains;
use PHPUnit\Framework\TestCase;
use Throwable;

abstract class AggregateRootTestCase extends TestCase
{
    /** @var array<object> */
    private array $givenEvents = [];

    /** @var array<Closure> */
    private array $whens = [];

    /** @var array<object> */
    private array $expectedEvents = [];
    /** @var class-string<Throwable>|null  */
    private string|null $expectedException = null;
    private string|null $expectedExceptionMessage = null;

    /** @return class-string<AggregateRoot> */
    abstract protected function aggregateClass(): string;

    public function given(object ...$events): self
    {
        $this->givenEvents = $events;

        return $this;
    }

    public function when(Closure ...$callables): self
    {
        $this->whens = $callables;

        return $this;
    }

    public function then(object ...$events): self
    {
        $this->expectedEvents = $events;

        return $this;
    }

    /** @param class-string<Throwable> $exception */
    public function expectsException(string $exception): self
    {
        $this->expectedException = $exception;

        return $this;
    }

    public function expectsExceptionMessage(string $exceptionMessage): self
    {
        $this->expectedExceptionMessage = $exceptionMessage;

        return $this;
    }

    #[After]
    public function assert(): self
    {
        $aggregate = null;

        if ($this->givenEvents) {
            $aggregate = $this->aggregateClass()::createFromEvents($this->givenEvents);
        }

        try {
            foreach ($this->whens as $callable) {
                $return = $callable($aggregate);

                if ($aggregate !== null && $return instanceof AggregateRoot) {
                    throw new AggregateAlreadySet();
                }

                if ($aggregate === null && !$return instanceof AggregateRoot) {
                    throw new NoAggregateCreated();
                }

                if ($aggregate !== null || !($return instanceof AggregateRoot)) {
                    continue;
                }

                $aggregate = $return;
            }
        } catch (Throwable $throwable) {
            $this->handleException($throwable);
        }

        if (!$aggregate instanceof AggregateRoot) {
            throw new NoAggregateCreated();
        }

        $events = $aggregate->releaseEvents();

        self::assertEquals($this->expectedEvents, $events, 'The events doesn\'t match the expected events.');

        return $this;
    }

    #[Before]
    public function reset(): void
    {
        $this->givenEvents = [];
        $this->whens = [];
        $this->expectedEvents = [];
        $this->expectedException = null;
        $this->expectedExceptionMessage = null;
    }

    private function handleException(Throwable $throwable): void
    {
        if ($this->expectedException === null && $this->expectedExceptionMessage === null) {
            throw $throwable;
        }

        if ($this->expectedException) {
            $this->assertThat(
                $throwable,
                new ExceptionConstraint(
                    $this->expectedException,
                ),
            );
        }

        if (!$this->expectedExceptionMessage) {
            return;
        }

        $this->assertThat(
            $throwable->getMessage(),
            new ExceptionMessageIsOrContains(
                $this->expectedExceptionMessage,
            ),
        );
    }
}
