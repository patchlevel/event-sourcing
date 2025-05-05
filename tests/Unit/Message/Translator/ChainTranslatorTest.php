<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Message\Translator;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Translator\ChainTranslator;
use Patchlevel\EventSourcing\Message\Translator\Translator;
use Patchlevel\EventSourcing\Tests\ReturnCallback;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChainTranslator::class)]
final class ChainTranslatorTest extends TestCase
{
    public function testEmptyChain(): void
    {
        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de'),
            ),
        );

        $translator = new ChainTranslator([]);

        self::assertSame([$message], $translator($message));
    }

    public function testChain(): void
    {
        $message1 = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de'),
            ),
        );

        $message2 = new Message(
            new ProfileCreated(
                ProfileId::fromString('2'),
                Email::fromString('hallo@patchlevel.de'),
            ),
        );
        $message3 = new Message(
            new ProfileCreated(
                ProfileId::fromString('3'),
                Email::fromString('hallo@patchlevel.de'),
            ),
        );

        $message4 = new Message(
            new ProfileCreated(
                ProfileId::fromString('4'),
                Email::fromString('hallo@patchlevel.de'),
            ),
        );
        $message5 = new Message(
            new ProfileCreated(
                ProfileId::fromString('5'),
                Email::fromString('hallo@patchlevel.de'),
            ),
        );

        $child1 = $this->createMock(Translator::class);
        $child1
            ->expects($this->once())
            ->method('__invoke')
            ->with($message1)
            ->willReturn([$message2, $message3]);

        $child2 = $this->createMock(Translator::class);
        $child2
            ->expects($this->exactly(2))
            ->method('__invoke')
            ->willReturnCallback(
                new ReturnCallback([
                    [
                        [$message2],
                        [$message4, $message5],
                    ],
                    [
                        [$message3],
                        [$message3],
                    ],
                ]),
            );

        $translator = new ChainTranslator([
            $child1,
            $child2,
        ]);

        self::assertSame([$message4, $message5, $message3], $translator($message1));
    }
}
