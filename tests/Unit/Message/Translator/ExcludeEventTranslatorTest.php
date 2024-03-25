<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Message\Translator;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Translator\ExcludeEventTranslator;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Message\Translator\ExcludeEventTranslator */
final class ExcludeEventTranslatorTest extends TestCase
{
    public function testDeleteEvent(): void
    {
        $translator = new ExcludeEventTranslator([ProfileCreated::class]);

        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de'),
            ),
        );

        $result = $translator($message);

        self::assertSame([], $result);
    }

    public function testSkipEvent(): void
    {
        $translator = new ExcludeEventTranslator([ProfileCreated::class]);

        $message = new Message(
            new ProfileVisited(
                ProfileId::fromString('1'),
            ),
        );

        $result = $translator($message);

        self::assertSame([$message], $result);
    }
}
