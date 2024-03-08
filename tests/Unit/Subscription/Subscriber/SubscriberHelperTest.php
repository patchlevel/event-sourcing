<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Subscriber;

use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberHelper;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberHelper */
final class SubscriberHelperTest extends TestCase
{
    public function testSubscriberId(): void
    {
        $subscriber = new #[Subscriber('dummy', RunMode::FromBeginning)]
        class {
        };

        $helper = new SubscriberHelper();

        self::assertEquals('dummy', $helper->subscriberId($subscriber));
    }
}
