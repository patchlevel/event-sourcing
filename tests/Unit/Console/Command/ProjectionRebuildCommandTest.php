<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Patchlevel\EventSourcing\Console\Command\ProjectionRebuildCommand;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\Projector\InMemoryProjectorRepository;
use Patchlevel\EventSourcing\Store\ArrayStream;
use Patchlevel\EventSourcing\Store\Criteria;
use Patchlevel\EventSourcing\Store\CriteriaBuilder;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\Stream;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Dummy2Projection;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\DummyProjection;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
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

    private Stream $stream;
    private Criteria $criteria;

    public function setUp(): void
    {
        $this->stream = new ArrayStream([
            new Message(
                new ProfileCreated(ProfileId::fromString('1'), Email::fromString('info@patchlevel.de')),
            ),
            new Message(
                new ProfileCreated(ProfileId::fromString('1'), Email::fromString('info@patchlevel.de')),
            ),
            new Message(
                new ProfileCreated(ProfileId::fromString('1'), Email::fromString('info@patchlevel.de')),
            ),
            new Message(
                new ProfileCreated(ProfileId::fromString('1'), Email::fromString('info@patchlevel.de')),
            ),
            new Message(
                new ProfileCreated(ProfileId::fromString('1'), Email::fromString('info@patchlevel.de')),
            ),
        ]);

        $this->criteria = (new CriteriaBuilder())->fromIndex(0)->build();
    }

    public function testSuccessful(): void
    {
        $projectionA = new DummyProjection();
        $repository = new InMemoryProjectorRepository([$projectionA]);

        $store = $this->prophesize(Store::class);
        $store->count($this->criteria)->willReturn(5);
        $store->load($this->criteria)->willReturn($this->stream);

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
        $store = $this->prophesize(Store::class);
        $store->count($this->criteria)->willReturn(5);
        $store->load($this->criteria)->willReturn($this->stream);

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

        $store = $this->prophesize(Store::class);
        $store->count($this->criteria)->willReturn(5);
        $store->load($this->criteria)->willReturn($this->stream);

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
        $store = $this->prophesize(Store::class);
        $store->count($this->criteria)->willReturn(5);
        $store->load($this->criteria)->willReturn($this->stream);

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
