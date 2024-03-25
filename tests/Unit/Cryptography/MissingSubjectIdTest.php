<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Cryptography;

use Patchlevel\EventSourcing\Cryptography\MissingSubjectId;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Cryptography\MissingSubjectId */
final class MissingSubjectIdTest extends TestCase
{
    public function testCreation(): void
    {
        $exception = new MissingSubjectId();

        self::assertSame('Missing subject id.', $exception->getMessage());
    }
}
