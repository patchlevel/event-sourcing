<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer\Hydrator;

use Patchlevel\EventSourcing\Serializer\SerializeException;

final class MissingPlayhead extends SerializeException
{
}
