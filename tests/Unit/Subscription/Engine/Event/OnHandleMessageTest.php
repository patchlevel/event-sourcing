<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine\Event;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessage;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OnHandleMessage::class)]
final class OnHandleMessageTest extends TestCase
{
    public function testInstantiate(): void
    {
        $message = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('foo@bar.com')));
        $subscription = new Subscription('foo');

        $event = new OnHandleMessage($subscription, $message);

        self::assertSame($subscription, $event->subscription);
        self::assertSame($message, $event->message);
    }
}
