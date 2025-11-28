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
use Patchlevel\EventSourcing\Store\Criteria\AggregateIdCriterion;
use Patchlevel\EventSourcing\Store\Criteria\AggregateNameCriterion;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\MissingInputException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(ShowAggregateCommand::class)]
final class ShowAggregateCommandTest extends TestCase
{
    public function testSuccessful(): void
    {
        $event = new ProfileVisited(ProfileId::fromString('1'));
        $message = Message::create($event)
            ->withHeader(new AggregateHeader('profile', '1', 1, new DateTimeImmutable()));

        $store = $this->createMock(Store::class);
        $store
            ->method('load')
            ->with(new Criteria(
                new AggregateNameCriterion('profile'),
                new AggregateIdCriterion('1'),
            ))
            ->willReturn(new ArrayStream([$message]));

        $serializer = $this->createMock(EventSerializer::class);
        $serializer
            ->method('serialize')
            ->with($event, [Encoder::OPTION_PRETTY_PRINT => true])
            ->willReturn(
                new SerializedEvent(
                    'profile.visited',
                    '{"visitorId": "1"}',
                ),
            );

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer->method('serialize')->with($message->headers())->willReturn(
            <<<'JSON'
{
	"aggregate": {
		"aggregateName": "profile",
		"aggregateId": "1",
		"playhead": 1,
		"recordedOn": "2020-01-01T20:00:00+01:00"
	}
}
JSON,
        );

        $command = new ShowAggregateCommand(
            $store,
            $serializer,
            $headersSerializer,
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
        $store = $this->createMock(Store::class);
        $serializer = $this->createMock(EventSerializer::class);

        $command = new ShowAggregateCommand(
            $store,
            $serializer,
            $this->createMock(HeadersSerializer::class),
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
        $store = $this->createMock(Store::class);
        $serializer = $this->createMock(EventSerializer::class);

        $command = new ShowAggregateCommand(
            $store,
            $serializer,
            $this->createMock(HeadersSerializer::class),
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
        $store = $this->createMock(Store::class);
        $serializer = $this->createMock(EventSerializer::class);

        $command = new ShowAggregateCommand(
            $store,
            $serializer,
            $this->createMock(HeadersSerializer::class),
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
        $store = $this->createMock(Store::class);
        $store->method('load')->with(new Criteria(
            new AggregateNameCriterion('profile'),
            new AggregateIdCriterion('test'),
        ))->willReturn(new ArrayStream());

        $serializer = $this->createMock(EventSerializer::class);

        $command = new ShowAggregateCommand(
            $store,
            $serializer,
            $this->createMock(HeadersSerializer::class),
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
                $this->createMock(Store::class),
                $this->createMock(EventSerializer::class),
                $this->createMock(HeadersSerializer::class),
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
                $this->createMock(Store::class),
                $this->createMock(EventSerializer::class),
                $this->createMock(HeadersSerializer::class),
                new AggregateRootRegistry(['test' => Profile::class]),
            ),
        );

        // Select "test" in first question
        $commandTest->setInputs(['0']);

        $this->expectException(MissingInputException::class);
        $commandTest->execute([]);
    }

    public function testInteractiveSuccessful(): void
    {
        $event = new ProfileVisited(ProfileId::fromString('1'));
        $message = Message::create($event)
            ->withHeader(new AggregateHeader('profile', '1', 1, new DateTimeImmutable()));

        $store = $this->createMock(Store::class);
        $store->method('load')->with(new Criteria(
            new AggregateNameCriterion('profile'),
            new AggregateIdCriterion('1'),
        ))->willReturn(
            new ArrayStream([$message]),
        );

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer->method('serialize')->with($event, [Encoder::OPTION_PRETTY_PRINT => true])->willReturn(
            new SerializedEvent(
                'profile.visited',
                '{"visitorId": "1"}',
            ),
        );

        $headersSerializer = $this->createMock(HeadersSerializer::class);

        $commandTest = new CommandTester(
            new ShowAggregateCommand(
                $store,
                $eventSerializer,
                $headersSerializer,
                new AggregateRootRegistry(['profile' => Profile::class]),
            ),
        );

        $commandTest->setInputs(['0', '1']);
        $commandTest->execute([]);

        $display = $commandTest->getDisplay(true);

        self::assertStringContainsString('Choose the aggregate', $display);
        self::assertStringContainsString('Enter the aggregate id', $display);
        self::assertStringContainsString('"visitorId": "1"', $display);
        self::assertStringContainsString('aggregate', $display);
        self::assertStringContainsString('profile', $display);
    }
}
