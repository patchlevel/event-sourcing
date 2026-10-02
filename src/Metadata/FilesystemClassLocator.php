<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata;

use ReflectionClass;

use function array_filter;
use function array_values;

final class FilesystemClassLocator implements ClassLocator
{
    /**
     * @param list<string>      $paths
     * @param class-string|null $attribute only classes with this attribute are returned
     */
    public function __construct(
        private readonly array $paths,
        private readonly string|null $attribute = null,
    ) {
    }

    /** @return list<class-string> */
    public function locate(): array
    {
        $classes = (new ClassFinder())->findClassNames($this->paths);
        $attribute = $this->attribute;

        if ($attribute === null) {
            return $classes;
        }

        return array_values(
            array_filter(
                $classes,
                static fn (string $class): bool => (new ReflectionClass($class))->getAttributes($attribute) !== [],
            ),
        );
    }
}
