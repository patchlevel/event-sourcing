<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Cryptography;

use Patchlevel\EventSourcing\Cryptography\Cipher\Cipher;
use Patchlevel\EventSourcing\Cryptography\Cipher\CipherKeyFactory;
use Patchlevel\EventSourcing\Cryptography\Cipher\DecryptionFailed;
use Patchlevel\EventSourcing\Cryptography\Cipher\OpensslCipher;
use Patchlevel\EventSourcing\Cryptography\Cipher\OpensslCipherKeyFactory;
use Patchlevel\EventSourcing\Cryptography\Store\CipherKeyNotExists;
use Patchlevel\EventSourcing\Cryptography\Store\CipherKeyStore;
use Patchlevel\EventSourcing\Metadata\Event\EventMetadataFactory;

use function array_key_exists;
use function is_string;

final class DefaultEventPayloadCryptographer implements EventPayloadCryptographer
{
    public function __construct(
        private readonly EventMetadataFactory $eventMetadataFactory,
        private readonly CipherKeyStore $cipherKeyStore,
        private readonly CipherKeyFactory $cipherKeyFactory,
        private readonly Cipher $cipher,
    ) {
    }

    /**
     * @param class-string         $class
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function encrypt(string $class, array $data): array
    {
        $subjectId = $this->subjectId($class, $data);

        if ($subjectId === null) {
            return $data;
        }

        try {
            $cipherKey = $this->cipherKeyStore->get($subjectId);
        } catch (CipherKeyNotExists) {
            $cipherKey = ($this->cipherKeyFactory)();
            $this->cipherKeyStore->store($subjectId, $cipherKey);
        }

        $metadata = $this->eventMetadataFactory->metadata($class);

        foreach ($metadata->propertyMetadata as $propertyMetadata) {
            if (!$propertyMetadata->isPersonalData) {
                continue;
            }

            $data[$propertyMetadata->fieldName] = $this->cipher->encrypt(
                $cipherKey,
                $data[$propertyMetadata->fieldName],
            );
        }

        return $data;
    }

    /**
     * @param class-string         $class
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function decrypt(string $class, array $data): array
    {
        $subjectId = $this->subjectId($class, $data);

        if ($subjectId === null) {
            return $data;
        }

        try {
            $cipherKey = $this->cipherKeyStore->get($subjectId);
        } catch (CipherKeyNotExists) {
            $cipherKey = null;
        }

        $metadata = $this->eventMetadataFactory->metadata($class);

        foreach ($metadata->propertyMetadata as $propertyMetadata) {
            if (!$propertyMetadata->isPersonalData) {
                continue;
            }

            if (!$cipherKey) {
                $data[$propertyMetadata->fieldName] = $propertyMetadata->personalDataFallback;
                continue;
            }

            try {
                $data[$propertyMetadata->fieldName] = $this->cipher->decrypt(
                    $cipherKey,
                    $data[$propertyMetadata->fieldName],
                );
            } catch (DecryptionFailed) {
                $data[$propertyMetadata->fieldName] = $propertyMetadata->personalDataFallback;
            }
        }

        return $data;
    }

    /**
     * @param class-string         $class
     * @param array<string, mixed> $data
     */
    private function subjectId(string $class, array $data): string|null
    {
        $metadata = $this->eventMetadataFactory->metadata($class);

        if ($metadata->dataSubjectIdField === null) {
            return null;
        }

        if (!array_key_exists($metadata->dataSubjectIdField, $data)) {
            throw new MissingSubjectId();
        }

        $subjectId = $data[$metadata->dataSubjectIdField];

        if (!is_string($subjectId)) {
            throw new UnsupportedSubjectId($subjectId);
        }

        return $subjectId;
    }

    public static function createWithOpenssl(EventMetadataFactory $eventMetadataFactory, CipherKeyStore $cryptoStore): static
    {
        return new self(
            $eventMetadataFactory,
            $cryptoStore,
            new OpensslCipherKeyFactory(),
            new OpensslCipher(),
        );
    }
}