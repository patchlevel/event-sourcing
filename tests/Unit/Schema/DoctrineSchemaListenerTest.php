<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaListener;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Schema\DoctrineSchemaListener */
final class DoctrineSchemaListenerTest extends TestCase
{
    use ProphecyTrait;

    public function testPostGenerateSchema(): void
    {
        $connection = $this->prophesize(Connection::class)->reveal();
        $em = $this->prophesize(EntityManagerInterface::class);
        $em->getConnection()->willReturn($connection);
        $expectedSchema = new Schema();

        $schemaConfigurator = $this->prophesize(DoctrineSchemaConfigurator::class);
        $schemaConfigurator->configureSchema($expectedSchema, $connection)->shouldBeCalled();

        $event = new GenerateSchemaEventArgs($em->reveal(), $expectedSchema);

        $doctrineSchemaSubscriber = new DoctrineSchemaListener($schemaConfigurator->reveal());
        $doctrineSchemaSubscriber->postGenerateSchema($event);
    }
}
