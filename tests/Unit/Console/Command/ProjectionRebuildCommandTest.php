<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Closure;
use Generator;
use Patchlevel\EventSourcing\Console\Command\ProjectionRebuildCommand;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\Projector\InMemoryProjectorRepository;
use Patchlevel\EventSourcing\Store\StreamableStore;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Dummy2Projection;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\DummyProjection;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
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
        /** @return Generator<Message> */
        $this->messages = static function (): Generator {
            yield new Message(
                new ProfileCreated(ProfileId::fromString('1'), Email::fromString('info@patchlevel.de')),
            );

            yield new Message(
                new ProfileCreated(ProfileId::fromString('1'), Email::fromString('info@patchlevel.de')),
            );

            yield new Message(
                new ProfileCreated(ProfileId::fromString('1'), Email::fromString('info@patchlevel.de')),
            );

            yield new Message(
                new ProfileCreated(ProfileId::fromString('1'), Email::fromString('info@patchlevel.de')),
            );

            yield new Message(
                new ProfileCreated(ProfileId::fromString('1'), Email::fromString('info@patchlevel.de')),
            );
        };
    }

    public function testSuccessful(): void
    {
        $projectionA = new DummyProjection();
        $repository = new InMemoryProjectorRepository([$projectionA]);

        $store = $this->prophesize(StreamableStore::class);
        $store->count(Argument::is(0))->willReturn(5);
        $store->stream(Argument::is(0))->willReturn(($this->messages)());

        $command = new ProjectionRebuildCommand(
            $store->reveal(),
            $repository,
        );

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);

        $content = $output->fetch();

        self::assertInstanceOf(Message::class, $projectionA->handledMessage);
        self::assertInstanceOf(ProfileCreated::class, $projectionA->handledMessage->event());

        self::assertStringContainsString('[WARNING] rebuild projections', $content);
        self::assertStringContainsString('[OK] finish', $content);
    }

    public function testSpecificProjection(): void
    {
        $store = $this->prophesize(StreamableStore::class);
        $store->count(Argument::is(0))->willReturn(5);
        $store->stream(Argument::is(0))->willReturn(($this->messages)());

        $projectionA = new DummyProjection();
        $projectionB = new Dummy2Projection();
        $repository = new InMemoryProjectorRepository([$projectionA, $projectionB]);

        $command = new ProjectionRebuildCommand(
            $store->reveal(),
            $repository,
        );

        $input = new ArrayInput(['--projection' => $projectionA::class]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);
        self::assertNotNull($projectionA->handledMessage);
        self::assertNull($projectionB->handledMessage);

        $content = $output->fetch();

        self::assertStringContainsString('[WARNING] rebuild projections', $content);
        self::assertStringContainsString('[OK] finish', $content);
    }

    public function testRecreate(): void
    {
        $projectionA = new DummyProjection();

        $store = $this->prophesize(StreamableStore::class);
        $store->count(Argument::is(0))->willReturn(5);
        $store->stream(Argument::is(0))->willReturn(($this->messages)());

        $repository = new InMemoryProjectorRepository([$projectionA]);

        $command = new ProjectionRebuildCommand(
            $store->reveal(),
            $repository,
        );

        $input = new ArrayInput(['--recreate' => true]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);

        $content = $output->fetch();

        self::assertTrue($projectionA->createCalled);
        self::assertTrue($projectionA->dropCalled);

        self::assertStringContainsString('[OK] projection schema deleted', $content);
        self::assertStringContainsString('[OK] projection schema created', $content);
        self::assertStringContainsString('[WARNING] rebuild projections', $content);
        self::assertStringContainsString('[OK] finish', $content);
    }

    public function testRecreateWithSpecificProjection(): void
    {
        $store = $this->prophesize(StreamableStore::class);
        $store->count(Argument::is(0))->willReturn(5);
        $store->stream(Argument::is(0))->willReturn(($this->messages)());

        $projectionA = new DummyProjection();
        $projectionB = new Dummy2Projection();
        $repository = new InMemoryProjectorRepository([$projectionA, $projectionB]);

        $command = new ProjectionRebuildCommand(
            $store->reveal(),
            $repository,
        );

        $input = new ArrayInput(['--recreate' => true, '--projection' => DummyProjection::class]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        self::assertSame(0, $exitCode);
        self::assertNotNull($projectionA->handledMessage);
        self::assertTrue($projectionA->createCalled);
        self::assertTrue($projectionA->dropCalled);
        self::assertNull($projectionB->handledMessage);
        self::assertFalse($projectionB->createCalled);
        self::assertFalse($projectionB->dropCalled);

        $content = $output->fetch();

        self::assertStringContainsString('[OK] projection schema deleted', $content);
        self::assertStringContainsString('[OK] projection schema created', $content);
        self::assertStringContainsString('[WARNING] rebuild projections', $content);
        self::assertStringContainsString('[OK] finish', $content);
    }
}
