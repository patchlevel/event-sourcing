<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Schema;

use Doctrine\DBAL\Schema\Schema;
use Patchlevel\EventSourcing\Schema\MigrationSchemaProvider;
use Patchlevel\EventSourcing\Store\DoctrineStore;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Schema\MigrationSchemaProvider */
final class MigrationSchemaProviderTest extends TestCase
{
    use ProphecyTrait;

    public function testCreateSchema(): void
    {
        $expectedSchema = new Schema();

        $store = $this->prophesize(DoctrineStore::class);
        $store->schema()->willReturn($expectedSchema);

        $doctrineSchemaManager = new MigrationSchemaProvider($store->reveal());
        $schema = $doctrineSchemaManager->createSchema();

        $this->assertEquals($expectedSchema, $schema);
    }
}
