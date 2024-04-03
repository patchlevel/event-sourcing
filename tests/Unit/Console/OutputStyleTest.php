<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Serializer\HeadersSerializer;
use Patchlevel\EventSourcing\Serializer\Encoder\Encoder;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Header\FooHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/** @covers \Patchlevel\EventSourcing\Console\OutputStyle */
final class OutputStyleTest extends TestCase
{
    use ProphecyTrait;

    public function testMessage(): void
    {
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('foo@bar.com'),
        );

        $message = Message::create($event)
            ->withHeader(new AggregateHeader('profile', '1', 1, new DateTimeImmutable()));

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $eventSerializer->serialize($event, [Encoder::OPTION_PRETTY_PRINT => true])->willReturn(new SerializedEvent(
            'profile.created',
            '{"id":"1","email":"foo@bar.com"}',
        ));

        $headersSerializer = $this->prophesize(HeadersSerializer::class);
        $headersSerializer->serialize(Argument::any())->shouldNotBeCalled();

        $console = new OutputStyle($input, $output);

        $console->message(
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
            $message,
        );

        $content = $output->fetch();

        self::assertStringContainsString('profile.created', $content);
        self::assertStringContainsString('profile', $content);
        self::assertStringContainsString('{"id":"1","email":"foo@bar.com"}', $content);
        self::assertStringContainsString('aggregate', $content);
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
            ->withHeader(new AggregateHeader('profile', '1', 1, new DateTimeImmutable()))
            ->withHeader($fooHeader);

        $eventSerializer = $this->prophesize(EventSerializer::class);
        $eventSerializer->serialize($event, [Encoder::OPTION_PRETTY_PRINT => true])->willReturn(new SerializedEvent(
            'profile.created',
            '{"id":"1","email":"foo@bar.com"}',
        ));

        $headersSerializer = $this->prophesize(HeadersSerializer::class);
        $headersSerializer->serialize([$fooHeader])->willReturn(
            '{"aggregate":{"aggregateName":"profile","aggregateId":"1","playhead":1,"recordedOn":"2020-01-01T20:00:00+01:00"},"archived":[]}',
        )->shouldBeCalled();

        $console = new OutputStyle($input, $output);

        $console->message(
            $eventSerializer->reveal(),
            $headersSerializer->reveal(),
            $message,
        );

        $content = $output->fetch();

        self::assertStringContainsString('profile.created', $content);
        self::assertStringContainsString('profile', $content);
        self::assertStringContainsString('{"id":"1","email":"foo@bar.com"}', $content);
        self::assertStringContainsString('aggregate', $content);
        self::assertStringContainsString('profile', $content);
    }
}
