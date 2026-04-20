<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Container;

use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

use function sprintf;

final class ServiceNotFound extends RuntimeException implements NotFoundExceptionInterface
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Service "%s" not found.', $id));
    }
}
