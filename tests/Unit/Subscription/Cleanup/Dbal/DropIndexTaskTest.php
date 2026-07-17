<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Cleanup\Dbal;

use Patchlevel\EventSourcing\Subscription\Cleanup\Dbal\DropIndexTask;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DropIndexTask::class)]
final class DropIndexTaskTest extends TestCase
{
    public function testInstantiate(): void
    {
        $object = new DropIndexTask('idx_foo', 'projection_table', 'foo');

        self::assertSame('idx_foo', $object->index);
        self::assertSame('projection_table', $object->table);
        self::assertSame('foo', $object->connectionName);
    }

    public function testInstantiateWithDefaults(): void
    {
        $object = new DropIndexTask('idx_foo', 'projection_table');

        self::assertSame('idx_foo', $object->index);
        self::assertNull($object->connectionName);
    }
}
