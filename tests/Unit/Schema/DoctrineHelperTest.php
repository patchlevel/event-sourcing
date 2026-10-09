<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Patchlevel\EventSourcing\Schema\DoctrineHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function str_starts_with;

#[CoversClass(DoctrineHelper::class)]
final class DoctrineHelperTest extends TestCase
{
    public function testSameConnection(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->never())
            ->method('getParams');

        self::assertTrue(DoctrineHelper::sameDatabase($connection, $connection));
    }

    public function testSameParams(): void
    {
        $connectionA = $this->createMock(Connection::class);
        $connectionA
            ->expects($this->once())
            ->method('getParams')
            ->willReturn(['dbname' => 'db']);
        $connectionA
            ->expects($this->never())
            ->method('executeStatement');

        $connectionB = $this->createMock(Connection::class);
        $connectionB
            ->expects($this->once())
            ->method('getParams')
            ->willReturn(['dbname' => 'db']);
        $connectionB
            ->expects($this->never())
            ->method('executeStatement');

        self::assertTrue(DoctrineHelper::sameDatabase($connectionA, $connectionB));
    }

    public function testDifferentDatabase(): void
    {
        $connectionA = $this->createMock(Connection::class);
        $connectionA
            ->expects($this->once())
            ->method('getParams')
            ->willReturn(['dbname' => 'db']);
        $connectionA
            ->expects($this->exactly(2))
            ->method('executeStatement')
            ->with($this->matchesRegularExpression('/^(CREATE|DROP) TABLE same_db_check_[0-9a-f]{14}/'))
            ->willReturn(1);

        $connectionB = $this->createMock(Connection::class);
        $connectionB
            ->expects($this->once())
            ->method('getParams')
            ->willReturn(['dbname' => 'db2']);
        $connectionB
            ->expects($this->once())
            ->method('executeStatement')
            ->with($this->matchesRegularExpression('/^DROP TABLE same_db_check_[0-9a-f]{14}/'))
            ->willThrowException(new TableNotFoundException(new Exception('table not found'), null));

        self::assertFalse(DoctrineHelper::sameDatabase($connectionA, $connectionB));
    }

    public function testSameDatabaseDetectedViaCheckTable(): void
    {
        $connectionA = $this->createMock(Connection::class);
        $connectionA
            ->expects($this->once())
            ->method('getParams')
            ->willReturn(['dbname' => 'db', 'host' => 'a']);
        $connectionA
            ->expects($this->exactly(2))
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql): int {
                if (str_starts_with($sql, 'CREATE TABLE same_db_check_')) {
                    return 1;
                }

                throw new TableNotFoundException(new Exception('table not found'), null);
            });

        $connectionB = $this->createMock(Connection::class);
        $connectionB
            ->expects($this->once())
            ->method('getParams')
            ->willReturn(['dbname' => 'db', 'host' => 'b']);
        $connectionB
            ->expects($this->once())
            ->method('executeStatement')
            ->with($this->matchesRegularExpression('/^DROP TABLE same_db_check_[0-9a-f]{14}/'))
            ->willReturn(1);

        self::assertTrue(DoctrineHelper::sameDatabase($connectionA, $connectionB));
    }

    public function testIgnoresErrorOnSecondConnectionDrop(): void
    {
        $connectionA = $this->createMock(Connection::class);
        $connectionA
            ->expects($this->once())
            ->method('getParams')
            ->willReturn(['dbname' => 'db']);
        $connectionA
            ->expects($this->exactly(2))
            ->method('executeStatement')
            ->with($this->matchesRegularExpression('/^(CREATE|DROP) TABLE same_db_check_[0-9a-f]{14}/'))
            ->willReturn(1);

        $connectionB = $this->createMock(Connection::class);
        $connectionB
            ->expects($this->once())
            ->method('getParams')
            ->willReturn(['dbname' => 'db2']);
        $connectionB
            ->expects($this->once())
            ->method('executeStatement')
            ->willThrowException(new RuntimeException('connection error'));

        self::assertFalse(DoctrineHelper::sameDatabase($connectionA, $connectionB));
    }
}
