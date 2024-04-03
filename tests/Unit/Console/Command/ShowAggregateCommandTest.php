<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use DateTimeImmutable;
use InvalidArgumentException;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Console\Command\ShowAggregateCommand;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Serializer\HeadersSerializer;
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

/** @covers \Patchlevel\EventSourcing\Console\Command\ShowAggregateCommand */
final class ShowAggregateCommandTest extends TestCase
{
    use ProphecyTrait;

    public function testSuccessful(): void
    {
        $event = new ProfileVisited(ProfileId::fromString('1'));
        $message = Message::create($event)
            ->withHeader(new AggregateHeader('profile', '1', 1, new DateTimeImmutable()));

        $store = $this->prophesize(Store::class);
        $store->load(new Criteria(Profile::class, '1'))->willReturn(
            new ArrayStream([$message]),
        );

        $serializer = $this->prophesize(EventSerializer::class);
        $serializer->serialize($event, [Encoder::OPTION_PRETTY_PRINT => true])->willReturn(
            new SerializedEvent(
                'profile.visited',
                '{"visitorId": "1"}',
            ),
        );

        $headersSerializer = $this->prophesize(HeadersSerializer::class);
        $headersSerializer->serialize($message->headers())->willReturn(
            ['aggregate' => ['aggregateName' => 'profile', 'aggregateId' => '1', 'playhead' => 1, 'recordedOn' => '2020-01-01T20:00:00+01:00']],
        );

        $command = new ShowAggregateCommand(
            $store->reveal(),
            $serializer->reveal(),
            $headersSerializer->reveal(),
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

        $command = new ShowAggregateCommand(
            $store->reveal(),
            $serializer->reveal(),
            $this->prophesize(HeadersSerializer::class)->reveal(),
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

        $command = new ShowAggregateCommand(
            $store->reveal(),
            $serializer->reveal(),
            $this->prophesize(HeadersSerializer::class)->reveal(),
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

        $command = new ShowAggregateCommand(
            $store->reveal(),
            $serializer->reveal(),
            $this->prophesize(HeadersSerializer::class)->reveal(),
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

        $command = new ShowAggregateCommand(
            $store->reveal(),
            $serializer->reveal(),
            $this->prophesize(HeadersSerializer::class)->reveal(),
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
            new ShowAggregateCommand(
                $this->prophesize(Store::class)->reveal(),
                $this->prophesize(EventSerializer::class)->reveal(),
                $this->prophesize(HeadersSerializer::class)->reveal(),
                new AggregateRootRegistry(['test' => Profile::class]),
            ),
        );

        $this->expectException(MissingInputException::class);
        $commandTest->execute([]);
    }

    public function testInteractiveMissingIdShouldRaiseException(): void
    {
        $commandTest = new CommandTester(
            new ShowAggregateCommand(
                $this->prophesize(Store::class)->reveal(),
                $this->prophesize(EventSerializer::class)->reveal(),
                $this->prophesize(HeadersSerializer::class)->reveal(),
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
        $message = Message::create($event)
            ->withHeader(new AggregateHeader('profile', '1', 1, new DateTimeImmutable()));

        $store = $this->prophesize(Store::class);
        $store->load(new Criteria(Profile::class, '1'))->willReturn(
            new ArrayStream([$message]),
        );

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $eventSerializer->serialize($event, [Encoder::OPTION_PRETTY_PRINT => true])->willReturn(
            new SerializedEvent(
                'profile.visited',
                '{"visitorId": "1"}',
            ),
        );

        $headersSerializer = $this->prophesize(HeadersSerializer::class);

        $commandTest = new CommandTester(
            new ShowAggregateCommand(
                $store->reveal(),
                $eventSerializer->reveal(),
                $headersSerializer->reveal(),
                new AggregateRootRegistry(['profile' => Profile::class]),
            ),
        );

        $commandTest->setInputs([0, 1]);
        $commandTest->execute([]);

        $display = $commandTest->getDisplay(true);

        self::assertStringContainsString('Choose the aggregate', $display);
        self::assertStringContainsString('Enter the aggregate id', $display);
        self::assertStringContainsString('"visitorId": "1"', $display);
        self::assertStringContainsString('aggregate', $display);
        self::assertStringContainsString('profile', $display);
    }
}
