<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Message\Translator;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Translator\ChainTranslator;
use Patchlevel\EventSourcing\Message\Translator\Translator;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Message\Translator\ChainTranslator */
final class ChainTranslatorTest extends TestCase
{
    use ProphecyTrait;

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

        $child1 = $this->prophesize(Translator::class);
        $child1->__invoke($message1)->willReturn([$message2, $message3])->shouldBeCalled();

        $child2 = $this->prophesize(Translator::class);
        $child2->__invoke($message2)->willReturn([$message4, $message5])->shouldBeCalled();
        $child2->__invoke($message3)->willReturn([$message3])->shouldBeCalled();

        $translator = new ChainTranslator([
            $child1->reveal(),
            $child2->reveal(),
        ]);

        self::assertSame([$message4, $message5, $message3], $translator($message1));
    }
}
