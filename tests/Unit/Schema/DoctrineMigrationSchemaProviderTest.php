<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Schema;

use Doctrine\DBAL\Schema\Schema;
use Patchlevel\EventSourcing\Schema\DoctrineMigrationSchemaProvider;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaProvider;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Schema\DoctrineMigrationSchemaProvider */
final class DoctrineMigrationSchemaProviderTest extends TestCase
{
    use ProphecyTrait;

    public function testCreateSchema(): void
    {
        $expectedSchema = new Schema();

        $schemaProvider = $this->prophesize(DoctrineSchemaProvider::class);
        $schemaProvider->schema()->willReturn($expectedSchema);

        $doctrineSchemaManager = new DoctrineMigrationSchemaProvider($schemaProvider->reveal());
        $schema = $doctrineSchemaManager->createSchema();

        $this->assertEquals($expectedSchema, $schema);
    }
}
