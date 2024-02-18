<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Message;

use Patchlevel\EventSourcing\EventBus\Header;
use function array_flip;
use function array_key_exists;

final class MessageHeaderRegistry
{
    /** @var array<string, class-string<Header>> */
    private array $nameToClassMap;

    /** @var array<class-string<Header>, string> */
    private array $classToNameMap;

    /** @param array<string, class-string<Header>> $headerNameToClassMap */
    public function __construct(array $headerNameToClassMap)
    {
        $this->nameToClassMap = $headerNameToClassMap;
        $this->classToNameMap = array_flip($headerNameToClassMap);
    }

    /** @param class-string<Header> $headerClass */
    public function headerName(string $headerClass): string
    {
        if (!array_key_exists($headerClass, $this->classToNameMap)) {
            throw new HeaderClassNotRegistered($headerClass);
        }

        return $this->classToNameMap[$headerClass];
    }

    /** @return class-string<Header> */
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

    /** @return array<string, class-string<Header>> */
    public function headerClasses(): array
    {
        return $this->nameToClassMap;
    }

    /** @return array<class-string<Header>, string> */
    public function headerNames(): array
    {
        return $this->classToNameMap;
    }
}
