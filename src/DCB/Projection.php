<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\DCB;

use Closure;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Message\Message;
use ReflectionClass;
use RuntimeException;

use function is_a;
use function method_exists;
use function sprintf;

/**
 * @experimental
 * @template S as mixed
 */
abstract class Projection
{
    /** @return list<string> */
    abstract public function tagFilter(): array;

    /** @return S */
    abstract public function initialState(): mixed;

    /**
     * @param S $state
     *
     * @return S
     */
    public function apply(mixed $state, Message $message): mixed
    {
        $event = $message->event();

        $method = 'apply' . (new ReflectionClass($event))->getShortName();

        if (method_exists($this, $method)) {
            $state = $this->{$method}($state, $event);
        }

        return $state;
    }

    /** @return array<class-string, Closure(mixed, object): mixed> */
    private static function applies(): array
    {
        $reflection = new ReflectionClass(static::class);
        $methods = $reflection->getMethods();

        $applies = [];

        foreach ($methods as $method) {
            $attributes = $method->getAttributes(Apply::class);

            if ($attributes === []) {
                continue;
            }

            foreach ($attributes as $attribute) {
                /** @var Apply $apply */
                $apply = $attribute->newInstance();

                if ($apply->class === null) {
                    $applies[$method->getName()] = Closure::fromCallable([$reflection->getName(), $method->getName()]);
                    continue;
                }

                if (!is_a($apply->class, static::class, true)) {
                    throw new RuntimeException(
                        sprintf('Apply class %s must be a subclass of %s', $apply->class, static::class),
                    );
                }

                $applies[$apply->class] = Closure::fromCallable([$reflection->getName(), $method->getName()]);
            }
        }

        return $applies;
    }
}
