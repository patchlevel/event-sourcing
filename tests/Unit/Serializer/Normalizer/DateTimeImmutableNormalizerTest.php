<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Serializer\Normalizer;

use DateTime;
use DateTimeImmutable;
use Patchlevel\EventSourcing\Serializer\Normalizer\DateTimeImmutableNormalizer;
use Patchlevel\EventSourcing\Serializer\Normalizer\InvalidArgument;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

final class DateTimeImmutableNormalizerTest extends TestCase
{
    use ProphecyTrait;

    public function testNormalizeWithNull(): void
    {
        $normalizer = new DateTimeImmutableNormalizer();
        $this->assertEquals(null, $normalizer->normalize(null));
    }

    public function testDenormalizeWithNull(): void
    {
        $normalizer = new DateTimeImmutableNormalizer();
        $this->assertEquals(null, $normalizer->denormalize(null));
    }

    public function testNormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);

        $normalizer = new DateTimeImmutableNormalizer();
        $normalizer->normalize(123);
    }

    public function testDenormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);

        $normalizer = new DateTimeImmutableNormalizer();
        $normalizer->denormalize(123);
    }

    public function testNormalizeWithValue(): void
    {
        $normalizer = new DateTimeImmutableNormalizer();
        $this->assertEquals('2015-02-13T22:34:32+01:00', $normalizer->normalize(new DateTimeImmutable('2015-02-13 22:34:32+01:00')));
    }

    public function testNormalizeWithChangeFormat(): void
    {
        $normalizer = new DateTimeImmutableNormalizer(format: DateTime::RFC822);
        $this->assertEquals('Fri, 13 Feb 15 22:34:32 +0100', $normalizer->normalize(new DateTimeImmutable('2015-02-13 22:34:32+01:00')));
    }

    public function testDenormalizeWithValue(): void
    {
        $normalizer = new DateTimeImmutableNormalizer();
        $this->assertEquals(new DateTimeImmutable('2015-02-13 22:34:32+01:00'), $normalizer->denormalize('2015-02-13T22:34:32+01:00'));
    }

    public function testDenormalizeWithChangeFormat(): void
    {
        $normalizer = new DateTimeImmutableNormalizer(format: DateTime::RFC822);
        $this->assertEquals(new DateTimeImmutable('2015-02-13 22:34:32+01:00'), $normalizer->denormalize('Fri, 13 Feb 15 22:34:32 +0100'));
    }
}
