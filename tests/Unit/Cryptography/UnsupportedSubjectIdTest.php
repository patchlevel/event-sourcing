<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Cryptography;

use Patchlevel\EventSourcing\Cryptography\UnsupportedSubjectId;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Cryptography\UnsupportedSubjectId */
final class UnsupportedSubjectIdTest extends TestCase
{
    public function testCreation(): void
    {
        $exception = new UnsupportedSubjectId(42);

        self::assertSame('Unsupported subject id: should be a string, got int.', $exception->getMessage());
    }
}
