<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Patchlevel\EventSourcing\Schema\ChainDoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaConfigurator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChainDoctrineSchemaConfigurator::class)]
final class ChainDoctrineSchemaConfiguratorTest extends TestCase
{
    public function testChain(): void
    {
        $schema = $this->createMock(Schema::class);
        $connection = $this->createMock(Connection::class);

        $configurator1 = $this->createMock(DoctrineSchemaConfigurator::class);
        $configurator1->expects($this->once())->method('configureSchema')->with($schema, $connection);
        $configurator2 = $this->createMock(DoctrineSchemaConfigurator::class);
        $configurator2->expects($this->once())->method('configureSchema')->with($schema, $connection);

        $chain = new ChainDoctrineSchemaConfigurator([
            $configurator1,
            $configurator2,
        ]);

        $chain->configureSchema($schema, $connection);
    }
}
