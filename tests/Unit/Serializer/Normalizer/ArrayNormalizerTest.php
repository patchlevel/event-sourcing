<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Serializer\Normalizer;

use Patchlevel\EventSourcing\Serializer\Normalizer\ArrayNormalizer;
use Patchlevel\EventSourcing\Serializer\Normalizer\InvalidArgument;
use Patchlevel\EventSourcing\Serializer\Normalizer\Normalizer;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

final class ArrayNormalizerTest extends TestCase
{
    use ProphecyTrait;

    public function testNormalizeWithNull(): void
    {
        $innerNormalizer = $this->prophesize(Normalizer::class);

        $normalizer = new ArrayNormalizer($innerNormalizer->reveal());
        $this->assertEquals(null, $normalizer->normalize(null));
    }

    public function testDenormalizeWithNull(): void
    {
        $innerNormalizer = $this->prophesize(Normalizer::class);

        $normalizer = new ArrayNormalizer($innerNormalizer->reveal());
        $this->assertEquals(null, $normalizer->denormalize(null));
    }

    public function testNormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);

        $innerNormalizer = $this->prophesize(Normalizer::class);

        $normalizer = new ArrayNormalizer($innerNormalizer->reveal());
        $normalizer->normalize('foo');
    }

    public function testDenormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);

        $innerNormalizer = $this->prophesize(Normalizer::class);

        $normalizer = new ArrayNormalizer($innerNormalizer->reveal());
        $normalizer->denormalize('foo');
    }

    public function testNormalizeWithValue(): void
    {
        $innerNormalizer = new class implements Normalizer {
            public function normalize(mixed $value): string
            {
                return (string)$value;
            }

            public function denormalize(mixed $value): int
            {
                return (int)$value;
            }
        };

        $normalizer = new ArrayNormalizer($innerNormalizer);
        $this->assertEquals(['1', '2'], $normalizer->normalize([1, 2]));
    }

    public function testDenormalizeWithValue(): void
    {
        $innerNormalizer = new class implements Normalizer {
            public function normalize(mixed $value): string
            {
                return (string)$value;
            }

            public function denormalize(mixed $value): int
            {
                return (int)$value;
            }
        };

        $normalizer = new ArrayNormalizer($innerNormalizer);
        $this->assertEquals([1, 2], $normalizer->denormalize(['1', '2']));
    }
}
