<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projection\Store;

use Throwable;

use function array_walk_recursive;
use function get_resource_type;
use function is_object;
use function is_resource;
use function sprintf;

/**
 * @psalm-type Trace = array{file?: string, line?: int, function?: string, class?: string, type?: string, args?: array}
 * @psalm-type Context = array{class: class-string, message: string, code: int|string, file: string, line: int, trace: list<Trace>}
 */
final class ErrorContext
{
    /** @return list<Context> */
    public static function fromThrowable(Throwable $error): array
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
        return [
            'class' => $error::class,
            'message' => $error->getMessage(),
            'code' => $error->getCode(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'trace' => self::transformTrace($error->getTrace()),
        ];
    }

    /**
     * @param list<Trace> $trace
     *
     * @return list<Trace>
     */
    private static function transformTrace(array $trace): array
    {
        array_walk_recursive($trace, static function (mixed &$value): void {
            if (is_object($value)) {
                $value = sprintf('object(%s)', $value::class);
            }

            if (!is_resource($value)) {
                return;
            }

            $value = sprintf('resource(%s)', get_resource_type($value));
        });

        /** @var list<Trace> $trace */
        return $trace;
    }
}
