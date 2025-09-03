<?php

namespace Pikulsky\EncryptedStreams\Key;

use InvalidArgumentException;

/**
 * Base implementation of {@see MediaTypeKeyInterface}.
 *
 * Stores a 32-byte cryptographic key together with its {@see ApplicationInfoEnum}.
 * Validates key length on construction.
 */
abstract class MediaTypeKey implements MediaTypeKeyInterface
{
    private const KEY_LENGTH = 32;

    public function __construct(
        private readonly string $key,
        private readonly ApplicationInfoEnum $applicationInfo,
    ) {
        if (strlen($this->key) !== self::KEY_LENGTH) {
            throw new InvalidArgumentException(sprintf('Key length should be %d bytes', self::KEY_LENGTH));
        }
    }

    public function getMediaKey(): string
    {
        return $this->key;
    }

    public function getApplicationInfo(): string
    {
        return $this->applicationInfo->value;
    }
}
