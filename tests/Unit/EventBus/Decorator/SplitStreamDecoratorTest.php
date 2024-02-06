<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\EventBus\Decorator;

use Patchlevel\EventSourcing\EventBus\Decorator\SplitStreamDecorator;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\SplittingEvent;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\EventBus\Decorator\SplitStreamDecorator */
final class SplitStreamDecoratorTest extends TestCase
{
    use ProphecyTrait;

    public function testWithoutSplittingStream(): void
    {
        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de'),
            ),
        );

        $decorator = new SplitStreamDecorator(new AttributeEventMetadataFactory());
        $decorator($message);

        self::assertFalse($message->newStreamStart());
    }

    public function testSplittingStream(): void
    {
        $message = new Message(
            new SplittingEvent(
                Email::fromString('info@patchlevel.de'),
                1,
            ),
        );

        $decorator = new SplitStreamDecorator(new AttributeEventMetadataFactory());
        $decoratedMessage = $decorator($message);

        self::assertTrue($decoratedMessage->newStreamStart());
    }
}
