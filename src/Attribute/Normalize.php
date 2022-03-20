<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;
use Patchlevel\EventSourcing\Serializer\Normalizer;

use function is_string;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Normalize
{
    private Normalizer $normalizer;

    /**
     * @param Normalizer|class-string<Normalizer> $normalizer
     */
    public function __construct(Normalizer|string $normalizer)
    {
        if (is_string($normalizer)) {
            $this->normalizer = new $normalizer();

            return;
        }

        $this->normalizer = $normalizer;
    }

    public function normalizer(): Normalizer
    {
        return $this->normalizer;
    }
}
