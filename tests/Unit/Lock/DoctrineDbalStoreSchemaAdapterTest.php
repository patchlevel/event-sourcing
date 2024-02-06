<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Lock;

use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Patchlevel\EventSourcing\Lock\DoctrineDbalStoreSchemaAdapter;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use RuntimeException;
use Symfony\Component\Lock\Store\DoctrineDbalStore;

/** @covers \Patchlevel\EventSourcing\Lock\DoctrineDbalStoreSchemaAdapter */
final class DoctrineDbalStoreSchemaAdapterTest extends TestCase
{
    use ProphecyTrait;

    public function testConfigureSchema(): void
    {
        $schema = new Schema();
        $store = $this->prophesize(DoctrineDbalStore::class);
        //$store->configureSchema($schema, Argument::type('Closure'))->shouldBeCalledOnce();

        $store->configureSchema($schema, Argument::any())->shouldBeCalledOnce()->will(
            /** @param array{1: Closure} $args */
            static function (array $args): void {
                if (!$args[1]()) {
                    throw new RuntimeException();
                }
            },
        );

        $adapter = new DoctrineDbalStoreSchemaAdapter($store->reveal());
        $adapter->configureSchema($schema, $this->prophesize(Connection::class)->reveal());
    }
}
