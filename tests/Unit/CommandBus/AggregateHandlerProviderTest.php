<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\CommandBus;

use Patchlevel\EventSourcing\CommandBus\AggregateHandlerProvider;
use Patchlevel\EventSourcing\CommandBus\Handler\CreateAggregateHandler;
use Patchlevel\EventSourcing\CommandBus\Handler\DefaultParameterResolver;
use Patchlevel\EventSourcing\CommandBus\Handler\UpdateAggregateHandler;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ChangeProfileName;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\CreateProfile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

#[CoversClass(AggregateHandlerProvider::class)]
final class AggregateHandlerProviderTest extends TestCase
{
    use ProphecyTrait;

    public function testEmpty(): void
    {
        $repositoryManager = $this->prophesize(RepositoryManager::class);

        $provider = new AggregateHandlerProvider(
            new AggregateRootRegistry([]),
            $repositoryManager->reveal(),
        );

        $result = $provider->handlerForCommand(CreateProfile::class);

        self::assertCount(0, $result);
    }

    public function testGetCreateHandler(): void
    {
        $repositoryManager = $this->prophesize(RepositoryManager::class);

        $provider = new AggregateHandlerProvider(
            new AggregateRootRegistry(['profile' => ProfileWithHandler::class]),
            $repositoryManager->reveal(),
        );

        $result = $provider->handlerForCommand(CreateProfile::class);

        $handler = new CreateAggregateHandler(
            $repositoryManager->reveal(),
            ProfileWithHandler::class,
            'create',
            new DefaultParameterResolver(),
        );

        self::assertCount(1, $result);
        self::assertEquals($handler->__invoke(...), $result[0]->callable());
    }

    public function testGetUpdateHandler(): void
    {
        $repositoryManager = $this->prophesize(RepositoryManager::class);

        $provider = new AggregateHandlerProvider(
            new AggregateRootRegistry(['profile' => ProfileWithHandler::class]),
            $repositoryManager->reveal(),
        );

        $result = $provider->handlerForCommand(ChangeProfileName::class);

        $handler = new UpdateAggregateHandler(
            $repositoryManager->reveal(),
            ProfileWithHandler::class,
            'updateName',
            new DefaultParameterResolver(),
        );

        self::assertCount(1, $result);
        self::assertEquals($handler->__invoke(...), $result[0]->callable());
    }
}
