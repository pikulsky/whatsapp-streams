<?php

namespace Pikulsky\EncryptedStreams\Cipher;

use Pikulsky\EncryptedStreams\Key\ImageKey;

/**
 * Image-specific WhatsApp cipher.
 *
 * Serves as a simple facade for creating a cipher for image media:
 * it automatically wraps the given key in {@see ImageKey} and provides
 * easy access to encrypt and decrypt operations.
 *
 * Implements {@see WhatsAppCipherInterface}.
 */
class WhatsAppImageCipher extends WhatsAppCipher implements WhatsAppCipherInterface
{
    public function __construct(string $key)
    {
        $imageKey = new ImageKey($key);
        parent::__construct($imageKey);
    }
}
