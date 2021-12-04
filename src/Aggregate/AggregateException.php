<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use RuntimeException;

abstract class AggregateException extends RuntimeException
{
}
