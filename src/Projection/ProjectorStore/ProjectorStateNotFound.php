<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\ProjectorStore;

use RuntimeException;

final class ProjectorStateNotFound extends RuntimeException
{
}
