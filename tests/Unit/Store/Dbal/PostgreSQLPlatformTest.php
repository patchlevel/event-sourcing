<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store\Dbal;

use Doctrine\DBAL\Schema\Index;
use Patchlevel\EventSourcing\Store\Dbal\PostgreSQLPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PostgreSQLPlatform::class)]
final class PostgreSQLPlatformTest extends TestCase
{
    public function testCreateGinIndex(): void
    {
        $platform = new PostgreSQLPlatform();

        self::assertSame(
            'CREATE INDEX "event_store_tags_gin_idx" ON event_store USING gin ("tags" jsonb_path_ops)',
            $platform->getCreateIndexSQL(
                new Index('event_store_tags_gin_idx', ['tags']),
                'event_store',
            ),
        );
    }

    public function testCreateRegularIndex(): void
    {
        $platform = new PostgreSQLPlatform();

        self::assertSame(
            'CREATE INDEX event_store_tags_idx ON event_store (tags)',
            $platform->getCreateIndexSQL(
                new Index('event_store_tags_idx', ['tags']),
                'event_store',
            ),
        );
    }

    public function testUniqueIndexIsNotCreatedAsGin(): void
    {
        $platform = new PostgreSQLPlatform();

        self::assertSame(
            'CREATE UNIQUE INDEX foo_gin_idx ON event_store (tags)',
            $platform->getCreateIndexSQL(
                new Index('foo_gin_idx', ['tags'], true),
                'event_store',
            ),
        );
    }

    public function testMultiColumnIndexIsNotCreatedAsGin(): void
    {
        $platform = new PostgreSQLPlatform();

        self::assertSame(
            'CREATE INDEX foo_gin_idx ON event_store (tags, stream)',
            $platform->getCreateIndexSQL(
                new Index('foo_gin_idx', ['tags', 'stream']),
                'event_store',
            ),
        );
    }
}
