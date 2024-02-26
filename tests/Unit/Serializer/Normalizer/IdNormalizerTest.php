<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Serializer\Normalizer;

use Attribute;
use Patchlevel\EventSourcing\Aggregate\CustomId;
use Patchlevel\EventSourcing\Aggregate\Uuid;
use Patchlevel\EventSourcing\Serializer\Normalizer\IdNormalizer;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\Hydrator\Normalizer\InvalidArgument;
use Patchlevel\Hydrator\Normalizer\InvalidType;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Ramsey\Uuid\Exception\InvalidUuidStringException;
use ReflectionClass;
use ReflectionType;
use RuntimeException;

/** @covers \Patchlevel\EventSourcing\Serializer\Normalizer\IdNormalizer */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class IdNormalizerTest extends TestCase
{
    use ProphecyTrait;

    public function testNormalizeWithNull(): void
    {
        $normalizer = new IdNormalizer(CustomId::class);
        $this->assertEquals(null, $normalizer->normalize(null));
    }

    public function testDenormalizeWithNull(): void
    {
        $normalizer = new IdNormalizer(CustomId::class);
        $this->assertEquals(null, $normalizer->denormalize(null));
    }

    public function testNormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('type "Patchlevel\EventSourcing\Aggregate\CustomId" was expected but "string" was passed.');

        $normalizer = new IdNormalizer(CustomId::class);
        $normalizer->normalize('foo');
    }

    public function testDenormalizeWithInvalidArgument(): void
    {
        $this->expectException(InvalidUuidStringException::class);

        $normalizer = new IdNormalizer(Uuid::class);
        $normalizer->denormalize('foo');
    }

    public function testNormalizeWithValue(): void
    {
        $normalizer = new IdNormalizer(CustomId::class);
        $this->assertEquals('foo', $normalizer->normalize(new CustomId('foo')));
    }

    public function testDenormalizeWithValue(): void
    {
        $normalizer = new IdNormalizer(CustomId::class);
        $this->assertEquals(new CustomId('foo'), $normalizer->denormalize('foo'));
    }

    public function testDenormalizeWithWrongValue(): void
    {
        $normalizer = new IdNormalizer(CustomId::class);

        $this->expectException(InvalidArgument::class);
        $normalizer->denormalize(123);
    }

    public function testAutoDetect(): void
    {
        $normalizer = new IdNormalizer();
        $normalizer->handleReflectionType($this->reflectionType(ProfileCreated::class, 'profileId'));

        self::assertEquals(ProfileId::class, $normalizer->aggregateIdClass());
    }

    public function testAutoDetectMissingType(): void
    {
        $this->expectException(InvalidType::class);

        $normalizer = new IdNormalizer();
        $normalizer->aggregateIdClass();
    }

    public function testAutoDetectMissingTypeBecauseNull(): void
    {
        $this->expectException(InvalidType::class);

        $normalizer = new IdNormalizer();
        $normalizer->handleReflectionType(null);

        $normalizer->aggregateIdClass();
    }

    /** @param class-string $class */
    private function reflectionType(string $class, string $property): ReflectionType
    {
        $reflection = new ReflectionClass($class);
        $property = $reflection->getProperty($property);

        $type = $property->getType();

        if (!$type instanceof ReflectionType) {
            throw new RuntimeException('no type');
        }

        return $type;
    }
}
