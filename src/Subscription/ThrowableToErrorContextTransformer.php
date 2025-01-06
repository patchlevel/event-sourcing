<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription;

use Throwable;

use function array_key_exists;
use function array_map;
use function array_pop;
use function explode;
use function get_debug_type;
use function get_resource_type;
use function implode;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_object;
use function is_resource;
use function mb_strlen;
use function mb_substr;

/**
 * @psalm-import-type Context from SubscriptionError
 * @psalm-import-type Trace from SubscriptionError
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

        $classParts = explode('\\', $error::class);
        $shortClass = array_pop($classParts);
        $namespace = implode('\\', $classParts);

        return [
            'short_name' => $shortClass,
            'namespace' => $namespace,
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
        if (array_key_exists('class', $trace) && is_string($trace['class'])) {
            $trace['class'] = str_replace("\x00", '', $trace['class']);
        }

        if (!array_key_exists('args', $trace)) {
            return $trace;
        }

        $trace['args'] = self::flattenArgs($trace['args']);

        return $trace;
    }

    /**
     * @param array<array-key, mixed> $args
     *
     * @return array<array-key, mixed>
     */
    private static function flattenArgs(array $args, int $level = 0, int &$count = 0): array
    {
        $result = [];

        foreach ($args as $key => $value) {
            $count++;

            if ($count > 10_000) {
                return ['array', '*SKIPPED over 10000 entries*'];
            }

            if (is_object($value)) {
                $result[$key] = ['object', get_debug_type($value)];
            } elseif (is_array($value)) {
                if ($level > 10) {
                    $result[$key] = ['array', '*DEEP NESTED ARRAY*'];
                } else {
                    $result[$key] = ['array', self::flattenArgs($value, $level + 1, $count)];
                }
            } elseif ($value === null) {
                $result[$key] = ['null', null];
            } elseif (is_bool($value)) {
                $result[$key] = ['boolean', $value];
            } elseif (is_int($value)) {
                $result[$key] = ['integer', $value];
            } elseif (is_float($value)) {
                $result[$key] = ['float', $value];
            } elseif (is_resource($value)) {
                $result[$key] = ['resource', get_resource_type($value)];
            } else {
                if (mb_strlen((string)$value) > 1_000) {
                    $result[$key] = ['string', '*TOO LONG STRING TRUNCATED* ' . mb_substr((string)$value, 0, 1_000) . '...'];
                } else {
                    $result[$key] = ['string', (string)$value];
                }
            }
        }

        return $result;
    }
}
