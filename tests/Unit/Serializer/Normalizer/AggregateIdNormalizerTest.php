<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Serializer\Normalizer;

use Attribute;
use Patchlevel\EventSourcing\Aggregate\BasicAggregateRootId;
use Patchlevel\EventSourcing\Aggregate\UuidAggregateRootId;
use Patchlevel\EventSourcing\Serializer\Normalizer\AggregateIdNormalizer;
use Patchlevel\Hydrator\Normalizer\InvalidArgument;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Ramsey\Uuid\Exception\InvalidUuidStringException;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class AggregateIdNormalizerTest extends TestCase
{
    use ProphecyTrait;

    public function testNormalizeWithNull(): void
    {
        $normalizer = new AggregateIdNormalizer(BasicAggregateRootId::class);
        $this->assertEquals(null, $normalizer->normalize(null));
    }

    public function testDenormalizeWithNull(): void
    {
        $normalizer = new AggregateIdNormalizer(BasicAggregateRootId::class);
        $this->assertEquals(null, $normalizer->denormalize(null));
    }

    public function testNormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('type "Patchlevel\EventSourcing\Aggregate\BasicAggregateRootId" was expected but "string" was passed.');

        $normalizer = new AggregateIdNormalizer(BasicAggregateRootId::class);
        $normalizer->normalize('foo');
    }

    public function testDenormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidUuidStringException::class);

        $normalizer = new AggregateIdNormalizer(UuidAggregateRootId::class);
        $normalizer->denormalize('foo');
    }

    public function testNormalizeWithValue(): void
    {
        $normalizer = new AggregateIdNormalizer(BasicAggregateRootId::class);
        $this->assertEquals('foo', $normalizer->normalize(new BasicAggregateRootId('foo')));
    }

    public function testDenormalizeWithValue(): void
    {
        $normalizer = new AggregateIdNormalizer(BasicAggregateRootId::class);
        $this->assertEquals(new BasicAggregateRootId('foo'), $normalizer->denormalize('foo'));
    }
}
