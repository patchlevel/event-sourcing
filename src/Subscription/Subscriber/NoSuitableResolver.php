<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber;

use RuntimeException;

use function sprintf;

class NoSuitableResolver extends RuntimeException
{
    public function __construct(string $class, string $methodName, string $argumentName)
    {
        parent::__construct(
            sprintf(
                'No suitable resolver found for argument "%s" in method "%s" of class "%s"',
                $argumentName,
                $methodName,
                $class,
            ),
        );
    }
}
