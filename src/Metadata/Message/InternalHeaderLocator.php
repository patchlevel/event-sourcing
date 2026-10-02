<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Message;

use Patchlevel\EventSourcing\Metadata\ClassLocator;
use Patchlevel\EventSourcing\Store\ArchivedHeader;
use Patchlevel\EventSourcing\Store\Header\EventIdHeader;
use Patchlevel\EventSourcing\Store\Header\IndexHeader;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\Header\TagsHeader;
use Patchlevel\EventSourcing\Store\StreamStartHeader;

/** @internal */
final class InternalHeaderLocator implements ClassLocator
{
    /** @return list<class-string> */
    public function locate(): array
    {
        return [
            StreamNameHeader::class,
            PlayheadHeader::class,
            RecordedOnHeader::class,
            ArchivedHeader::class,
            StreamStartHeader::class,
            EventIdHeader::class,
            IndexHeader::class,
            TagsHeader::class,
        ];
    }
}
