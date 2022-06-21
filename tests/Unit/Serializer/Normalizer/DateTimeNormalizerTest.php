<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Serializer\Normalizer;

use DateTime;
use Patchlevel\EventSourcing\Serializer\Normalizer\DateTimeNormalizer;
use Patchlevel\EventSourcing\Serializer\Normalizer\InvalidArgument;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class DateTimeNormalizerTest extends TestCase
{
    use ProphecyTrait;

    public function testNormalizeWithNull(): void
    {
        $normalizer = new DateTimeNormalizer();
        $this->assertEquals(null, $normalizer->normalize(null));
    }

    public function testDenormalizeWithNull(): void
    {
        $normalizer = new DateTimeNormalizer();
        $this->assertEquals(null, $normalizer->denormalize(null));
    }

    public function testNormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);

        $normalizer = new DateTimeNormalizer();
        $normalizer->normalize(123);
    }

    public function testDenormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);

        $normalizer = new DateTimeNormalizer();
        $normalizer->denormalize(123);
    }

    public function testNormalizeWithValue(): void
    {
        $normalizer = new DateTimeNormalizer();
        $this->assertEquals('2015-02-13T22:34:32+01:00', $normalizer->normalize(new DateTime('2015-02-13 22:34:32+01:00')));
    }

    public function testNormalizeWithChangeFormat(): void
    {
        $normalizer = new DateTimeNormalizer(format: DateTime::RFC822);
        $this->assertEquals('Fri, 13 Feb 15 22:34:32 +0100', $normalizer->normalize(new DateTime('2015-02-13 22:34:32+01:00')));
    }

    public function testDenormalizeWithValue(): void
    {
        $normalizer = new DateTimeNormalizer();
        $this->assertEquals(new DateTime('2015-02-13 22:34:32+01:00'), $normalizer->denormalize('2015-02-13T22:34:32+01:00'));
    }

    public function testDenormalizeWithChangeFormat(): void
    {
        $normalizer = new DateTimeNormalizer(format: DateTime::RFC822);
        $this->assertEquals(new DateTime('2015-02-13 22:34:32+01:00'), $normalizer->denormalize('Fri, 13 Feb 15 22:34:32 +0100'));
    }
}
