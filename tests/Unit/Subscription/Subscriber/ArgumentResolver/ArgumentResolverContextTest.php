<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Subscriber\ArgumentResolver;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadata;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\ArgumentResolverContext;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArgumentResolverContext::class)]
final class ArgumentResolverContextTest extends TestCase
{
    public function testInstantiate(): void
    {
        $message = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('foo@bar.com')));
        $subscription = new Subscription('foo');
        $metadata = new SubscriberMetadata('foo');

        $context = new ArgumentResolverContext($message, $subscription, $metadata);

        self::assertSame($message, $context->message);
        self::assertSame($subscription, $context->subscription);
        self::assertSame($metadata, $context->subscriber);
    }
}
