<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Serializer\HeadersSerializer;
use Patchlevel\EventSourcing\Serializer\Encoder\Encoder;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Header\FooHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

#[CoversClass(OutputStyle::class)]
final class OutputStyleTest extends TestCase
{
    public function testMessage(): void
    {
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('foo@bar.com'),
        );

        $message = Message::create($event)
            ->withHeader(new StreamNameHeader('profile-1'))
            ->withHeader(new PlayheadHeader(1))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer->method('serialize')->with($event, [Encoder::OPTION_PRETTY_PRINT => true])->willReturn(new SerializedEvent(
            'profile.created',
            '{"id":"1","email":"foo@bar.com"}',
        ));

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer->expects($this->never())->method('serialize');

        $console = new OutputStyle($input, $output);

        $console->message(
            $eventSerializer,
            $headersSerializer,
            $message,
        );

        $content = $output->fetch();

        self::assertStringContainsString('profile.created', $content);
        self::assertStringContainsString('profile', $content);
        self::assertStringContainsString('{"id":"1","email":"foo@bar.com"}', $content);
        self::assertStringContainsString('stream', $content);
        self::assertStringContainsString('profile', $content);
    }

    public function testMessageWithCustomHeaders(): void
    {
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('foo@bar.com'),
        );

        $fooHeader = new FooHeader('foo');

        $message = Message::create($event)
            ->withHeader(new StreamNameHeader('profile-1'))
            ->withHeader(new PlayheadHeader(1))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable()))
            ->withHeader($fooHeader);

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer->method('serialize')->with($event, [Encoder::OPTION_PRETTY_PRINT => true])->willReturn(new SerializedEvent(
            'profile.created',
            '{"id":"1","email":"foo@bar.com"}',
        ));

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer->expects($this->atLeastOnce())->method('serialize')->with([$fooHeader])->willReturn(
            '{"aggregate":{"aggregateName":"profile","aggregateId":"1","playhead":1,"recordedOn":"2020-01-01T20:00:00+01:00"},"archived":[]}',
        );

        $console = new OutputStyle($input, $output);

        $console->message(
            $eventSerializer,
            $headersSerializer,
            $message,
        );

        $content = $output->fetch();

        self::assertStringContainsString('profile.created', $content);
        self::assertStringContainsString('profile', $content);
        self::assertStringContainsString('{"id":"1","email":"foo@bar.com"}', $content);
        self::assertStringContainsString('stream', $content);
        self::assertStringContainsString('profile', $content);
    }

    public function testMessageWithSerializationError(): void
    {
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('foo@bar.com'),
        );

        $message = Message::create($event)
            ->withHeader(new StreamNameHeader('profile-1'));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with($event, [Encoder::OPTION_PRETTY_PRINT => true])
            ->willThrowException(new RuntimeException('boom'));

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer->expects($this->never())->method('serialize');

        $console = new OutputStyle($input, $output);

        $console->message(
            $eventSerializer,
            $headersSerializer,
            $message,
        );

        $content = $output->fetch();

        self::assertStringContainsString('Error while serializing event', $content);
        self::assertStringContainsString('boom', $content);
        self::assertStringNotContainsString('#0', $content);
    }

    public function testMessageWithSerializationErrorVeryVerbose(): void
    {
        $input = new ArrayInput([]);
        $output = new BufferedOutput(OutputInterface::VERBOSITY_VERY_VERBOSE);

        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('foo@bar.com'),
        );

        $message = Message::create($event)
            ->withHeader(new StreamNameHeader('profile-1'));

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with($event, [Encoder::OPTION_PRETTY_PRINT => true])
            ->willThrowException(new RuntimeException('boom'));

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer->expects($this->never())->method('serialize');

        $console = new OutputStyle($input, $output);

        $console->message(
            $eventSerializer,
            $headersSerializer,
            $message,
        );

        $content = $output->fetch();

        self::assertStringContainsString('Error while serializing event', $content);
        self::assertStringContainsString('1) boom', $content);
        self::assertStringContainsString('#0', $content);
    }

    public function testThrowable(): void
    {
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $error = new RuntimeException(
            'outer error',
            0,
            new RuntimeException('inner error'),
        );

        $console = new OutputStyle($input, $output);
        $console->throwable($error);

        $content = $output->fetch();

        self::assertStringContainsString('1) outer error', $content);
        self::assertStringContainsString('2) inner error', $content);
    }
}
