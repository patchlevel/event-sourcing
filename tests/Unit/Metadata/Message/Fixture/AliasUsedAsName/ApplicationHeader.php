<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Message\Fixture\AliasUsedAsName;

use Patchlevel\EventSourcing\Attribute\Header;

/** @immutable */
#[Header('application')]
final class ApplicationHeader
{
}
