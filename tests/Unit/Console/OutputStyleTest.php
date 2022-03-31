<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Clock;
use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Serializer\Serializer;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
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

        $serializer = $this->prophesize(Serializer::class);

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

        $console = new OutputStyle($input, $output);

        $content = '';

        $this->assertEquals($content, $console->message($serializer->reveal(), $message));
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
