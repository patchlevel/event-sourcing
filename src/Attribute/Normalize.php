<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;
use Patchlevel\EventSourcing\Serializer\Normalizer;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Normalize
{
    /** @var class-string<Normalizer> */
    private string $normalizerClass;

    /**
     * @param class-string<Normalizer> $normalizerClass
     */
    public function __construct(string $normalizerClass)
    {
        $this->normalizerClass = $normalizerClass;
    }

    /**
     * @return class-string<Normalizer>
     */
    public function normalizerClass(): string
    {
        return $this->normalizerClass;
    }
}
