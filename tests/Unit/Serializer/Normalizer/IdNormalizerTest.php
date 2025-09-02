<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Serializer\Normalizer;

use Attribute;
use Patchlevel\EventSourcing\Identifier\CustomId;
use Patchlevel\EventSourcing\Identifier\Uuid;
use Patchlevel\EventSourcing\Serializer\Normalizer\IdNormalizer;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\Hydrator\Normalizer\InvalidArgument;
use Patchlevel\Hydrator\Normalizer\InvalidType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Exception\InvalidUuidStringException;
use Symfony\Component\TypeInfo\Type;

#[CoversClass(IdNormalizer::class)]
#[Attribute(Attribute::TARGET_PROPERTY)]
final class IdNormalizerTest extends TestCase
{
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
        $this->expectExceptionMessage('type "Patchlevel\EventSourcing\Identifier\CustomId" was expected but "string" was passed.');

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
        $normalizer->handleType(Type::object(ProfileId::class));;

        self::assertEquals(ProfileId::class, $normalizer->identifierClass());
    }

    public function testAutoDetectMissingType(): void
    {
        $this->expectException(InvalidType::class);

        $normalizer = new IdNormalizer();
        $normalizer->identifierClass();
    }

    public function testAutoDetectMissingTypeBecauseNull(): void
    {
        $this->expectException(InvalidType::class);

        $normalizer = new IdNormalizer();
        $normalizer->handleType(null);

        $normalizer->identifierClass();
    }
}
