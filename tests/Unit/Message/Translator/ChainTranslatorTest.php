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

    public function testChain(): void
    {
        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de'),
            ),
        );

        $child1 = $this->prophesize(Translator::class);
        $child1->__invoke($message)->willReturn([$message])->shouldBeCalled();

        $child2 = $this->prophesize(Translator::class);
        $child2->__invoke($message)->willReturn([$message])->shouldBeCalled();

        $translator = new ChainTranslator([
            $child1->reveal(),
            $child2->reveal(),
        ]);

        $translator($message);
    }
}
