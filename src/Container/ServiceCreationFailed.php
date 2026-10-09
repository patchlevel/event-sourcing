<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Container;

use Psr\Container\ContainerExceptionInterface;
use RuntimeException;

final class ServiceCreationFailed extends RuntimeException implements ContainerExceptionInterface
{
}
