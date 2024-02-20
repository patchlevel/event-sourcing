<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projection;

use Throwable;

use function array_key_exists;
use function array_map;
use function array_walk_recursive;
use function get_resource_type;
use function is_object;
use function is_resource;
use function sprintf;

/**
 * @psalm-import-type Context from ProjectionError
 * @psalm-import-type Trace from ProjectionError
 */
final class ThrowableToErrorContextTransformer
{
    /** @return list<Context> */
    public static function transform(Throwable $error): array
    {
        $errors = [];

        do {
            $errors[] = self::transformThrowable($error);
            $error = $error->getPrevious();
        } while ($error);

        return $errors;
    }

    /** @return Context */
    private static function transformThrowable(Throwable $error): array
    {
        /** @var list<Trace> $traces */
        $traces = $error->getTrace();

        return [
            'class' => $error::class,
            'message' => $error->getMessage(),
            'code' => $error->getCode(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'trace' => array_map(self::transformTrace(...), $traces),
        ];
    }

    /**
     * @param Trace $trace
     *
     * @return Trace
     */
    private static function transformTrace(array $trace): array
    {
        if (!array_key_exists('args', $trace)) {
            return $trace;
        }

        array_walk_recursive($trace['args'], static function (mixed &$value): void {
            if (is_object($value)) {
                $value = sprintf('object(%s)', $value::class);
            }

            if (!is_resource($value)) {
                return;
            }

            $value = sprintf('resource(%s)', get_resource_type($value));
        });

        return $trace;
    }
}
