<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\CommandBus;

use Patchlevel\EventSourcing\CommandBus\AggregateHandlerProvider;
use Patchlevel\EventSourcing\CommandBus\Handler\CreateAggregateHandler;
use Patchlevel\EventSourcing\CommandBus\Handler\DefaultParameterResolver;
use Patchlevel\EventSourcing\CommandBus\Handler\UpdateAggregateHandler;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\BaseCommand;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ChangeProfileName;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\CreateProfile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithHandler;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithInheritanceHandler;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\SomeCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AggregateHandlerProvider::class)]
final class AggregateHandlerProviderTest extends TestCase
{
    public function testEmpty(): void
    {
        $repositoryManager = $this->createMock(RepositoryManager::class);

        $provider = new AggregateHandlerProvider(
            new AggregateRootRegistry([]),
            $repositoryManager,
        );

        $result = [...$provider->handlerForCommand(CreateProfile::class)];

        self::assertCount(0, $result);
    }

    public function testGetCreateHandler(): void
    {
        $repositoryManager = $this->createMock(RepositoryManager::class);

        $provider = new AggregateHandlerProvider(
            new AggregateRootRegistry(['profile' => ProfileWithHandler::class]),
            $repositoryManager,
        );

        $result = [...$provider->handlerForCommand(CreateProfile::class)];

        $handler = new CreateAggregateHandler(
            $repositoryManager,
            ProfileWithHandler::class,
            'create',
            new DefaultParameterResolver(),
        );

        self::assertCount(1, $result);
        self::assertEquals($handler->__invoke(...), $result[0]->callable());
    }

    public function testGetUpdateHandler(): void
    {
        $repositoryManager = $this->createMock(RepositoryManager::class);

        $provider = new AggregateHandlerProvider(
            new AggregateRootRegistry(['profile' => ProfileWithHandler::class]),
            $repositoryManager,
        );

        $result = [...$provider->handlerForCommand(ChangeProfileName::class)];

        $handler = new UpdateAggregateHandler(
            $repositoryManager,
            ProfileWithHandler::class,
            'updateName',
            new DefaultParameterResolver(),
        );

        self::assertCount(1, $result);
        self::assertEquals($handler->__invoke(...), $result[0]->callable());
    }

    public function testGetHandlerByInterface(): void
    {
        $repositoryManager = $this->createMock(RepositoryManager::class);
        $command = new class () implements SomeCommand {
        };

        $provider = new AggregateHandlerProvider(
            new AggregateRootRegistry(['profile' => ProfileWithInheritanceHandler::class]),
            $repositoryManager,
        );

        $result = [...$provider->handlerForCommand($command::class)];

        $handler = new UpdateAggregateHandler(
            $repositoryManager,
            ProfileWithInheritanceHandler::class,
            'handleInterface',
            new DefaultParameterResolver(),
        );

        self::assertCount(1, $result);
        self::assertEquals($handler->__invoke(...), $result[0]->callable());
    }

    public function testGetHandlerByAbstractClass(): void
    {
        $repositoryManager = $this->createMock(RepositoryManager::class);
        $command = new class () extends BaseCommand {
        };

        $provider = new AggregateHandlerProvider(
            new AggregateRootRegistry(['profile' => ProfileWithInheritanceHandler::class]),
            $repositoryManager,
        );

        $result = [...$provider->handlerForCommand($command::class)];

        $handler = new UpdateAggregateHandler(
            $repositoryManager,
            ProfileWithInheritanceHandler::class,
            'handleAbstract',
            new DefaultParameterResolver(),
        );

        self::assertCount(1, $result);
        self::assertEquals($handler->__invoke(...), $result[0]->callable());
    }
}
