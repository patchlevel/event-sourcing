<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Clock;
use Patchlevel\EventSourcing\Console\EventPrinter;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Style\SymfonyStyle;

/** @covers \Patchlevel\EventSourcing\Console\EventPrinter */
final class EventPrinterTest extends TestCase
{
    use ProphecyTrait;

    public function testWrite(): void
    {
        $console = $this->prophesize(SymfonyStyle::class);
        $console->title(ProfileCreated::class)->shouldBeCalledOnce();
        $console->horizontalTable(
            [
                'aggregateClass',
                'aggregateId',
                'playhead',
                'recordedOn',
            ],
            [['Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile', '1', 1, '2022-03-11T17:22:46+01:00']]
        )->shouldBeCalledOnce();

        $console->block(
            Argument::allOf(
                Argument::containingString('{'),
                Argument::containingString('"profileId": "1"'),
                Argument::containingString('"email": "foo@bar.com"'),
                Argument::containingString('}'),
            )
        )->shouldBeCalledOnce();

        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('foo@bar.com')
        );

        $message = new Message(
            Profile::class,
            '1',
            1,
            $event
        );

        $printer = new EventPrinter();
        $printer->write($console->reveal(), $message);
    }

    public function setUp(): void
    {
        Clock::freeze(new DateTimeImmutable('2022-03-11T17:22:46+01:00'));
    }

    public function tearDown(): void
    {
        Clock::reset();
    }
}
