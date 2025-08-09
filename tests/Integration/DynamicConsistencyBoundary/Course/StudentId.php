<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Course;

use Patchlevel\EventSourcing\Aggregate\RamseyUuidV7Behaviour;
use Patchlevel\EventSourcing\Stringable;

final class StudentId implements Stringable
{
    use RamseyUuidV7Behaviour;
}
