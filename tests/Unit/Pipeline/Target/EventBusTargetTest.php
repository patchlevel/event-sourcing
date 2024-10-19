<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline\Target;

use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Pipeline\Target\EventBusTarget;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Pipeline\Target\EventBusTarget */
final class EventBusTargetTest extends TestCase
{
    use ProphecyTrait;

    public function testSave(): void
    {
        $message = new Message(
            new ProfileCreated(ProfileId::fromString('1'), Email::fromString('foo@test.com')),
        );

        $pipelineStore = $this->prophesize(EventBus::class);
        $pipelineStore->dispatch($message)->shouldBeCalled();

        $storeTarget = new EventBusTarget($pipelineStore->reveal());

        $storeTarget->save($message);
    }
}
