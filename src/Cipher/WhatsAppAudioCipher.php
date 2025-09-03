<?php

namespace Pikulsky\EncryptedStreams\Cipher;

use Pikulsky\EncryptedStreams\Key\AudioKey;

/**
 * Audio-specific WhatsApp cipher.
 *
 * Serves as a simple facade for creating a cipher for audio media:
 * it automatically wraps the given key in {@see AudioKey} and provides
 * easy access to encrypt, decrypt, and sidecar operations.
 *
 * Implements {@see WhatsAppCipherInterface}.
 */
class WhatsAppAudioCipher extends WhatsAppCipher implements WhatsAppCipherInterface
{
    public function __construct(string $key)
    {
        $audioKey = new AudioKey($key);
        parent::__construct($audioKey);
    }
}
