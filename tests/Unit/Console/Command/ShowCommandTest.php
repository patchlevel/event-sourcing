<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Patchlevel\EventSourcing\Console\Command\ShowCommand;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Serializer\HeadersSerializer;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\InMemoryStore;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(ShowCommand::class)]
final class ShowCommandTest extends TestCase
{
    public function testShowNoMessages(): void
    {
        $store = new InMemoryStore();

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer->expects($this->never())->method('serialize');

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer->expects($this->never())->method('serialize');

        $commandTester = new CommandTester(new ShowCommand($store, $eventSerializer, $headersSerializer));
        $commandTester->execute([]);

        $display = $commandTester->getDisplay();

        self::assertSame(0, $commandTester->getStatusCode());
        self::assertStringContainsString('No more messages (0/0)', $display);
    }

    public function testShowOneMessage(): void
    {
        $message = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('foo@bar.com')))
            ->withHeader(new StreamNameHeader('profile-1'))
            ->withHeader(new PlayheadHeader(1));

        $store = new InMemoryStore([$message]);

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer
            ->expects($this->once())
            ->method('serialize')
            ->willReturn(new SerializedEvent('profile.created', '{"id":"1"}'));

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer
            ->expects($this->once())
            ->method('serialize')
            ->willReturn('[]');

        $commandTester = new CommandTester(new ShowCommand($store, $eventSerializer, $headersSerializer));
        $commandTester->execute([]);

        $display = $commandTester->getDisplay();

        self::assertSame(0, $commandTester->getStatusCode());
        self::assertStringContainsString('profile.created', $display);
        self::assertStringContainsString('profile-1', $display);
        self::assertStringContainsString('No more messages (1/1)', $display);
    }

    public function testShowWithStream(): void
    {
        $message1 = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('foo@bar.com')))
            ->withHeader(new StreamNameHeader('profile-1'))
            ->withHeader(new PlayheadHeader(1));
        $message2 = Message::create(new ProfileCreated(ProfileId::fromString('2'), Email::fromString('foo@bar.com')))
            ->withHeader(new StreamNameHeader('other-1'))
            ->withHeader(new PlayheadHeader(1));

        $store = new InMemoryStore([$message1, $message2]);

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer
            ->expects($this->once())
            ->method('serialize')
            ->willReturn(new SerializedEvent('profile.created', '{"id":"1"}'));

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer
            ->expects($this->once())
            ->method('serialize')
            ->willReturn('[]');

        $commandTester = new CommandTester(new ShowCommand($store, $eventSerializer, $headersSerializer));
        $commandTester->execute(['--stream' => 'profile-1']);

        $display = $commandTester->getDisplay();

        self::assertSame(0, $commandTester->getStatusCode());
        self::assertStringContainsString('profile-1', $display);
        self::assertStringNotContainsString('other-1', $display);
        self::assertStringContainsString('No more messages (1/1)', $display);
    }

    public function testShowWithLimitZero(): void
    {
        $message1 = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('foo@bar.com')))
            ->withHeader(new StreamNameHeader('profile-1'))
            ->withHeader(new PlayheadHeader(1));
        $message2 = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('foo@bar.com')))
            ->withHeader(new StreamNameHeader('profile-1'))
            ->withHeader(new PlayheadHeader(2));

        $store = new InMemoryStore([$message1, $message2]);

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer
            ->expects($this->exactly(2))
            ->method('serialize')
            ->willReturn(new SerializedEvent('profile.created', '{"id":"1"}'));

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer
            ->expects($this->exactly(2))
            ->method('serialize')
            ->willReturn('[]');

        $commandTester = new CommandTester(new ShowCommand($store, $eventSerializer, $headersSerializer));
        $commandTester->execute(['--limit' => 0]);

        $display = $commandTester->getDisplay();

        self::assertSame(0, $commandTester->getStatusCode());
        self::assertStringContainsString('profile.created', $display);
        self::assertStringNotContainsString('No more messages', $display);
    }

    public function testShowNextBatchConfirmed(): void
    {
        $message1 = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('foo@bar.com')))
            ->withHeader(new StreamNameHeader('profile-1'))
            ->withHeader(new PlayheadHeader(1));
        $message2 = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('foo@bar.com')))
            ->withHeader(new StreamNameHeader('profile-1'))
            ->withHeader(new PlayheadHeader(2));

        $store = new InMemoryStore([$message1, $message2]);

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer
            ->expects($this->exactly(2))
            ->method('serialize')
            ->willReturn(new SerializedEvent('profile.created', '{"id":"1"}'));

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer
            ->expects($this->exactly(2))
            ->method('serialize')
            ->willReturn('[]');

        $commandTester = new CommandTester(new ShowCommand($store, $eventSerializer, $headersSerializer));
        $commandTester->setInputs(['yes']);
        $commandTester->execute(['--limit' => 1]);

        $display = $commandTester->getDisplay();

        self::assertSame(0, $commandTester->getStatusCode());
        self::assertStringContainsString('Show next 1 messages? (1/2)', $display);
        self::assertStringContainsString('No more messages (2/2)', $display);
    }

    public function testShowNextBatchAborted(): void
    {
        $message1 = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('foo@bar.com')))
            ->withHeader(new StreamNameHeader('profile-1'))
            ->withHeader(new PlayheadHeader(1));
        $message2 = Message::create(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('foo@bar.com')))
            ->withHeader(new StreamNameHeader('profile-1'))
            ->withHeader(new PlayheadHeader(2));

        $store = new InMemoryStore([$message1, $message2]);

        $eventSerializer = $this->createMock(EventSerializer::class);
        $eventSerializer
            ->expects($this->once())
            ->method('serialize')
            ->willReturn(new SerializedEvent('profile.created', '{"id":"1"}'));

        $headersSerializer = $this->createMock(HeadersSerializer::class);
        $headersSerializer
            ->expects($this->once())
            ->method('serialize')
            ->willReturn('[]');

        $commandTester = new CommandTester(new ShowCommand($store, $eventSerializer, $headersSerializer));
        $commandTester->setInputs(['no']);
        $commandTester->execute(['--limit' => 1]);

        $display = $commandTester->getDisplay();

        self::assertSame(0, $commandTester->getStatusCode());
        self::assertStringContainsString('Show next 1 messages? (1/2)', $display);
        self::assertStringNotContainsString('No more messages', $display);
    }
}
