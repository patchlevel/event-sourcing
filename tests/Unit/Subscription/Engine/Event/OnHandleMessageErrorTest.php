<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine\Event;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessageError;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(OnHandleMessageError::class)]
final class OnHandleMessageErrorTest extends TestCase
{
    public function testInstantiate(): void
    {
        $message = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('foo@bar.com')));
        $subscription = new Subscription('foo');
        $throwable = new RuntimeException('error');

        $event = new OnHandleMessageError($subscription, $throwable, $message, 42);

        self::assertSame($subscription, $event->subscription);
        self::assertSame($throwable, $event->throwable);
        self::assertSame($message, $event->message);
        self::assertSame(42, $event->index);
        self::assertFalse($event->transitionToFailed);
    }
}
