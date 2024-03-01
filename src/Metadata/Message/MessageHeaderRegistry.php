<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Message;

use function array_flip;
use function array_key_exists;

final class MessageHeaderRegistry
{
    /** @var array<string, class-string> */
    private array $nameToClassMap;

    /** @var array<class-string, string> */
    private array $classToNameMap;

    /** @param array<string, class-string> $headerNameToClassMap */
    public function __construct(array $headerNameToClassMap)
    {
        $this->nameToClassMap = $headerNameToClassMap;
        $this->classToNameMap = array_flip($headerNameToClassMap);
    }

    /** @param class-string $headerClass */
    public function headerName(string $headerClass): string
    {
        if (!array_key_exists($headerClass, $this->classToNameMap)) {
            throw new HeaderClassNotRegistered($headerClass);
        }

        return $this->classToNameMap[$headerClass];
    }

    /** @return class-string */
    public function headerClass(string $headerName): string
    {
        if (!array_key_exists($headerName, $this->nameToClassMap)) {
            throw new HeaderNameNotRegistered($headerName);
        }

        return $this->nameToClassMap[$headerName];
    }

    public function hasHeaderClass(string $headerClass): bool
    {
        return array_key_exists($headerClass, $this->classToNameMap);
    }

    public function hasHeaderName(string $headerName): bool
    {
        return array_key_exists($headerName, $this->nameToClassMap);
    }

    /** @return array<string, class-string> */
    public function headerClasses(): array
    {
        return $this->nameToClassMap;
    }

    /** @return array<class-string, string> */
    public function headerNames(): array
    {
        return $this->classToNameMap;
    }
}
