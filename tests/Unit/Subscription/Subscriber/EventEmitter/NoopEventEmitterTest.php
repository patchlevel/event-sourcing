<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Subscriber\EventEmitter;

use Patchlevel\EventSourcing\Subscription\Subscriber\EventEmitter\NoopEventEmitter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(NoopEventEmitter::class)]
final class NoopEventEmitterTest extends TestCase
{
    public function testEmitAndLinkToDoNothing(): void
    {
        $emitter = new NoopEventEmitter();

        $emitter->emit([new stdClass()]);
        $emitter->linkTo('foo', [new stdClass()]);

        $this->expectNotToPerformAssertions();
    }
}
