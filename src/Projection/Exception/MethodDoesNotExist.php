<?php declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Exception;

use Patchlevel\EventSourcing\Projection\Projection;
use function sprintf;

final class MethodDoesNotExist extends ProjectionException
{
    /**
     * @param class-string<Projection> $class
     * @param string $method
     */
    public function __construct(string $class, string $method)
    {
        parent::__construct(sprintf('method "%s" does not exists in %s', $method, $class));
    }
}
