<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Console\Command\OutboxInfoCommand;
use Patchlevel\EventSourcing\EventBus\Message;
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

        $store = $this->prophesize(OutboxStore::class);
        $store->retrieveOutboxMessages(null)->willReturn([
            Message::create($event)
                ->withAggregateName('profile')
                ->withAggregateId('1')
                ->withPlayhead(1)
                ->withRecordedOn(new DateTimeImmutable()),
        ]);

        $serializer = $this->prophesize(EventSerializer::class);
        $serializer->serialize($event, [Encoder::OPTION_PRETTY_PRINT => true])->willReturn(
            new SerializedEvent(
                'profile.visited',
                '{"visitorId": "1"}',
            ),
        );

        $command = new OutboxInfoCommand(
            $store->reveal(),
            $serializer->reveal(),
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

        $store = $this->prophesize(OutboxStore::class);
        $store->retrieveOutboxMessages(100)->willReturn([
            Message::create($event)
                ->withAggregateName('profile')
                ->withAggregateId('1')
                ->withPlayhead(1)
                ->withRecordedOn(new DateTimeImmutable()),
        ]);

        $serializer = $this->prophesize(EventSerializer::class);
        $serializer->serialize($event, [Encoder::OPTION_PRETTY_PRINT => true])->willReturn(
            new SerializedEvent(
                'profile.visited',
                '{"visitorId": "1"}',
            ),
        );

        $command = new OutboxInfoCommand(
            $store->reveal(),
            $serializer->reveal(),
        );

        $input = new ArrayInput(['--limit' => 100]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('"visitorId": "1"', $content);
    }
}
