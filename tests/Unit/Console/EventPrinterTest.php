<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console;

use Patchlevel\EventSourcing\Console\EventPrinter;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
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
        $console->block(<<<'json'
{
    "profileId": "1",
    "email": "foo@bar.com"
}
json
        )->shouldBeCalledOnce();

        $profileCreated = ProfileCreated::raise(
            ProfileId::fromString('1'),
            Email::fromString('foo@bar.com')
        );

        $printer = new EventPrinter();
        $printer->write($console->reveal(), $profileCreated);
    }
}
