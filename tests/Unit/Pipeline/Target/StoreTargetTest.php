<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline\Target;

use Patchlevel\EventSourcing\Pipeline\EventBucket;
use Patchlevel\EventSourcing\Pipeline\Target\StoreTarget;
use Patchlevel\EventSourcing\Store\PipelineStore;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class StoreTargetTest extends TestCase
{
    use ProphecyTrait;

    public function testSave(): void
    {
        $bucket = new EventBucket(
            Profile::class,
            ProfileCreated::raise(ProfileId::fromString('1'), Email::fromString('foo@test.com'))
        );

        $pipelineStore = $this->prophesize(PipelineStore::class);
        $pipelineStore->saveEventBucket($bucket)->shouldBeCalled();

        $storeTarget = new StoreTarget($pipelineStore->reveal());

        $storeTarget->save($bucket);
    }
}
