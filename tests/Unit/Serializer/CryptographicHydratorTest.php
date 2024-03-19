<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Serializer;

use Patchlevel\EventSourcing\Cryptography\EventPayloadCryptographer;
use Patchlevel\EventSourcing\Serializer\CryptographicHydrator;
use Patchlevel\Hydrator\Hydrator;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use stdClass;

/** @covers \Patchlevel\EventSourcing\Serializer\CryptographicHydrator */
final class CryptographicHydratorTest extends TestCase
{
    use ProphecyTrait;

    public function testHydrate(): void
    {
        $object = new stdClass();
        $payload = ['foo' => 'bar'];
        $encryptedPayload = ['foo' => 'encrypted'];

        $parentHydrator = $this->prophesize(Hydrator::class);
        $parentHydrator
            ->hydrate(stdClass::class, $payload)
            ->willReturn($object)
            ->shouldBeCalledOnce();

        $cryptographer = $this->prophesize(EventPayloadCryptographer::class);
        $cryptographer
            ->decrypt(stdClass::class, $encryptedPayload)
            ->willReturn($payload)
            ->shouldBeCalledOnce();

        $hydrator = new CryptographicHydrator(
            $parentHydrator->reveal(),
            $cryptographer->reveal(),
        );

        $return = $hydrator->hydrate(stdClass::class, $encryptedPayload);

        self::assertSame($object, $return);
    }

    public function testExtract(): void
    {
        $object = new stdClass();
        $payload = ['foo' => 'bar'];
        $encryptedPayload = ['foo' => 'encrypted'];

        $parentHydrator = $this->prophesize(Hydrator::class);
        $parentHydrator
            ->extract($object)
            ->willReturn($payload)
            ->shouldBeCalledOnce();

        $cryptographer = $this->prophesize(EventPayloadCryptographer::class);
        $cryptographer
            ->encrypt(stdClass::class, $payload)
            ->willReturn($encryptedPayload)
            ->shouldBeCalledOnce();

        $hydrator = new CryptographicHydrator(
            $parentHydrator->reveal(),
            $cryptographer->reveal(),
        );

        $return = $hydrator->extract($object);

        self::assertSame($encryptedPayload, $return);
    }
}
