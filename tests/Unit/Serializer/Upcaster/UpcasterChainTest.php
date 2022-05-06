<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Serializer\Upcaster;

use Patchlevel\EventSourcing\Serializer\Upcast\Upcast;
use Patchlevel\EventSourcing\Serializer\Upcast\Upcaster;
use Patchlevel\EventSourcing\Serializer\Upcast\UpcasterChain;
use PHPUnit\Framework\TestCase;

final class UpcasterChainTest extends TestCase
{
    public function testChainSuccessful(): void
    {
        $upcasterOne = new class implements Upcaster {
            public int $counter = 0;

            public function __invoke(Upcast $upcast): Upcast
            {
                $this->counter++;

                return new Upcast(self::class, $upcast->payload);
            }
        };

        $upcasterTwo = new class implements Upcaster {
            public int $counter = 0;

            public function __invoke(Upcast $upcast): Upcast
            {
                $this->counter++;

                return new Upcast(self::class, $upcast->payload);
            }
        };

        $inputPayload = ['bar' => 'baz'];
        $inputClass = UpcasterChain::class;

        $chain = new UpcasterChain([$upcasterOne, $upcasterTwo]);
        $upcast = ($chain)(new Upcast($inputClass, $inputPayload));

        self::assertSame(1, $upcasterOne->counter);
        self::assertSame(1, $upcasterTwo->counter);
        self::assertSame($upcasterTwo::class, $upcast->class);
        self::assertSame($inputPayload, $upcast->payload);
    }
}
