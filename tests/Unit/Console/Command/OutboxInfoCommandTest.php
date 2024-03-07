<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Console\Command\OutboxInfoCommand;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\EventBus\Serializer\HeadersSerializer;
use Patchlevel\EventSourcing\Outbox\OutboxStore;
use Patchlevel\EventSourcing\Serializer\Encoder\Encoder;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/** @covers \Patchlevel\EventSourcing\Console\Command\OutboxInfoCommand */
final class OutboxInfoCommandTest extends TestCase
{
    use ProphecyTrait;

    public function testSuccessful(): void
    {
        $event = new ProfileVisited(ProfileId::fromString('1'));
        $message = Message::create($event)
            ->withHeader(new AggregateHeader('profile', '1', 1, new DateTimeImmutable()));

        $store = $this->prophesize(OutboxStore::class);
        $store->retrieveOutboxMessages(null)->willReturn([$message]);

        $serializer = $this->prophesize(EventSerializer::class);
        $serializer->serialize($event, [Encoder::OPTION_PRETTY_PRINT => true])->willReturn(
            new SerializedEvent(
                'profile.visited',
                '{"visitorId": "1"}',
            ),
        );

        $headersSerializer = $this->prophesize(HeadersSerializer::class);
        $headersSerializer->serialize($message->headers(), [Encoder::OPTION_PRETTY_PRINT => true])->willReturn([
            [
                'name' => 'aggregate',
                'payload' => '{"aggregateName":"profile","aggregateId":"1","playhead":1,"recordedOn":"2020-01-01T20:00:00+01:00"}',
            ],
        ]);

        $command = new OutboxInfoCommand(
            $store->reveal(),
            $serializer->reveal(),
            $headersSerializer->reveal(),
        );

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('"visitorId": "1"', $content);
    }

    public function testSuccessfulWithLimit(): void
    {
        $event = new ProfileVisited(ProfileId::fromString('1'));
        $message = Message::create($event)
            ->withHeader(new AggregateHeader('profile', '1', 1, new DateTimeImmutable()));

        $store = $this->prophesize(OutboxStore::class);
        $store->retrieveOutboxMessages(100)->willReturn([$message]);

        $serializer = $this->prophesize(EventSerializer::class);
        $serializer->serialize($event, [Encoder::OPTION_PRETTY_PRINT => true])->willReturn(
            new SerializedEvent(
                'profile.visited',
                '{"visitorId": "1"}',
            ),
        );

        $headersSerializer = $this->prophesize(HeadersSerializer::class);
        $headersSerializer->serialize($message->headers(), [Encoder::OPTION_PRETTY_PRINT => true])->willReturn([
            [
                'name' => 'aggregate',
                'payload' => '{"aggregateName":"profile","aggregateId":"1","playhead":1,"recordedOn":"2020-01-01T20:00:00+01:00"}',
            ],
        ]);

        $command = new OutboxInfoCommand(
            $store->reveal(),
            $serializer->reveal(),
            $headersSerializer->reveal(),
        );

        $input = new ArrayInput(['--limit' => 100]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('"visitorId": "1"', $content);
    }
}
