<?php

namespace Pikulsky\EncryptedStreams\Cipher;

use Pikulsky\EncryptedStreams\Key\MediaTypeKeyInterface;
use RuntimeException;

/**
 * Implements {@see WhatsAppCipherInterface} for WhatsApp media payloads.
 *
 * This class performs AES-256-CBC encryption/decryption of media data,
 * computes HMAC-based sidecars for verification, and manages key material
 * using {@see MediaTypeKeyInterface}.
 *
 * Internally, the media key is expanded via HKDF and split into IV, cipher key,
 * and MAC key required for the encryption process.
 */
class WhatsAppCipher implements WhatsAppCipherInterface
{
    protected const HKDF_ALGO = 'sha256';
    protected const HKDF_SIZE = 112;
    protected const CIPHER_ALGO = 'aes-256-cbc';
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
     * Returns the sidecar value for the given payload
     *
     * @param string $payload
     * @param string $iv IV: if it's empty, use IV from the mediaKey
     * @return string
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
     * Encrypts the given plain text
     *
     * @param string $plainText
     * @return string
     */
    public function encrypt(string $plainText): string
    {
        // Encrypt data
        $enc = openssl_encrypt(
            $plainText,
            self::CIPHER_ALGO,
            $this->cipherKey,
            OPENSSL_RAW_DATA,
            $this->iv
        );

        if ($enc === false) {
            throw new RuntimeException("Encryption failed");
        }

        // Sign `iv + enc` with `macKey` using HMAC
        $hmac = hash_hmac(self::HMAC_ALGO, $this->iv . $enc, $this->macKey, true);
        // Store the first 10 bytes of the hash as `mac`.
        $mac  = substr($hmac, 0, 10);

        // Append `mac` to the `enc` to obtain the result
        $result = $enc . $mac;

        return $result;
    }

    /**
     * Decrypts the given cipher text
     *
     * @param string $cipherText
     * @return string
     */
    public function decrypt(string $cipherText): string
    {
        // Split to `file` and `mac`
        $file = substr($cipherText, 0, -10);
        $mac  = substr($cipherText, -10);

        // Validate HMAC
        $hmac = hash_hmac(self::HMAC_ALGO, $this->iv . $file, $this->macKey, true);
        // Note: `mac` is truncated to 10 bytes, so we need to truncate `hmac` as well
        $calcMac = substr($hmac, 0, 10);

        if (!hash_equals($calcMac, $mac)) {
            throw new RuntimeException("MAC validation failed – file may be corrupted or tampered");
        }

        // Decrypt `file`
        $decrypted = openssl_decrypt(
            $file,
            self::CIPHER_ALGO,
            $this->cipherKey,
            OPENSSL_RAW_DATA,
            $this->iv
        );

        if ($decrypted === false) {
            throw new RuntimeException("Decryption failed");
        }

        return $decrypted;
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
