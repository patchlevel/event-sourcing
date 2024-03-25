<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Message\Translator;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Translator\ExcludeArchivedEventTranslator;
use Patchlevel\EventSourcing\Store\ArchivedHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Message\Translator\ExcludeArchivedEventTranslator */
final class ExcludeArchivedEventTranslatorTest extends TestCase
{
    public function testExcludedEvent(): void
    {
        $translator = new ExcludeArchivedEventTranslator();

        $message = Message::create(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de'),
            ),
        )->withHeader(new ArchivedHeader(true));

        $result = $translator($message);

        self::assertSame([], $result);
    }

    public function testIncludeEvent(): void
    {
        $translator = new ExcludeArchivedEventTranslator();

        $message = Message::create(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de'),
            ),
        )->withHeader(new ArchivedHeader(false));

        $result = $translator($message);

        self::assertSame([$message], $result);
    }

    public function testHeaderNotSet(): void
    {
        $translator = new ExcludeArchivedEventTranslator();

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
