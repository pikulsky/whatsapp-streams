<?php

namespace Pikulsky\EncryptedStreams\Cipher;

/**
 * Defines the contract for WhatsApp media encryption and decryption.
 *
 * Implementations of this interface handle the encryption and decryption
 * of media payloads, and can optionally produce a sidecar for verification.
 */
interface WhatsAppCipherInterface
{
    /**
     * Encrypts the given plaintext payload.
     *
     * @param string $plainText The plaintext media data
     * @return string The encrypted data
     */
    public function encrypt(string $plainText): string;

    /**
     * Decrypts the given ciphertext payload.
     *
     * @param string $cipherText The encrypted media data
     * @return string The decrypted plaintext
     */
    public function decrypt(string $cipherText): string;

    /**
     * Generates a sidecar from the given payload.
     *
     * A sidecar typically contains metadata or verification data needed
     * for decryption or integrity checks.
     *
     * @param string $payload The media data (plaintext or ciphertext)
     * @param string $iv IV: if it's empty, use IV from the mediaKey
     * @return string The sidecar data
     */
    public function sidecar(string $payload, string $iv): string;
}
