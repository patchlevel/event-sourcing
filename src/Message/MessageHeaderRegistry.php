<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Message;

use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Debug\Trace\TraceHeader;
use Patchlevel\EventSourcing\Store\ArchivedHeader;
use Patchlevel\EventSourcing\Store\NewStreamStartHeader;

use function array_key_exists;
use function array_merge;

final class MessageHeaderRegistry
{
    /** @var array<string, class-string<Header>> */
    private array $nameToClassMap = [];

    /** @var array<class-string<Header>, string> */
    private array $classToNameMap = [];

    /** @param list<class-string<Header>> $headers */
    public function __construct(array $headers)
    {
        foreach ($headers as $header) {
            $this->nameToClassMap[$header::name()] = $header;
            $this->classToNameMap[$header] = $header::name();
        }
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

    /** @param list<class-string<Header>> $headers */
    public static function createWithInternalHeaders(array $headers = []): self
    {
        return new self(
            array_merge(
                $headers,
                [
                    AggregateHeader::class,
                    TraceHeader::class,
                    ArchivedHeader::class,
                    NewStreamStartHeader::class,
                ],
            ),
        );
    }
}
