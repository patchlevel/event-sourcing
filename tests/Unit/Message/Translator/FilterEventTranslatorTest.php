<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Message\Translator;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Translator\FilterEventTranslator;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Message\Translator\FilterEventTranslator */
final class FilterEventTranslatorTest extends TestCase
{
    public function testPositive(): void
    {
        $translator = new FilterEventTranslator(static function (object $event) {
            return $event instanceof ProfileCreated;
        });

        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de'),
            ),
        );

        $result = $translator($message);

        self::assertSame([$message], $result);
    }

    public function testNegative(): void
    {
        $translator = new FilterEventTranslator(static function (object $event) {
            return $event instanceof ProfileCreated;
        });

        $message = new Message(
            new ProfileVisited(
                ProfileId::fromString('1'),
            ),
        );

        $result = $translator($message);

        self::assertSame([], $result);
    }
}
