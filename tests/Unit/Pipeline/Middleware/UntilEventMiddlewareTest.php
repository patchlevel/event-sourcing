<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline\Middleware;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Pipeline\Middleware\UntilEventMiddleware;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Pipeline\Middleware\UntilEventMiddleware */
final class UntilEventMiddlewareTest extends TestCase
{
    public function testPositive(): void
    {
        $until = new DateTimeImmutable('2020-02-02 00:00:00');

        $middleware = new UntilEventMiddleware($until);

        $message = Message::create(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de'),
            ),
        )->withHeader(new AggregateHeader('pofile', '1', 1, new DateTimeImmutable('2020-02-01 00:00:00')));

        $result = $middleware($message);

        self::assertSame([$message], $result);
    }

    public function testNegative(): void
    {
        $until = new DateTimeImmutable('2020-01-01 00:00:00');

        $middleware = new UntilEventMiddleware($until);

        $message = Message::create(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de'),
            ),
        )->withHeader(new AggregateHeader('pofile', '1', 1, new DateTimeImmutable('2020-02-01 00:00:00')));

        $result = $middleware($message);

        self::assertSame([], $result);
    }

    public function testWithoutHeader(): void
    {
        $until = new DateTimeImmutable('2020-01-01 00:00:00');

        $middleware = new UntilEventMiddleware($until);

        $event =  new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('hallo@patchlevel.de'),
        );

        $message = Message::create($event);

        $result = $middleware($message);

        self::assertSame([$message], $result);
    }
}
