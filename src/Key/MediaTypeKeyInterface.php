<?php

namespace Pikulsky\EncryptedStreams\Key;

/**
 * Defines how a cryptographic key is provided for media encryption/decryption.
 *
 * Implementations supply both the raw key material and media-type–specific
 * application information. The application info depends on the type of media
 * being encrypted or decrypted (e.g. image, audio, video).
 */
interface MediaTypeKeyInterface
{
    /**
     * Returns the raw cryptographic key as a binary string.
     *
     * @return string 32-byte cryptographic key
     */
    public function getMediaKey(): string;

    /**
     * Returns media-type-specific application info required by the cipher.
     *
     * @return string
     */
    public function getApplicationInfo(): string;
}
