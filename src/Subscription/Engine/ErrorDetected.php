<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use RuntimeException;

use function array_map;
use function implode;
use function sprintf;

final class ErrorDetected extends RuntimeException
{
    /** @param list<Error> $errors */
    public function __construct(
        public readonly array $errors,
    ) {
        $sentences = array_map(
            static fn (Error $error) => sprintf(
                'Subscription %s: %s',
                $error->subscriptionId,
                $error->message,
            ),
            $errors,
        );

        parent::__construct("Error in subscription engine detected.\n" . implode("\n", $sentences));
    }
}
