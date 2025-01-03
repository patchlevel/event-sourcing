<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Test;

use RuntimeException;

abstract class AggregateTestError extends RuntimeException
{
}
