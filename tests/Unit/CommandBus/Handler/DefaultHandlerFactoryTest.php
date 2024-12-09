<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\CommandBus\Handler;

use Patchlevel\EventSourcing\CommandBus\Handler\CreateAggregateHandler;
use Patchlevel\EventSourcing\CommandBus\Handler\DefaultHandlerFactory;
use Patchlevel\EventSourcing\CommandBus\Handler\UpdateAggregateHandler;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Container\ContainerInterface;

/** @covers \Patchlevel\EventSourcing\CommandBus\Handler\DefaultHandlerFactory */
final class DefaultHandlerFactoryTest extends TestCase
{
    use ProphecyTrait;

    public function testCreateHandler(): void
    {
        $repositoryManager = $this->prophesize(RepositoryManager::class);
        $container = $this->prophesize(ContainerInterface::class);

        $handlerFactory = new DefaultHandlerFactory(
            $repositoryManager->reveal(),
            $container->reveal(),
        );

        $handler = $handlerFactory->createHandler(
            'aggregateClass',
            'method',
        );

        $this->assertInstanceOf(CreateAggregateHandler::class, $handler);
    }

    public function testUpdateHandler(): void
    {
        $repositoryManager = $this->prophesize(RepositoryManager::class);
        $container = $this->prophesize(ContainerInterface::class);

        $handlerFactory = new DefaultHandlerFactory(
            $repositoryManager->reveal(),
            $container->reveal(),
        );

        $handler = $handlerFactory->updateHandler(
            'aggregateClass',
            'method',
        );

        $this->assertInstanceOf(UpdateAggregateHandler::class, $handler);
    }
}
