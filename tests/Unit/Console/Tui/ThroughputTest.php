<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Tui;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Console\Tui\Throughput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Throughput::class)]
final class ThroughputTest extends TestCase
{
    public function testRate(): void
    {
        $throughput = new Throughput();

        $throughput->record('profile', 100, new DateTimeImmutable('@1000'));
        self::assertNull($throughput->rate('profile'));

        $throughput->record('profile', 300, new DateTimeImmutable('@1002'));
        $throughput->record('profile', 500, new DateTimeImmutable('@1004'));
        self::assertSame(100.0, $throughput->rate('profile'));

        // only the last 10 seconds count
        $throughput->record('profile', 700, new DateTimeImmutable('@1014'));
        self::assertSame(20.0, $throughput->rate('profile'));
    }

    public function testResetWhenPositionGoesBack(): void
    {
        $throughput = new Throughput();

        $throughput->record('profile', 500, new DateTimeImmutable('@1000'));
        $throughput->record('profile', 0, new DateTimeImmutable('@1002'));
        self::assertNull($throughput->rate('profile'));

        $throughput->record('profile', 10, new DateTimeImmutable('@1004'));
        self::assertSame(5.0, $throughput->rate('profile'));
    }

    public function testForget(): void
    {
        $throughput = new Throughput();

        $throughput->record('profile', 100, new DateTimeImmutable('@1000'));
        $throughput->record('profile', 200, new DateTimeImmutable('@1001'));
        $throughput->record('123', 100, new DateTimeImmutable('@1000'));
        $throughput->record('123', 200, new DateTimeImmutable('@1001'));

        $throughput->retain(['123']);
        self::assertNull($throughput->rate('profile'));
        self::assertSame(100.0, $throughput->rate('123'));

        $throughput->record('123', null, new DateTimeImmutable('@1002'));
        self::assertNull($throughput->rate('123'));
    }
}
