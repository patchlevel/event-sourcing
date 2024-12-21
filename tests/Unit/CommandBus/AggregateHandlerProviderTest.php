<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\CommandBus;

use Patchlevel\EventSourcing\CommandBus\AggregateHandlerProvider;
use Patchlevel\EventSourcing\CommandBus\Handler\HandlerFactory;
use Patchlevel\EventSourcing\CommandBus\InvalidHandleMethod;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ActivateProfile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ChangeProfileName;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\CreateProfile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithHandler;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithNoParameterHandler;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithNoTypeHandler;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\CommandBus\AggregateHandlerProvider */
final class AggregateHandlerProviderTest extends TestCase
{
    use ProphecyTrait;

    public function testNoParameters(): void
    {
        $handlerFactory = $this->prophesize(HandlerFactory::class);
        $provider = new AggregateHandlerProvider(
            new AggregateRootRegistry(['profile' => ProfileWithNoParameterHandler::class]),
            $handlerFactory->reveal(),
        );

        $this->expectException(InvalidHandleMethod::class);

        $provider->handlerForCommand(ChangeProfileName::class);
    }

    public function testNoType(): void
    {
        $handlerFactory = $this->prophesize(HandlerFactory::class);
        $provider = new AggregateHandlerProvider(
            new AggregateRootRegistry(['profile' => ProfileWithNoTypeHandler::class]),
            $handlerFactory->reveal(),
        );

        $this->expectException(InvalidHandleMethod::class);

        $provider->handlerForCommand(ChangeProfileName::class);
    }

    public function testEmpty(): void
    {
        $handlerFactory = $this->prophesize(HandlerFactory::class);

        $provider = new AggregateHandlerProvider(
            new AggregateRootRegistry([]),
            $handlerFactory->reveal(),
        );

        $result = $provider->handlerForCommand(CreateProfile::class);

        self::assertCount(0, $result);
    }

    public function testGetCreateHandler(): void
    {
        $handler = static fn (CreateProfile $command): mixed => null;

        $handlerFactory = $this->prophesize(HandlerFactory::class);
        $handlerFactory
            ->createHandler(ProfileWithHandler::class, 'create')
            ->shouldBeCalled()
            ->willReturn($handler);

        $handlerFactory
            ->updateHandler(ProfileWithHandler::class, 'changeName')
            ->shouldBeCalled()
            ->willReturn($handler);

        $handlerFactory
            ->updateHandler(ProfileWithHandler::class, 'activate')
            ->shouldBeCalled()
            ->willReturn($handler);

        $provider = new AggregateHandlerProvider(
            new AggregateRootRegistry(['profile' => ProfileWithHandler::class]),
            $handlerFactory->reveal(),
        );

        $result = $provider->handlerForCommand(CreateProfile::class);

        self::assertCount(1, $result);
        self::assertSame($handler, $result[0]->callable());
    }

    public function testGetUpdateHandler(): void
    {
        $handler = static fn (ChangeProfileName $command): mixed => null;

        $handlerFactory = $this->prophesize(HandlerFactory::class);

        $handlerFactory
            ->createHandler(ProfileWithHandler::class, 'create')
            ->shouldBeCalled()
            ->willReturn($handler);

        $handlerFactory
            ->updateHandler(ProfileWithHandler::class, 'changeName')
            ->shouldBeCalled()
            ->willReturn($handler);

        $handlerFactory
            ->updateHandler(ProfileWithHandler::class, 'activate')
            ->shouldBeCalled()
            ->willReturn($handler);

        $provider = new AggregateHandlerProvider(
            new AggregateRootRegistry(['profile' => ProfileWithHandler::class]),
            $handlerFactory->reveal(),
        );

        $result = $provider->handlerForCommand(ChangeProfileName::class);

        self::assertCount(1, $result);
        self::assertSame($handler, $result[0]->callable());
    }

    public function testUpdateHandlerWithoutParameter(): void
    {
        $handler = static fn (ChangeProfileName $command): mixed => null;

        $handlerFactory = $this->prophesize(HandlerFactory::class);

        $handlerFactory
            ->createHandler(ProfileWithHandler::class, 'create')
            ->shouldBeCalled()
            ->willReturn($handler);

        $handlerFactory
            ->updateHandler(ProfileWithHandler::class, 'changeName')
            ->shouldBeCalled()
            ->willReturn($handler);

        $handlerFactory
            ->updateHandler(ProfileWithHandler::class, 'activate')
            ->shouldBeCalled()
            ->willReturn($handler);

        $provider = new AggregateHandlerProvider(
            new AggregateRootRegistry(['profile' => ProfileWithHandler::class]),
            $handlerFactory->reveal(),
        );

        $result = $provider->handlerForCommand(ActivateProfile::class);

        self::assertCount(1, $result);
        self::assertSame($handler, $result[0]->callable());
    }
}
