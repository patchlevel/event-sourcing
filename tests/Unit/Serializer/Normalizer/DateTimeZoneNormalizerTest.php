<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Serializer\Normalizer;

use DateTimeZone;
use Patchlevel\EventSourcing\Serializer\Normalizer\DateTimeZoneNormalizer;
use Patchlevel\EventSourcing\Serializer\Normalizer\InvalidArgument;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

final class DateTimeZoneNormalizerTest extends TestCase
{
    use ProphecyTrait;

    public function testNormalizeWithNull(): void
    {
        $normalizer = new DateTimeZoneNormalizer();
        $this->assertEquals(null, $normalizer->normalize(null));
    }

    public function testDenormalizeWithNull(): void
    {
        $normalizer = new DateTimeZoneNormalizer();
        $this->assertEquals(null, $normalizer->denormalize(null));
    }

    public function testNormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);

        $normalizer = new DateTimeZoneNormalizer();
        $normalizer->normalize(123);
    }

    public function testDenormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);

        $normalizer = new DateTimeZoneNormalizer();
        $normalizer->denormalize(123);
    }

    public function testNormalizeWithValue(): void
    {
        $normalizer = new DateTimeZoneNormalizer();
        $this->assertEquals('EDT', $normalizer->normalize(new DateTimeZone('EDT')));
    }

    public function testDenormalizeWithValue(): void
    {
        $normalizer = new DateTimeZoneNormalizer();
        $this->assertEquals(new DateTimeZone('EDT'), $normalizer->denormalize('EDT'));
    }
}
