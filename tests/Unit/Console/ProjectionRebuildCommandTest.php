<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console;

use Patchlevel\EventSourcing\Console\ProjectionRebuildCommand;
use Patchlevel\EventSourcing\Pipeline\EventBucket;
use Patchlevel\EventSourcing\Projection\ProjectionRepository;
use Patchlevel\EventSourcing\Store\PipelineStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class ProjectionRebuildCommandTest extends TestCase
{
    use ProphecyTrait;
    use MatchesSnapshots;

    public function testSuccessful(): void
    {
        $events = static function () {
            yield new EventBucket(
                Profile::class,
                ProfileVisited::raise(ProfileId::fromString('1'), ProfileId::fromString('1'))
            );

            yield new EventBucket(
                Profile::class,
                ProfileVisited::raise(ProfileId::fromString('1'), ProfileId::fromString('1'))
            );

            yield new EventBucket(
                Profile::class,
                ProfileVisited::raise(ProfileId::fromString('1'), ProfileId::fromString('1'))
            );

            yield new EventBucket(
                Profile::class,
                ProfileVisited::raise(ProfileId::fromString('1'), ProfileId::fromString('1'))
            );

            yield new EventBucket(
                Profile::class,
                ProfileVisited::raise(ProfileId::fromString('1'), ProfileId::fromString('1'))
            );
        };

        $store = $this->prophesize(PipelineStore::class);
        $store->count()->willReturn(5);
        $store->all()->willReturn($events());

        $repository = $this->prophesize(ProjectionRepository::class);
        $repository->handle(Argument::type(ProfileVisited::class))->shouldBeCalledTimes(5);

        $command = new ProjectionRebuildCommand(
            $store->reveal(),
            $repository->reveal()
        );

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertEquals(0, $exitCode);
        self::assertMatchesSnapshot($output->fetch());
    }

    public function testRecreate(): void
    {
        $events = static function () {
            yield new EventBucket(
                Profile::class,
                ProfileVisited::raise(ProfileId::fromString('1'), ProfileId::fromString('1'))
            );

            yield new EventBucket(
                Profile::class,
                ProfileVisited::raise(ProfileId::fromString('1'), ProfileId::fromString('1'))
            );

            yield new EventBucket(
                Profile::class,
                ProfileVisited::raise(ProfileId::fromString('1'), ProfileId::fromString('1'))
            );

            yield new EventBucket(
                Profile::class,
                ProfileVisited::raise(ProfileId::fromString('1'), ProfileId::fromString('1'))
            );

            yield new EventBucket(
                Profile::class,
                ProfileVisited::raise(ProfileId::fromString('1'), ProfileId::fromString('1'))
            );
        };

        $store = $this->prophesize(PipelineStore::class);
        $store->count()->willReturn(5);
        $store->all()->willReturn($events());

        $repository = $this->prophesize(ProjectionRepository::class);
        $repository->drop()->shouldBeCalled();
        $repository->create()->shouldBeCalled();
        $repository->handle(Argument::type(ProfileVisited::class))->shouldBeCalledTimes(5);

        $command = new ProjectionRebuildCommand(
            $store->reveal(),
            $repository->reveal()
        );

        $input = new ArrayInput(['--recreate' => true]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertEquals(0, $exitCode);
        self::assertMatchesSnapshot($output->fetch());
    }

    public function testStoreNotSupported(): void
    {
        $store = $this->prophesize(Store::class);
        $repository = $this->prophesize(ProjectionRepository::class);

        $command = new ProjectionRebuildCommand(
            $store->reveal(),
            $repository->reveal()
        );

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertEquals(1, $exitCode);
        self::assertMatchesSnapshot($output->fetch());
    }
}
