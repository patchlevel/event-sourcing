<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Message\Translator;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Translator\ExtractEventTagTranslator;
use Patchlevel\EventSourcing\Serializer\EventTagExtractor;
use Patchlevel\EventSourcing\Store\Header\TagsHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExtractEventTagTranslator::class)]
final class ExtractEventTagTranslatorTest extends TestCase
{
    public function testExtractTags(): void
    {
        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $message = Message::create($event);

        $extractor = $this->createMock(EventTagExtractor::class);
        $extractor
            ->expects($this->once())
            ->method('extract')
            ->with($event)
            ->willReturn(['profile-1']);

        $translator = new ExtractEventTagTranslator($extractor);

        $result = $translator($message);

        self::assertCount(1, $result);
        self::assertSame(['profile-1'], $result[0]->header(TagsHeader::class)->tags);
    }

    public function testOverwriteAlreadyTagged(): void
    {
        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $message = Message::create($event)
            ->withHeader(new TagsHeader(['old']));

        $extractor = $this->createMock(EventTagExtractor::class);
        $extractor
            ->expects($this->once())
            ->method('extract')
            ->with($event)
            ->willReturn(['profile-1']);

        $translator = new ExtractEventTagTranslator($extractor);

        $result = $translator($message);

        self::assertCount(1, $result);
        self::assertSame(['profile-1'], $result[0]->header(TagsHeader::class)->tags);
    }

    public function testSkipAlreadyTagged(): void
    {
        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('info@patchlevel.de'),
        );

        $message = Message::create($event)
            ->withHeader(new TagsHeader(['old']));

        $extractor = $this->createMock(EventTagExtractor::class);
        $extractor
            ->expects($this->never())
            ->method('extract');

        $translator = new ExtractEventTagTranslator($extractor, skipAlreadyTagged: true);

        $result = $translator($message);

        self::assertSame([$message], $result);
        self::assertSame(['old'], $result[0]->header(TagsHeader::class)->tags);
    }
}
