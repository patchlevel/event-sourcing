<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\CommandBus;

use Patchlevel\EventSourcing\CommandBus\AggregateHandlerProvider;
use Patchlevel\EventSourcing\CommandBus\Handler\HandlerFactory;
use Patchlevel\EventSourcing\CommandBus\InvalidHandleMethod;
use Patchlevel\EventSourcing\CommandBus\MissingHandledBy;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ChangeProfileName;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\CreateProfile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\NoParameterCommand;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\NoTypeCommand;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithHandlers;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use stdClass;

/** @covers \Patchlevel\EventSourcing\CommandBus\AggregateHandlerProvider */
final class AggregateHandlerProviderTest extends TestCase
{
    use ProphecyTrait;

    public function testMissingHandledBy(): void
    {
        $handlerFactory = $this->prophesize(HandlerFactory::class);
        $provider = new AggregateHandlerProvider($handlerFactory->reveal());

        $this->expectException(MissingHandledBy::class);

        $provider->handlerForCommand(stdClass::class);
    }

    public function testNoParameters(): void
    {
        $handlerFactory = $this->prophesize(HandlerFactory::class);
        $provider = new AggregateHandlerProvider($handlerFactory->reveal());

        $this->expectException(InvalidHandleMethod::class);

        $provider->handlerForCommand(NoParameterCommand::class);
    }

    public function testNoType(): void
    {
        $handlerFactory = $this->prophesize(HandlerFactory::class);
        $provider = new AggregateHandlerProvider($handlerFactory->reveal());

        $this->expectException(InvalidHandleMethod::class);

        $provider->handlerForCommand(NoTypeCommand::class);
    }

    public function testGetCreateHandler(): void
    {
        $handler = static fn (CreateProfile $command): mixed => null;

        $handlerFactory = $this->prophesize(HandlerFactory::class);
        $handlerFactory
            ->createHandler(ProfileWithHandlers::class, 'create')
            ->shouldBeCalled()
            ->willReturn($handler);

        $provider = new AggregateHandlerProvider($handlerFactory->reveal());

        $result = $provider->handlerForCommand(CreateProfile::class);

        self::assertSame($handler, $result->callable());
    }

    public function testGetUpdateHandler(): void
    {
        $handler = static fn (ChangeProfileName $command): mixed => null;

        $handlerFactory = $this->prophesize(HandlerFactory::class);
        $handlerFactory
            ->updateHandler(ProfileWithHandlers::class, 'changeName')
            ->shouldBeCalled()
            ->willReturn($handler);

        $provider = new AggregateHandlerProvider($handlerFactory->reveal());

        $result = $provider->handlerForCommand(ChangeProfileName::class);

        self::assertSame($handler, $result->callable());
    }
}
