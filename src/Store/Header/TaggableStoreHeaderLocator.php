<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Header;

use Patchlevel\EventSourcing\Metadata\ClassLocator;
use Patchlevel\EventSourcing\Store\ArchivedHeader;

/** Headers which are stored in their own columns by the TaggableDoctrineDbalStore. */
final class TaggableStoreHeaderLocator implements ClassLocator
{
    /** @return list<class-string> */
    public function locate(): array
    {
        return [
            StreamNameHeader::class,
            PlayheadHeader::class,
            RecordedOnHeader::class,
            ArchivedHeader::class,
            EventIdHeader::class,
            IndexHeader::class,
            TagsHeader::class,
        ];
    }
}
