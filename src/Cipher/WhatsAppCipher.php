<?php

namespace Pikulsky\EncryptedStreams\Cipher;

use Pikulsky\EncryptedStreams\Key\MediaTypeKeyInterface;

/**
 * Implements {@see WhatsAppCipherInterface} for WhatsApp media payloads.
 *
 * This class computes HMAC-based sidecars for verification, and manages key material
 * using {@see MediaTypeKeyInterface}.
 *
 * Internally, the media key is expanded via HKDF and split into IV, cipher key,
 * and MAC key required for the encryption process.
 */
class WhatsAppCipher implements WhatsAppCipherInterface
{
    protected const HKDF_ALGO = 'sha256';
    protected const HKDF_SIZE = 112;
    protected const HMAC_ALGO = 'sha256';

    private string $cipherKey;
    private string $iv;
    private string $macKey;

    public function __construct(MediaTypeKeyInterface $mediaTypeKey)
    {
        $expandedKey = $this->getMediaKeyExpanded($mediaTypeKey);
        $this->setKeysFromExpanded($expandedKey);
    }

    /**
     * Generates a sidecar (verification metadata) for the given payload.
     *
     * A sidecar typically contains metadata or verification data needed
     * for decryption or integrity checks.
     *
     * @param string $payload Media data (plaintext or ciphertext).
     * @param string $iv Initialization vector. If empty, the IV from the media key is used.
     * @return string Sidecar data for integrity verification.
     */
    public function sidecar(string $payload, string $iv): string
    {
        // Use the IV from the mediaKey if it's empty
        $iv = empty($iv) ? $this->iv : $iv;

        // Sign the data with HMAC SHA-256 with `macKey`
        $mac = hash_hmac(self::HMAC_ALGO, $iv . $payload, $this->macKey, true);

        // Truncate the result to the first 10 bytes
        $entry = substr($mac, 0, 10);

        return $entry;
    }

    /**
     * Returns the AES key used for encryption and decryption.
     *
     * @return string Binary key material.
     */
    public function getKey(): string
    {
        return $this->cipherKey;
    }

    /**
     * Returns the initialization vector (IV) used for encryption.
     *
     * @return string Binary IV value.
     */
    public function getIV(): string
    {
        return $this->iv;
    }

    /**
     * Returns the key used for message authentication (HMAC-SHA256).
     *
     * @return string Binary MAC key.
     */
    public function getMacKey(): string
    {
        return $this->macKey;
    }

    /**
     * Splits the given expanded key into 3 parts: IV, cipherKey, macKey
     *
     * @param string $mediaKeyExpanded
     * @return void
     */
    private function setKeysFromExpanded(string $mediaKeyExpanded): void
    {
        // The expanded key should be split into 3 parts: IV, cipherKey, macKey
        $this->iv = substr($mediaKeyExpanded, 0, 16);           // [:16]
        $this->cipherKey = substr($mediaKeyExpanded, 16, 32);   // [16:48]
        $this->macKey = substr($mediaKeyExpanded, 48, 32);      // [48:80]
    }

    /**
     * Expands a media key to 112 bytes using HKDF with SHA-256 and application info
     *
     * @param MediaTypeKeyInterface $mediaTypeKey
     * @return string
     */
    private function getMediaKeyExpanded(MediaTypeKeyInterface $mediaTypeKey): string
    {
        $key = $mediaTypeKey->getMediaKey();
        $applicationInfo = $mediaTypeKey->getApplicationInfo();

        return hash_hkdf(
            self::HKDF_ALGO,
            $key,
            self::HKDF_SIZE,
            $applicationInfo,
            '' // salt
        );
    }
}
