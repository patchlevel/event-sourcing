<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store;

use DateTimeImmutable;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Patchlevel\EventSourcing\Store\DoctrineHelper;
use Patchlevel\EventSourcing\Store\InvalidType;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Store\DoctrineHelper */
final class DoctrineHelperTest extends TestCase
{
    use ProphecyTrait;

    public function testNormalizePlayhead(): void
    {
        $platform = $this->prophesize(AbstractPlatform::class);

        $result = DoctrineHelper::normalizePlayhead('1', $platform->reveal());
        self::assertSame(1, $result);
    }

    public function testNormalizePlayheadInvalid(): void
    {
        $platform = $this->prophesize(AbstractPlatform::class);

        $this->expectException(InvalidType::class);
        DoctrineHelper::normalizePlayhead('asd', $platform->reveal());
    }

    public function testNormalizeRecordedOn(): void
    {
        $platform = $this->prophesize(AbstractPlatform::class);
        $platform->getDateTimeTzFormatString()->shouldBeCalledOnce()->willReturn('Y-m-d H:i:s');

        $result = DoctrineHelper::normalizeRecordedOn('2020-10-10 10:10:10', $platform->reveal());
        self::assertEquals(new DateTimeImmutable('2020-10-10 10:10:10'), $result);
    }

    public function testNormalizeRecordedOnInvalid(): void
    {
        $platform = $this->prophesize(AbstractPlatform::class);

        $type = Type::getTypeRegistry()->get(Types::DATETIMETZ_IMMUTABLE);
        Type::getTypeRegistry()->override(Types::DATETIMETZ_IMMUTABLE, new class extends Type {
            /** @inheritdoc */
            public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
            {
                return '';
            }

            public function getName(): string
            {
                return 'needed for older dbal versions';
            }

            public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
            {
                return 'not a datetime';
            }
        });

        try {
            $this->expectException(InvalidType::class);
            DoctrineHelper::normalizeRecordedOn('asd', $platform->reveal());
        } finally {
            Type::getTypeRegistry()->override(Types::DATETIMETZ_IMMUTABLE, $type);
        }
    }
}
