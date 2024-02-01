<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer\Encoder;

use JsonException;

use function array_key_exists;
use function json_decode;
use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

final class JsonEncoder implements Encoder
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options
     */
    public function encode(array $data, array $options = []): string
    {
        $flags = JSON_THROW_ON_ERROR;

        if (array_key_exists(self::OPTION_PRETTY_PRINT, $options) && $options[self::OPTION_PRETTY_PRINT] === true) {
            $flags |= JSON_PRETTY_PRINT;
        }

        try {
            return json_encode($data, $flags);
        } catch (JsonException $e) {
            throw new EncodeNotPossible($data, $e);
        }
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function decode(string $data, array $options = []): array
    {
        try {
            /** @var array<string, mixed> $result */
            $result = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

            return $result;
        } catch (JsonException $e) {
            throw new DecodeNotPossible($data, $e);
        }
    }
}
