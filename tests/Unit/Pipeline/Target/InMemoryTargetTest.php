<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline\Target;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Pipeline\Target\InMemoryTarget;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Pipeline\Target\InMemoryTarget */
final class InMemoryTargetTest extends TestCase
{
    public function testSave(): void
    {
        $inMemoryTarget = new InMemoryTarget();

        $message = new Message(
            new ProfileCreated(ProfileId::fromString('1'), Email::fromString('foo@test.com')),
        );
        $inMemoryTarget->save($message);

        $messages = $inMemoryTarget->messages();

        self::assertSame([$message], $messages);
    }
}
