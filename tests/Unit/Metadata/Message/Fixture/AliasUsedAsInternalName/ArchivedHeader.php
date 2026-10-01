<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Message\Fixture\AliasUsedAsInternalName;

use Patchlevel\EventSourcing\Attribute\Header;

/** @immutable */
#[Header('archive', aliases: ['archived'])]
final class ArchivedHeader
{
}
