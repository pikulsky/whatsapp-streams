<?php

namespace Pikulsky\EncryptedStreams;

use InvalidArgumentException;
use Jsq\EncryptionStreams\AesEncryptingStream;
use Jsq\EncryptionStreams\Cbc;
use Jsq\EncryptionStreams\HashingStream;
use Pikulsky\EncryptedStreams\Cipher\WhatsAppCipherInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Stream wrapper that encrypts WhatsApp media data on-the-fly while appending a MAC.
 *
 * This class decorates a plaintext {@see StreamInterface} and uses a
 * {@see WhatsAppCipherInterface} implementation to automatically encrypt
 * any data read from the stream. At the end of the stream, a truncated
 * MAC (first 10 bytes of an HMAC-SHA256) is appended for integrity
 * verification during decryption.
 *
 * Encryption is performed per chunk, so large streams can be processed
 * incrementally without loading the entire file into memory.
 *
 * Example usage:
 * ```php
 * $encryptingStream = new WhatsAppEncryptingStream($plainStream, $cipher);
 * $data = $encryptingStream->read(1024); // reads and encrypts 1024 bytes
 * ```
 */
class WhatsAppEncryptingStream extends HashingStream implements StreamInterface
{
    private const MAC_SIZE = 10;

    /**
     * Creates an encrypting stream wrapper.
     *
     * @param StreamInterface $plainTextStream Plaintext media stream (must be readable).
     * @param WhatsAppCipherInterface $cipher Cipher providing encryption key, IV and MAC key.
     *
     * @throws InvalidArgumentException If the provided stream is not readable.
     */
    public function __construct(
        StreamInterface $plainTextStream,
        WhatsAppCipherInterface $cipher,
    ) {
        if (!$plainTextStream->isReadable()) {
            throw new InvalidArgumentException('This stream must be readable');
        }

        $key = $cipher->getKey();
        $macKey = $cipher->getMacKey();
        $iv = $cipher->getIV();
        $cipherMethod = new Cbc($iv);

        // wrap the plain text stream in an AES stream to encrypt it
        $encodingStream = new AesEncryptingStream($plainTextStream, $key, $cipherMethod);

        parent::__construct($encodingStream, $iv, $macKey);
    }

    /**
     * Reads data from the underlying stream and encrypts it.
     *
     * At the end of the stream, the first 10 bytes of the calculated
     * HMAC-SHA256 are appended as the MAC.
     *
     * @param int $length Number of bytes to read.
     * @return string Encrypted data (last chunk may include the MAC).
     */
    public function read($length): string
    {
        $read = parent::read($length);

        if ($this->eof()) {
            // Get the calculated final HMAC
            $hmac = $this->getHash();
            // Append the first 10 bytes of the HMAC as MAC
            $mac  = substr($hmac, 0, self::MAC_SIZE);
            $read .= $mac;
        }

        return $read;
    }

    /**
     * Writing is not supported for this stream.
     *
     * @param string $string Ignored data.
     * @return int
     * @throws RuntimeException Always thrown, because this stream is read-only.
     */
    public function write($string): int
    {
        throw new RuntimeException('This stream is read-only.');
    }
}
