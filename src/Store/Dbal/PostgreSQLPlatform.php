<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Dbal;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform as BasePostgreSQLPlatform;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;

use function count;
use function sprintf;
use function str_ends_with;

/**
 * PostgreSQL platform that can create GIN indexes on jsonb columns.
 *
 * The schema abstraction of doctrine/dbal cannot express the index method,
 * so every non-unique single column index whose name ends with the suffix
 * {@see self::GIN_INDEX_SUFFIX} is created as `USING gin (column jsonb_path_ops)`.
 * The schema comparator does not compare the index method, so these indexes
 * stay stable in schema diffs.
 */
final class PostgreSQLPlatform extends BasePostgreSQLPlatform
{
    public const GIN_INDEX_SUFFIX = '_gin_idx';

    public function getCreateIndexSQL(Index $index, string $table): string
    {
        $name = $index->getObjectName();
        $columns = $index->getIndexedColumns();

        if (
            !str_ends_with($name->getIdentifier()->getValue(), self::GIN_INDEX_SUFFIX)
            || $index->getType() !== IndexType::REGULAR
            || count($columns) !== 1
        ) {
            return parent::getCreateIndexSQL($index, $table);
        }

        return sprintf(
            'CREATE INDEX %s ON %s USING gin (%s jsonb_path_ops)',
            $name->toSQL($this),
            $table,
            $columns[0]->getColumnName()->toSQL($this),
        );
    }
}
