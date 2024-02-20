<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Projection;

use Patchlevel\EventSourcing\Projection\Projectionist\ProjectionistCriteria;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Projection\Projectionist\ProjectionistCriteria */
final class ProjectionCriteriaTest extends TestCase
{
    public function testProjectionId(): void
    {
        $id = 'test';
        $criteria = new ProjectionistCriteria([$id]);

        self::assertEquals([$id], $criteria->ids);
    }
}
