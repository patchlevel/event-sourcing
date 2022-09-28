<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaSubscriber;
use Patchlevel\EventSourcing\Schema\SchemaConfigurator;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Schema\DoctrineSchemaSubscriber */
final class DoctrineSchemaSubscriberTest extends TestCase
{
    use ProphecyTrait;

    public function testPostGenerateSchema(): void
    {
        $connection = $this->prophesize(Connection::class)->reveal();
        $em = $this->prophesize(EntityManagerInterface::class);
        $em->getConnection()->willReturn($connection);
        $expectedSchema = new Schema();

        $schemaConfigurator = $this->prophesize(SchemaConfigurator::class);
        $schemaConfigurator->configureSchema($expectedSchema, $connection)->shouldBeCalled();

        $event = new GenerateSchemaEventArgs($em->reveal(), $expectedSchema);

        $doctrineSchemaSubscriber = new DoctrineSchemaSubscriber($schemaConfigurator->reveal());
        $doctrineSchemaSubscriber->postGenerateSchema($event);
    }

    public function testGetSubscribedEvents(): void
    {
        $schemaConfigurator = $this->prophesize(SchemaConfigurator::class);

        $doctrineSchemaSubscriber = new DoctrineSchemaSubscriber($schemaConfigurator->reveal());
        $events = $doctrineSchemaSubscriber->getSubscribedEvents();

        self::assertEquals(['postGenerateSchema'], $events);
    }
}
