<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Closure;
use Generator;
use Patchlevel\EventSourcing\Console\Command\ProjectionRebuildCommand;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\DefaultProjectionHandler;
use Patchlevel\EventSourcing\Projection\ProjectionHandler;
use Patchlevel\EventSourcing\Store\PipelineStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Dummy2Projection;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\DummyProjection;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @covers \Patchlevel\EventSourcing\Console\Command\ProjectionRebuildCommand
 * @covers \Patchlevel\EventSourcing\Console\Command\ProjectionCommand
 */
final class ProjectionRebuildCommandTest extends TestCase
{
    use ProphecyTrait;

    private Closure $messages;

    public function setUp(): void
    {
        /**
         * @return Generator<Message>
         */
        $this->messages = static function (): Generator {
            yield new Message(
                Profile::class,
                '1',
                1,
                new ProfileCreated(ProfileId::fromString('1'), Email::fromString('info@patchlevel.de'))
            );

            yield new Message(
                Profile::class,
                '1',
                2,
                new ProfileCreated(ProfileId::fromString('1'), Email::fromString('info@patchlevel.de'))
            );

            yield new Message(
                Profile::class,
                '1',
                3,
                new ProfileCreated(ProfileId::fromString('1'), Email::fromString('info@patchlevel.de'))
            );

            yield new Message(
                Profile::class,
                '1',
                4,
                new ProfileCreated(ProfileId::fromString('1'), Email::fromString('info@patchlevel.de'))
            );

            yield new Message(
                Profile::class,
                '1',
                5,
                new ProfileCreated(ProfileId::fromString('1'), Email::fromString('info@patchlevel.de'))
            );
        };
    }

    public function testSuccessful(): void
    {
        $store = $this->prophesize(PipelineStore::class);
        $store->count(Argument::is(0))->willReturn(5);
        $store->stream(Argument::is(0))->willReturn(($this->messages)());

        $repository = $this->prophesize(ProjectionHandler::class);
        $repository->handle(Argument::type(Message::class))->shouldBeCalledTimes(5);

        $command = new ProjectionRebuildCommand(
            $store->reveal(),
            $repository->reveal()
        );

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('[WARNING] rebuild projections', $content);
        self::assertStringContainsString('[OK] finish', $content);
    }

    public function testSpecificProjection(): void
    {
        $store = $this->prophesize(PipelineStore::class);
        $store->count(Argument::is(0))->willReturn(5);
        $store->stream(Argument::is(0))->willReturn(($this->messages)());

        $projectionA = new DummyProjection();
        $projectionB = new Dummy2Projection();
        $handler = new DefaultProjectionHandler([$projectionA, $projectionB]);

        $command = new ProjectionRebuildCommand(
            $store->reveal(),
            $handler
        );

        $input = new ArrayInput(['--projection' => $projectionA::class]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);
        self::assertNotNull($projectionA->handledEvent);
        self::assertNull($projectionB->handledEvent);

        $content = $output->fetch();

        self::assertStringContainsString('[WARNING] rebuild projections', $content);
        self::assertStringContainsString('[OK] finish', $content);
    }

    public function testRecreate(): void
    {
        $store = $this->prophesize(PipelineStore::class);
        $store->count(Argument::is(0))->willReturn(5);
        $store->stream(Argument::is(0))->willReturn(($this->messages)());

        $repository = $this->prophesize(ProjectionHandler::class);
        $repository->drop(null)->shouldBeCalled();
        $repository->create(null)->shouldBeCalled();
        $repository->handle(Argument::type(Message::class))->shouldBeCalledTimes(5);

        $command = new ProjectionRebuildCommand(
            $store->reveal(),
            $repository->reveal()
        );

        $input = new ArrayInput(['--recreate' => true]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('[OK] projection schema deleted', $content);
        self::assertStringContainsString('[OK] projection schema created', $content);
        self::assertStringContainsString('[WARNING] rebuild projections', $content);
        self::assertStringContainsString('[OK] finish', $content);
    }

    public function testRecreateWithSpecificProjection(): void
    {
        $store = $this->prophesize(PipelineStore::class);
        $store->count(Argument::is(0))->willReturn(5);
        $store->stream(Argument::is(0))->willReturn(($this->messages)());

        $projectionA = new DummyProjection();
        $projectionB = new Dummy2Projection();
        $handler = new DefaultProjectionHandler([$projectionA, $projectionB]);

        $command = new ProjectionRebuildCommand(
            $store->reveal(),
            $handler
        );

        $input = new ArrayInput(['--recreate' => true, '--projection' => DummyProjection::class]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);
        self::assertNotNull($projectionA->handledEvent);
        self::assertTrue($projectionA->createCalled);
        self::assertTrue($projectionA->dropCalled);
        self::assertNull($projectionB->handledEvent);
        self::assertFalse($projectionB->createCalled);
        self::assertFalse($projectionB->dropCalled);

        $content = $output->fetch();

        self::assertStringContainsString('[OK] projection schema deleted', $content);
        self::assertStringContainsString('[OK] projection schema created', $content);
        self::assertStringContainsString('[WARNING] rebuild projections', $content);
        self::assertStringContainsString('[OK] finish', $content);
    }

    public function testStoreNotSupported(): void
    {
        $store = $this->prophesize(Store::class);
        $repository = $this->prophesize(ProjectionHandler::class);

        $command = new ProjectionRebuildCommand(
            $store->reveal(),
            $repository->reveal()
        );

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(1, $exitCode);

        $content = $output->fetch();

        self::assertStringContainsString('[ERROR] store is not supported', $content);
    }
}
