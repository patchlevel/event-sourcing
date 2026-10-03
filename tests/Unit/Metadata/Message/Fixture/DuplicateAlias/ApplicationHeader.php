<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Message\Fixture\DuplicateAlias;

use Patchlevel\EventSourcing\Attribute\Header;

/** @immutable */
#[Header('application', aliases: ['legacy'])]
final class ApplicationHeader
{
}
