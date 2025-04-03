<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Snapshot;

use Patchlevel\EventSourcing\Snapshot\Adapter\SnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\AdapterNotFound;
use Patchlevel\EventSourcing\Snapshot\ArrayAdapterRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

#[CoversClass(ArrayAdapterRepository::class)]
final class ArrayAdapterRepositoryTest extends TestCase
{
    use ProphecyTrait;

    public function testGetAdapter(): void
    {
        $adapter = $this->prophesize(SnapshotAdapter::class);
        $repository = new ArrayAdapterRepository(['memory' => $adapter->reveal()]);

        self::assertSame($adapter->reveal(), $repository->get('memory'));
    }

    public function testAdapterNotFound(): void
    {
        $this->expectException(AdapterNotFound::class);

        $repository = new ArrayAdapterRepository([]);
        $repository->get('memory');
    }
}
