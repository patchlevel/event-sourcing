<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Message;

use Patchlevel\EventSourcing\Message\MissingHeaders;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MissingHeaders::class)]
final class MissingHeadersTest extends TestCase
{
    public function testInstantiate(): void
    {
        $headers = ['foo' => ['bar' => 'baz']];

        $missingHeaders = new MissingHeaders($headers);

        self::assertSame($headers, $missingHeaders->headers);
    }
}
