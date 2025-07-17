<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Message\Translator;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Translator\RecalculatePlayheadTranslator;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RecalculatePlayheadTranslator::class)]
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
            ->withHeader(new StreamNameHeader('profile'))
            ->withHeader(new PlayheadHeader(5));

        $result = $translator($message);

        self::assertCount(1, $result);
        self::assertSame('profile', $result[0]->header(StreamNameHeader::class)->streamName);
        self::assertSame(1, $result[0]->header(PlayheadHeader::class)->playhead);
    }

    public function testRecalculatePlayheadWithSamePlayhead(): void
    {
        $translator = new RecalculatePlayheadTranslator();

        $event =  new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('hallo@patchlevel.de'),
        );

        $message = Message::create($event)
            ->withHeader(new StreamNameHeader('profile'))
            ->withHeader(new PlayheadHeader(1));

        $result = $translator($message);

        self::assertEquals([$message], $result);
    }

    public function testRecalculateMultipleMessages(): void
    {
        $translator = new RecalculatePlayheadTranslator();

        $event =  new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('hallo@patchlevel.de'),
        );

        $message = Message::create($event)
            ->withHeader(new StreamNameHeader('profile'))
            ->withHeader(new PlayheadHeader(5));

        $result = $translator($message);

        self::assertCount(1, $result);
        self::assertSame('profile', $result[0]->header(StreamNameHeader::class)->streamName);
        self::assertSame(1, $result[0]->header(PlayheadHeader::class)->playhead);

        $message = Message::create($event)
            ->withHeader(new StreamNameHeader('profile'))
            ->withHeader(new PlayheadHeader(8));

        $result = $translator($message);

        self::assertCount(1, $result);
        self::assertSame('profile', $result[0]->header(StreamNameHeader::class)->streamName);
        self::assertSame(2, $result[0]->header(PlayheadHeader::class)->playhead);
    }

    public function testReset(): void
    {
        $translator = new RecalculatePlayheadTranslator();

        $event =  new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('hallo@patchlevel.de'),
        );

        $message = Message::create($event)
            ->withHeader(new StreamNameHeader('profile'))
            ->withHeader(new PlayheadHeader(5));

        $result = $translator($message);

        self::assertCount(1, $result);
        self::assertSame('profile', $result[0]->header(StreamNameHeader::class)->streamName);
        self::assertSame(1, $result[0]->header(PlayheadHeader::class)->playhead);

        $message = Message::create($event)
            ->withHeader(new StreamNameHeader('profile'))
            ->withHeader(new PlayheadHeader(8));

        $translator->reset();
        $result = $translator($message);

        self::assertCount(1, $result);
        self::assertSame('profile', $result[0]->header(StreamNameHeader::class)->streamName);
        self::assertSame(1, $result[0]->header(PlayheadHeader::class)->playhead);
    }
}
