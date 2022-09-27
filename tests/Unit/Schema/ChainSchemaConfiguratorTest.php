<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Patchlevel\EventSourcing\Schema\ChainSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\SchemaConfigurator;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Schema\ChainSchemaConfigurator */
final class ChainSchemaConfiguratorTest extends TestCase
{
    use ProphecyTrait;

    public function testChain(): void
    {
        $schema = $this->prophesize(Schema::class)->reveal();
        $connection = $this->prophesize(Connection::class)->reveal();

        $configurator1 = $this->prophesize(SchemaConfigurator::class);
        $configurator1->configureSchema($schema, $connection)->shouldBeCalledOnce();
        $configurator2 = $this->prophesize(SchemaConfigurator::class);
        $configurator2->configureSchema($schema, $connection)->shouldBeCalledOnce();

        $chain = new ChainSchemaConfigurator([
            $configurator1->reveal(),
            $configurator2->reveal(),
        ]);

        $chain->configureSchema($schema, $connection);
    }
}
