<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Patchlevel\EventSourcing\Console\Command\ShowCommand;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class ShowCommandTests extends TestCase
{
    use ProphecyTrait;

    public function testSuccessful(): void
    {
        $store = $this->prophesize(Store::class);
        $store->load(Profile::class, '1')->willReturn([ProfileVisited::raise(ProfileId::fromString('1'), ProfileId::fromString('1'))]);

        $command = new ShowCommand(
            $store->reveal(),
            [Profile::class => 'profile']
        );

        $input = new ArrayInput([
            'aggregate' => 'profile',
            'id' => '1',
        ]);

        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertEquals(0, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('"visitorId": "1"', $content);
    }

    public function testWrongAggregate(): void
    {
        $store = $this->prophesize(Store::class);

        $command = new ShowCommand(
            $store->reveal(),
            [Profile::class => 'profile']
        );

        $input = new ArrayInput([
            'aggregate' => 'test',
            'id' => '1',
        ]);

        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertEquals(1, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('[ERROR] aggregate type "test" not exists', $content);
    }

    public function testNotFound(): void
    {
        $store = $this->prophesize(Store::class);
        $store->load(Profile::class, 'test')->willReturn([]);

        $command = new ShowCommand(
            $store->reveal(),
            [Profile::class => 'profile']
        );

        $input = new ArrayInput([
            'aggregate' => 'profile',
            'id' => 'test',
        ]);

        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertEquals(1, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('[ERROR] aggregate "profile" => "test" not found', $content);
    }
}
