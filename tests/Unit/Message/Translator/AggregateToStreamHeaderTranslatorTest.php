<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Message\Translator;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Translator\AggregateToStreamHeaderTranslator;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

#[CoversClass(AggregateToStreamHeaderTranslator::class)]
final class AggregateToStreamHeaderTranslatorTest extends TestCase
{
    use ProphecyTrait;

    public function testMissingHeader(): void
    {
        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de'),
            ),
        );

        $middleware = new AggregateToStreamHeaderTranslator();

        $result = $middleware($message);

        self::assertEquals([$message], $result);
    }

    public function testMigrateHeader(): void
    {
        $aggregateHeader = new AggregateHeader(
            'profile',
            '1',
            1,
            new DateTimeImmutable(),
        );

        $message = (new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de'),
            ),
        ))->withHeader($aggregateHeader);

        $middleware = new AggregateToStreamHeaderTranslator();

        $result = $middleware($message);

        self::assertCount(1, $result);

        $message = $result[0];

        self::assertFalse($message->hasHeader(AggregateHeader::class));

        self::assertEquals($aggregateHeader->recordedOn, $message->header(RecordedOnHeader::class)->recordedOn);
        self::assertEquals('profile-1', $message->header(StreamNameHeader::class)->streamName);
        self::assertEquals(1, $message->header(PlayheadHeader::class)->playhead);
    }
}
