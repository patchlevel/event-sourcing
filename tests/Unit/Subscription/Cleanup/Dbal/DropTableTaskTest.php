<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Cleanup\Dbal;

use Patchlevel\EventSourcing\Subscription\Cleanup\Dbal\DropTableTask;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DropTableTask::class)]
final class DropTableTaskTest extends TestCase
{
    public function testInstantiate(): void
    {
        $object = new DropTableTask('projection_table', 'foo');

        self::assertSame('projection_table', $object->table);
        self::assertSame('foo', $object->connectionName);
    }

    public function testInstantiateWithDefaults(): void
    {
        $object = new DropTableTask('projection_table');

        self::assertSame('projection_table', $object->table);
        self::assertNull($object->connectionName);
    }
}
