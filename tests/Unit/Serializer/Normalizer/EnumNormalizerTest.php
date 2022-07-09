<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Serializer\Normalizer;

use Patchlevel\EventSourcing\Serializer\Normalizer\EnumNormalizer;
use Patchlevel\EventSourcing\Serializer\Normalizer\InvalidArgument;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Status;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

final class EnumNormalizerTest extends TestCase
{
    use ProphecyTrait;

    public function testNormalizeWithNull(): void
    {
        $normalizer = new EnumNormalizer(Status::class);
        $this->assertEquals(null, $normalizer->normalize(null));
    }

    public function testDenormalizeWithNull(): void
    {
        $normalizer = new EnumNormalizer(Status::class);
        $this->assertEquals(null, $normalizer->denormalize(null));
    }

    public function testNormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);

        $normalizer = new EnumNormalizer(Status::class);
        $normalizer->normalize('foo');
    }

    public function testDenormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);

        $normalizer = new EnumNormalizer(Status::class);
        $normalizer->denormalize('foo');
    }

    public function testNormalizeWithValue(): void
    {
        $normalizer = new EnumNormalizer(Status::class);
        $this->assertEquals('pending', $normalizer->normalize(Status::Pending));
    }

    public function testDenormalizeWithValue(): void
    {
        $normalizer = new EnumNormalizer(Status::class);
        $this->assertEquals(Status::Pending, $normalizer->denormalize('pending'));
    }
}
