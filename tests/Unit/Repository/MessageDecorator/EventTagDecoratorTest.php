<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Repository\MessageDecorator;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Repository\MessageDecorator\EventTagDecorator;
use Patchlevel\EventSourcing\Serializer\EventTagExtractor;
use Patchlevel\EventSourcing\Store\Header\TagsHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EventTagDecorator::class)]
final class EventTagDecoratorTest extends TestCase
{
    public function testAddTagsHeader(): void
    {
        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('foo@bar.com'),
        );

        $message = Message::create($event);

        $extractor = $this->createMock(EventTagExtractor::class);
        $extractor
            ->expects($this->once())
            ->method('extract')
            ->with($event)
            ->willReturn(['profile-1']);

        $decorator = new EventTagDecorator($extractor);

        $decoratedMessage = $decorator($message);

        self::assertSame(['profile-1'], $decoratedMessage->header(TagsHeader::class)->tags);
    }
}
