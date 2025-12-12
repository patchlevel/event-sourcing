<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Query;

use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\ProfileId;

final readonly class QueryProfileName
{
    public function __construct(public ProfileId $id)
    {
    }
}
