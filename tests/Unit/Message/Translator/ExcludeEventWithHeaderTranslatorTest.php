<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Message\Translator;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Translator\ExcludeEventWithHeaderTranslator;
use Patchlevel\EventSourcing\Store\ArchivedHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Message\Translator\ExcludeEventWithHeaderTranslator */
final class ExcludeEventWithHeaderTranslatorTest extends TestCase
{
    public function testExcludedEvent(): void
    {
        $translator = new ExcludeEventWithHeaderTranslator(ArchivedHeader::class);

        $message = Message::create(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de'),
            ),
        )->withHeader(new ArchivedHeader());

        $result = $translator($message);

        self::assertSame([], $result);
    }

    public function testIncludeEvent(): void
    {
        $translator = new ExcludeEventWithHeaderTranslator(ArchivedHeader::class);

        $message = Message::create(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de'),
            ),
        );

        $result = $translator($message);

        self::assertSame([$message], $result);
    }
}
