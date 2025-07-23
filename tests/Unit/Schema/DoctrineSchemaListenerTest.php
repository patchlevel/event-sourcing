<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DoctrineSchemaListener::class)]
final class DoctrineSchemaListenerTest extends TestCase
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

        $doctrineSchemaListener = new DoctrineSchemaListener($schemaConfigurator);
        $doctrineSchemaListener->postGenerateSchema($event);
    }
}
