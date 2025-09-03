<?php

namespace Pikulsky\EncryptedStreams\Cipher;

use Pikulsky\EncryptedStreams\Key\VideoKey;

/**
 * Video-specific WhatsApp cipher.
 *
 * Serves as a simple facade for creating a cipher for video media:
 * it automatically wraps the given key in {@see VideoKey} and provides
 * easy access to encrypt, decrypt, and sidecar operations.
 *
 * Implements {@see WhatsAppCipherInterface}.
 */
class WhatsAppVideoCipher extends WhatsAppCipher implements WhatsAppCipherInterface
{
    public function __construct(string $key)
    {
        $videoKey = new VideoKey($key);
        parent::__construct($videoKey);
    }
}
