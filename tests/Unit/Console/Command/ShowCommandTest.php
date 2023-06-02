<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use DateTimeImmutable;
use InvalidArgumentException;
use Patchlevel\EventSourcing\Console\Command\ShowCommand;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Serializer\Encoder\Encoder;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;
use Patchlevel\EventSourcing\Store\ArrayStream;
use Patchlevel\EventSourcing\Store\Criteria;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Exception\MissingInputException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

/** @covers \Patchlevel\EventSourcing\Console\Command\ShowCommand */
final class ShowCommandTest extends TestCase
{
    use ProphecyTrait;

    public function testSuccessful(): void
    {
        $event = new ProfileVisited(ProfileId::fromString('1'));

        $store = $this->prophesize(Store::class);
        $store->load(new Criteria(Profile::class, '1'))->willReturn(
            new ArrayStream([
                Message::create($event)
                    ->withAggregateClass(Profile::class)
                    ->withAggregateId('1')
                    ->withPlayhead(1)
                    ->withRecordedOn(new DateTimeImmutable()),
            ]),
        );

        $serializer = $this->prophesize(EventSerializer::class);
        $serializer->serialize($event, [Encoder::OPTION_PRETTY_PRINT => true])->willReturn(
            new SerializedEvent(
                'profile.visited',
                '{"visitorId": "1"}',
            ),
        );

        $command = new ShowCommand(
            $store->reveal(),
            $serializer->reveal(),
            new AggregateRootRegistry(['profile' => Profile::class]),
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
        $serializer = $this->prophesize(EventSerializer::class);

        $command = new ShowCommand(
            $store->reveal(),
            $serializer->reveal(),
            new AggregateRootRegistry(['profile' => Profile::class]),
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
        $serializer = $this->prophesize(EventSerializer::class);

        $command = new ShowCommand(
            $store->reveal(),
            $serializer->reveal(),
            new AggregateRootRegistry(['profile' => Profile::class]),
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
        $serializer = $this->prophesize(EventSerializer::class);

        $command = new ShowCommand(
            $store->reveal(),
            $serializer->reveal(),
            new AggregateRootRegistry(['profile' => Profile::class]),
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
        $store->load(new Criteria(Profile::class, 'test'))->willReturn(new ArrayStream());

        $serializer = $this->prophesize(EventSerializer::class);

        $command = new ShowCommand(
            $store->reveal(),
            $serializer->reveal(),
            new AggregateRootRegistry(['profile' => Profile::class]),
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

    public function testInteractiveMissingAggregateShouldRaiseException(): void
    {
        $commandTest = new CommandTester(
            new ShowCommand(
                $this->prophesize(Store::class)->reveal(),
                $this->prophesize(EventSerializer::class)->reveal(),
                new AggregateRootRegistry(['test' => Profile::class]),
            ),
        );

        $this->expectException(MissingInputException::class);
        $commandTest->execute([]);
    }

    public function testInteractiveMissingIdShouldRaiseException(): void
    {
        $commandTest = new CommandTester(
            new ShowCommand(
                $this->prophesize(Store::class)->reveal(),
                $this->prophesize(EventSerializer::class)->reveal(),
                new AggregateRootRegistry(['test' => Profile::class]),
            ),
        );

        // Select "test" in first question
        $commandTest->setInputs([0]);

        $this->expectException(MissingInputException::class);
        $commandTest->execute([]);
    }

    public function testInteractiveSuccessful(): void
    {
        $event = new ProfileVisited(ProfileId::fromString('1'));

        $store = $this->prophesize(Store::class);
        $store->load(new Criteria(Profile::class, '1'))->willReturn(
            new ArrayStream([
                Message::create($event)
                    ->withAggregateClass(Profile::class)
                    ->withAggregateId('1')
                    ->withPlayhead(1)
                    ->withRecordedOn(new DateTimeImmutable()),
            ]),
        );

        $serializer = $this->prophesize(EventSerializer::class);
        $serializer->serialize($event, [Encoder::OPTION_PRETTY_PRINT => true])->willReturn(
            new SerializedEvent(
                'profile.visited',
                '{"visitorId": "1"}',
            ),
        );

        $commandTest = new CommandTester(
            new ShowCommand(
                $store->reveal(),
                $serializer->reveal(),
                new AggregateRootRegistry(['profile' => Profile::class]),
            ),
        );

        $commandTest->setInputs([0, 1]);
        $commandTest->execute([]);

        $display = $commandTest->getDisplay(true);

        self::assertStringContainsString('Choose the aggregate', $display);
        self::assertStringContainsString('Enter the aggregate id', $display);
        self::assertStringContainsString('"visitorId": "1"', $display);
    }
}
