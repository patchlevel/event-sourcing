<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Attribute;

use Patchlevel\EventSourcing\Attribute\Normalize;
use Patchlevel\EventSourcing\Serializer\Normalizer\ArrayNormalizer;
use Patchlevel\EventSourcing\Serializer\Normalizer\Normalizer;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Attribute\Normalize */
class NormalizeTest extends TestCase
{
    use ProphecyTrait;

    public function testWithNormalizer(): void
    {
        $normalizer = $this->prophesize(Normalizer::class)->reveal();

        $attribute = new Normalize($normalizer);

        self::assertSame($normalizer, $attribute->normalizer());
    }

    public function testWithNormalizerAndListFlag(): void
    {
        $normalizer = $this->prophesize(Normalizer::class)->reveal();

        $attribute = new Normalize($normalizer, true);

        self::assertInstanceOf(ArrayNormalizer::class, $attribute->normalizer());
    }
}
