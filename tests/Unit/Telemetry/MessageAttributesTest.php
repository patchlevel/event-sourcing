<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Telemetry;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\CausationIdHeader;
use Patchlevel\EventSourcing\Store\Header\CorrelationIdHeader;
use Patchlevel\EventSourcing\Store\Header\EventIdHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Telemetry\MessageAttributes;
use Patchlevel\EventSourcing\Telemetry\TraceAttributes;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessageAttributes::class)]
final class MessageAttributesTest extends TestCase
{
    public function testAllHeaders(): void
    {
        $message = Message::create(new ProfileVisited(ProfileId::fromString('1')))
            ->withHeader(new EventIdHeader('event-1'))
            ->withHeader(new CorrelationIdHeader('correlation-1'))
            ->withHeader(new CausationIdHeader('causation-1'))
            ->withHeader(new StreamNameHeader('profile-1'));

        self::assertSame(
            [
                TraceAttributes::EVENT_NAME => ProfileVisited::class,
                TraceAttributes::MESSAGING_MESSAGE_ID => 'event-1',
                TraceAttributes::MESSAGING_MESSAGE_CONVERSATION_ID => 'correlation-1',
                TraceAttributes::CAUSATION_ID => 'causation-1',
                TraceAttributes::MESSAGING_DESTINATION_NAME => 'profile-1',
            ],
            MessageAttributes::from($message),
        );
    }

    public function testWithoutHeaders(): void
    {
        $message = Message::create(new ProfileVisited(ProfileId::fromString('1')));

        self::assertSame(
            [
                TraceAttributes::EVENT_NAME => ProfileVisited::class,
                TraceAttributes::MESSAGING_MESSAGE_ID => null,
                TraceAttributes::MESSAGING_MESSAGE_CONVERSATION_ID => null,
                TraceAttributes::CAUSATION_ID => null,
                TraceAttributes::MESSAGING_DESTINATION_NAME => null,
            ],
            MessageAttributes::from($message),
        );
    }
}
