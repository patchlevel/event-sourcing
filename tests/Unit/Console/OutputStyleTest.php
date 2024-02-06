<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Serializer\Encoder\Encoder;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/** @covers \Patchlevel\EventSourcing\Console\OutputStyle */
final class OutputStyleTest extends TestCase
{
    use ProphecyTrait;

    public function testWrite(): void
    {
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('foo@bar.com'),
        );

        $serializer = $this->prophesize(EventSerializer::class);
        $serializer->serialize($event, [Encoder::OPTION_PRETTY_PRINT => true])->willReturn(new SerializedEvent(
            'profile.created',
            '{"id":"1","email":"foo@bar.com"}',
        ));

        $message = Message::create($event)
            ->withAggregateName('profile')
            ->withAggregateId('1')
            ->withPlayhead(1)
            ->withRecordedOn(new DateTimeImmutable());

        $console = new OutputStyle($input, $output);

        $console->message($serializer->reveal(), $message);

        $content = $output->fetch();

        self::assertStringContainsString('profile.created', $content);
        self::assertStringContainsString('Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated', $content);
        self::assertStringContainsString('Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile', $content);
        self::assertStringContainsString('{"id":"1","email":"foo@bar.com"}', $content);
    }
}
