<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Schema;

use Doctrine\DBAL\Schema\Schema;
use Patchlevel\EventSourcing\Schema\DoctrineMigrationSchemaProvider;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DoctrineMigrationSchemaProvider::class)]
final class DoctrineMigrationSchemaProviderTest extends TestCase
{
    public function testCreateSchema(): void
    {
        $expectedSchema = new Schema();

        $schemaProvider = $this->createMock(DoctrineSchemaProvider::class);
        $schemaProvider->method('schema')->willReturn($expectedSchema);

        $doctrineSchemaManager = new DoctrineMigrationSchemaProvider($schemaProvider);
        $schema = $doctrineSchemaManager->createSchema();

        $this->assertEquals($expectedSchema, $schema);
    }
}
