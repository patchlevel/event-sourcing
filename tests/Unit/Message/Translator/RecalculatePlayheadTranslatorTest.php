<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Message\Translator;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Translator\RecalculatePlayheadTranslator;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Message\Translator\RecalculatePlayheadTranslator */
final class RecalculatePlayheadTranslatorTest extends TestCase
{
    public function testRecalculatePlayhead(): void
    {
        $translator = new RecalculatePlayheadTranslator();

        $event =  new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('hallo@patchlevel.de'),
        );

        $message = Message::create($event)
            ->withHeader(new AggregateHeader('profile', '1', 5, new DateTimeImmutable()));

        $result = $translator($message);

        self::assertCount(1, $result);
        self::assertSame('profile', $result[0]->header(AggregateHeader::class)->aggregateName);
        self::assertSame(1, $result[0]->header(AggregateHeader::class)->playhead);
    }

    public function testRecalculatePlayheadWithSamePlayhead(): void
    {
        $translator = new RecalculatePlayheadTranslator();

        $event =  new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('hallo@patchlevel.de'),
        );

        $message = Message::create($event)
            ->withHeader(new AggregateHeader('profile', '1', 1, new DateTimeImmutable()));

        $result = $translator($message);

        self::assertSame([$message], $result);
    }

    public function testRecalculateMultipleMessages(): void
    {
        $translator = new RecalculatePlayheadTranslator();

        $event =  new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('hallo@patchlevel.de'),
        );

        $message = Message::create($event)
            ->withHeader(new AggregateHeader('profile', '1', 5, new DateTimeImmutable()));
        $result = $translator($message);

        self::assertCount(1, $result);
        self::assertSame('profile', $result[0]->header(AggregateHeader::class)->aggregateName);
        self::assertSame(1, $result[0]->header(AggregateHeader::class)->playhead);

        $message = Message::create($event)
            ->withHeader(new AggregateHeader('profile', '1', 8, new DateTimeImmutable()));

        $result = $translator($message);

        self::assertCount(1, $result);
        self::assertSame('profile', $result[0]->header(AggregateHeader::class)->aggregateName);
        self::assertSame(2, $result[0]->header(AggregateHeader::class)->playhead);
    }

    public function testReset(): void
    {
        $translator = new RecalculatePlayheadTranslator();

        $event =  new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('hallo@patchlevel.de'),
        );

        $message = Message::create($event)
            ->withHeader(new AggregateHeader('profile', '1', 5, new DateTimeImmutable()));
        $result = $translator($message);

        self::assertCount(1, $result);
        self::assertSame('profile', $result[0]->header(AggregateHeader::class)->aggregateName);
        self::assertSame(1, $result[0]->header(AggregateHeader::class)->playhead);

        $message = Message::create($event)
            ->withHeader(new AggregateHeader('profile', '1', 8, new DateTimeImmutable()));

        $translator->reset();
        $result = $translator($message);

        self::assertCount(1, $result);
        self::assertSame('profile', $result[0]->header(AggregateHeader::class)->aggregateName);
        self::assertSame(1, $result[0]->header(AggregateHeader::class)->playhead);
    }
}
