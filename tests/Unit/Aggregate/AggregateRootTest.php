<?php declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Aggregate;

use Patchlevel\EventSourcing\Tests\Unit\Aggregate\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Aggregate\Fixture\Message;
use Patchlevel\EventSourcing\Tests\Unit\Aggregate\Fixture\MessageId;
use Patchlevel\EventSourcing\Tests\Unit\Aggregate\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Aggregate\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;

class AggregateRootTest extends TestCase
{
    public function testCreateAggregate()
    {
        $id = ProfileId::fromString('1');
        $email = Email::fromString('d.a.badura@gmail.com');

        $profile = Profile::createProfile($id, $email);

        self::assertEquals('1', $profile->aggregateRootId());
        self::assertEquals(0, $profile->playhead());
        self::assertEquals($id, $profile->id());
        self::assertEquals($email, $profile->email());

        $events = $profile->releaseEvents();

        self::assertCount(1, $events);
    }

    public function testExecuteMethod()
    {
        $profileId = ProfileId::fromString('1');
        $email = Email::fromString('d.a.badura@gmail.com');

        $messageId = MessageId::fromString('2');

        $profile = Profile::createProfile($profileId, $email);

        $events = $profile->releaseEvents();

        self::assertCount(1, $events);
        self::assertEquals(0, $profile->playhead());

        $profile->publishMessage(
            Message::create(
                $messageId,
                'foo'
            )
        );

        self::assertEquals('1', $profile->aggregateRootId());
        self::assertEquals(1, $profile->playhead());
        self::assertEquals($profileId, $profile->id());
        self::assertEquals($email, $profile->email());

        $events = $profile->releaseEvents();

        self::assertCount(1, $events);
    }
}
