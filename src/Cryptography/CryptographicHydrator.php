<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Cryptography;

use Patchlevel\Hydrator\Hydrator;

final class CryptographicHydrator implements Hydrator
{
    public function __construct(
        private readonly Hydrator $hydrator,
        private readonly EventPayloadCryptographer $cryptographer,
    ) {
    }

    /**
     * @param class-string<T>      $class
     * @param array<string, mixed> $data
     *
     * @return T
     *
     * @template T of object
     */
    public function hydrate(string $class, array $data): object
    {
        $data = $this->cryptographer->decrypt($class, $data);

        return $this->hydrator->hydrate($class, $data);
    }

    /** @return array<string, mixed> */
    public function extract(object $object): array
    {
        $data = $this->hydrator->extract($object);

        return $this->cryptographer->encrypt($object::class, $data);
    }
}
