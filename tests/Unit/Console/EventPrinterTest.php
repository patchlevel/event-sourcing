<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console;

use Patchlevel\EventSourcing\Console\EventPrinter;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Style\SymfonyStyle;

final class EventPrinterTest extends TestCase
{
    use ProphecyTrait;

    public function testWrite(): void
    {
        $console = $this->prophesize(SymfonyStyle::class);
        $console->title(ProfileCreated::class)->shouldBeCalledOnce();
        $console->horizontalTable(
            [
                'aggregateId',
                'playhead',
                'recordedOn',
            ],
            [['1', null, 'null']]
        )->shouldBeCalledOnce();

        $console->block(
            Argument::allOf(
                Argument::containingString('{'),
                Argument::containingString('"profileId": "1"'),
                Argument::containingString('"email": "foo@bar.com"'),
                Argument::containingString('}'),
            )
        )->shouldBeCalledOnce();

        $profileCreated = ProfileCreated::raise(
            ProfileId::fromString('1'),
            Email::fromString('foo@bar.com')
        );

        $printer = new EventPrinter();
        $printer->write($console->reveal(), $profileCreated);
    }
}
