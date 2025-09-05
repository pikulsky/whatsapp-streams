<?php

namespace Pikulsky\EncryptedStreams\Cipher;

/**
 * Contract for key material.
 *
 * Implementations of this interface provide the cryptographic
 * primitives (AES key, IV, MAC key) and define how to generate a
 * "sidecar" — a piece of metadata required for integrity verification
 * and decryption.
 *
 * Typical usage:
 * - {@see getKey()} provides the AES-256 encryption/decryption key.
 * - {@see getIV()} provides the initialization vector for CBC mode.
 * - {@see getMacKey()} provides the HMAC-SHA256 key used for integrity.
 * - {@see sidecar()} generates verification data for a given payload.
 */
interface WhatsAppCipherInterface
{
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
    public function sidecar(string $payload, string $iv): string;

    /**
     * Returns the AES key used for encryption and decryption.
     *
     * @return string Binary key material.
     */
    public function getKey(): string;

    /**
     * Returns the initialization vector (IV) used for encryption.
     *
     * @return string Binary IV value.
     */
    public function getIV(): string;

    /**
     * Returns the key used for message authentication (HMAC-SHA256).
     *
     * @return string Binary MAC key.
     */
    public function getMacKey(): string;
}
