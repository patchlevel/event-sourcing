<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Repository\MessageDecorator;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Repository\MessageDecorator\ChainMessageDecorator;
use Patchlevel\EventSourcing\Repository\MessageDecorator\MessageDecorator;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChainMessageDecorator::class)]
final class ChainMessageDecoratorTest extends TestCase
{
    public function testChain(): void
    {
        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de'),
            ),
        );

        $decorator1 = $this->createMock(MessageDecorator::class);
        $decorator1->expects($this->atLeastOnce())->method('__invoke')->with($message)->willReturn($message);

        $decorator2 = $this->createMock(MessageDecorator::class);
        $decorator2->expects($this->atLeastOnce())->method('__invoke')->with($message)->willReturn($message);

        $chain = new ChainMessageDecorator([
            $decorator1,
            $decorator2,
        ]);

        $chain($message);
    }
}
