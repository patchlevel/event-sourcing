<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use InvalidArgumentException;
use Patchlevel\EventSourcing\Console\Command\ShowCommand;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Serializer\SerializedData;
use Patchlevel\EventSourcing\Serializer\Serializer;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/** @covers \Patchlevel\EventSourcing\Console\Command\ShowCommand */
final class ShowCommandTest extends TestCase
{
    use ProphecyTrait;

    public function testSuccessful(): void
    {
        $event = new ProfileVisited(ProfileId::fromString('1'));

        $store = $this->prophesize(Store::class);
        $store->load(Profile::class, '1')->willReturn([
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

        $command = new ShowCommand(
            $store->reveal(),
            $serializer->reveal(),
            [Profile::class => 'profile']
        );

        $input = new ArrayInput([
            'aggregate' => 'profile',
            'id' => '1',
        ]);

        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('"visitorId": "1"', $content);
    }

    public function testAggregateNotAString(): void
    {
        $store = $this->prophesize(Store::class);
        $serializer = $this->prophesize(Serializer::class);

        $command = new ShowCommand(
            $store->reveal(),
            $serializer->reveal(),
            [Profile::class => 'profile']
        );

        $input = new ArrayInput([
            'aggregate' => [],
            'id' => '1',
        ]);

        $output = new BufferedOutput();

        $this->expectException(InvalidArgumentException::class);
        $command->run($input, $output);
    }

    public function testIdNotAString(): void
    {
        $store = $this->prophesize(Store::class);
        $serializer = $this->prophesize(Serializer::class);

        $command = new ShowCommand(
            $store->reveal(),
            $serializer->reveal(),
            [Profile::class => 'profile']
        );

        $input = new ArrayInput([
            'aggregate' => 'profile',
            'id' => [],
        ]);

        $output = new BufferedOutput();

        $this->expectException(InvalidArgumentException::class);
        $command->run($input, $output);
    }

    public function testWrongAggregate(): void
    {
        $store = $this->prophesize(Store::class);
        $serializer = $this->prophesize(Serializer::class);

        $command = new ShowCommand(
            $store->reveal(),
            $serializer->reveal(),
            [Profile::class => 'profile']
        );

        $input = new ArrayInput([
            'aggregate' => 'test',
            'id' => '1',
        ]);

        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(1, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('[ERROR] aggregate type "test" not exists', $content);
    }

    public function testNotFound(): void
    {
        $store = $this->prophesize(Store::class);
        $store->load(Profile::class, 'test')->willReturn([]);

        $serializer = $this->prophesize(Serializer::class);

        $command = new ShowCommand(
            $store->reveal(),
            $serializer->reveal(),
            [Profile::class => 'profile']
        );

        $input = new ArrayInput([
            'aggregate' => 'profile',
            'id' => 'test',
        ]);

        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(1, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('[ERROR] aggregate "profile" => "test" not found', $content);
    }
}
