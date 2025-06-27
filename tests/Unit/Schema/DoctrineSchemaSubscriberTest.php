<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DoctrineSchemaSubscriber::class)]
final class DoctrineSchemaSubscriberTest extends TestCase
{
    public function testPostGenerateSchema(): void
    {
        $connection = $this->createMock(Connection::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);
        $expectedSchema = new Schema();

        $schemaConfigurator = $this->createMock(DoctrineSchemaConfigurator::class);
        $schemaConfigurator->expects($this->atLeastOnce())->method('configureSchema')->with($expectedSchema, $connection);

        $event = new GenerateSchemaEventArgs($em, $expectedSchema);

        $doctrineSchemaSubscriber = new DoctrineSchemaSubscriber($schemaConfigurator);
        $doctrineSchemaSubscriber->postGenerateSchema($event);
    }

    public function testGetSubscribedEvents(): void
    {
        $schemaConfigurator = $this->createMock(DoctrineSchemaConfigurator::class);

        $doctrineSchemaSubscriber = new DoctrineSchemaSubscriber($schemaConfigurator);
        $events = $doctrineSchemaSubscriber->getSubscribedEvents();

        self::assertEquals(['postGenerateSchema'], $events);
    }
}
