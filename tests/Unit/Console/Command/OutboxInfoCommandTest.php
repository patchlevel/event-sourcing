<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Patchlevel\EventSourcing\Console\Command\OutboxInfoCommand;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Serializer\SerializedData;
use Patchlevel\EventSourcing\Serializer\Serializer;
use Patchlevel\EventSourcing\Store\OutboxStore;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
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
            new Message(
                Profile::class,
                '1',
                1,
                $event
            ),
        ]);

        $serializer = $this->prophesize(Serializer::class);
        $serializer->serialize($event, [Serializer::OPTION_PRETTY_PRINT => true])->willReturn(new SerializedData(
            'profile.visited',
            '{"visitorId": "1"}',
        ));

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
            new Message(
                Profile::class,
                '1',
                1,
                $event
            ),
        ]);

        $serializer = $this->prophesize(Serializer::class);
        $serializer->serialize($event, [Serializer::OPTION_PRETTY_PRINT => true])->willReturn(new SerializedData(
            'profile.visited',
            '{"visitorId": "1"}',
        ));

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
