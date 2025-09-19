<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use RuntimeException;

use function count;
use function sprintf;

final class ErrorDetected extends RuntimeException
{
    /** @param list<Error> $errors */
    public function __construct(
        public readonly array $errors,
    ) {
        parent::__construct(
            sprintf(
                '%s error(s) in subscription engine detected. First error is in "%s" subscription: %s',
                count($errors),
                $errors[0]->subscriptionId,
                $errors[0]->message,
            ),
            previous: $errors[0]->throwable,
        );
    }
}
