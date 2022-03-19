<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline\Target;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Pipeline\Target\StoreTarget;
use Patchlevel\EventSourcing\Store\PipelineStore;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Pipeline\Target\StoreTarget */
class StoreTargetTest extends TestCase
{
    use ProphecyTrait;

    public function testSave(): void
    {
        $message = new Message(
            Profile::class,
            '1',
            1,
            new ProfileCreated(ProfileId::fromString('1'), Email::fromString('foo@test.com'))
        );

        $pipelineStore = $this->prophesize(PipelineStore::class);
        $pipelineStore->save($message)->shouldBeCalled();

        $storeTarget = new StoreTarget($pipelineStore->reveal());

        $storeTarget->save($message);
    }
}
