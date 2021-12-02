<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline\Target;

use Patchlevel\EventSourcing\Pipeline\EventBucket;
use Patchlevel\EventSourcing\Pipeline\Target\InMemoryTarget;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;

class InMemoryTargetTest extends TestCase
{
    public function testSave(): void
    {
        $inMemoryTarget = new InMemoryTarget();

        $bucket = new EventBucket(
            Profile::class,
            1,
            ProfileCreated::raise(ProfileId::fromString('1'), Email::fromString('foo@test.com'))
        );
        $inMemoryTarget->save($bucket);

        $buckets = $inMemoryTarget->buckets();
        self::assertCount(1, $buckets);
        self::assertEquals($bucket, $buckets[0]);
    }
}
