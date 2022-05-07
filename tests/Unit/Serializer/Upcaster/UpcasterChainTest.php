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

                return new Upcast('profile_1', $upcast->payload);
            }
        };

        $upcasterTwo = new class implements Upcaster {
            public int $counter = 0;

            public function __invoke(Upcast $upcast): Upcast
            {
                $this->counter++;

                return new Upcast('profile_2', $upcast->payload + ['foo' => 'bar']);
            }
        };

        $inputPayload = ['bar' => 'baz'];
        $inputEventName = 'profile';

        $chain = new UpcasterChain([$upcasterOne, $upcasterTwo]);
        $upcast = ($chain)(new Upcast($inputEventName, $inputPayload));

        self::assertSame(1, $upcasterOne->counter);
        self::assertSame(1, $upcasterTwo->counter);
        self::assertSame('profile_2', $upcast->eventName);
        self::assertSame(
            [
                'bar' => 'baz',
                'foo' => 'bar',
            ],
            $upcast->payload
        );
    }
}
