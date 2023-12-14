<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projection\Store;

use Throwable;

use function serialize;
use function unserialize;

final class ErrorSerializer
{
    public static function serialize(Throwable|null $error): string|null
    {
        if ($error === null) {
            return null;
        }

        return serialize($error);
    }

    public static function unserialize(string|null $error): Throwable|null
    {
        if ($error === null) {
            return null;
        }

        $result = @unserialize($error, ['allowed_classes' => true]);

        if (!$result instanceof Throwable) {
            return null;
        }

        return $result;
    }
}
