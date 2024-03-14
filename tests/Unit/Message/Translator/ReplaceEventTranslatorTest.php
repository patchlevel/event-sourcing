<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Message\Translator;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Translator\ReplaceEventTranslator;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\MessagePublished;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Message\Translator\ReplaceEventTranslator */
final class ReplaceEventTranslatorTest extends TestCase
{
    public function testReplace(): void
    {
        $translator = new ReplaceEventTranslator(
            ProfileCreated::class,
            static function (ProfileCreated $event) {
                return new ProfileVisited(
                    $event->profileId,
                );
            },
        );

        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de'),
            ),
        );

        $result = $translator($message);

        self::assertCount(1, $result);

        $event = $result[0]->event();

        self::assertInstanceOf(ProfileVisited::class, $event);
    }

    public function testReplaceInvalidClass(): void
    {
        /** @psalm-suppress InvalidArgument */
        $translator = new ReplaceEventTranslator(
            MessagePublished::class,
            static function (ProfileCreated $event) {
                return new ProfileVisited(
                    $event->profileId,
                );
            },
        );

        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de'),
            ),
        );

        $result = $translator($message);

        self::assertCount(1, $result);

        $event = $result[0]->event();

        self::assertInstanceOf(ProfileCreated::class, $event);
    }
}
