<?php

namespace Pikulsky\EncryptedStreams;

use InvalidArgumentException;
use Jsq\EncryptionStreams\AesDecryptingStream;
use Jsq\EncryptionStreams\Cbc;
use Jsq\EncryptionStreams\HashingStream;
use Pikulsky\EncryptedStreams\Cipher\WhatsAppCipherInterface;
use Pikulsky\EncryptedStreams\Stream\WhatsAppFinalizeStream;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Stream wrapper that decrypts WhatsApp media data on-the-fly.
 *
 * This class decorates an encrypted {@see StreamInterface} and uses a
 * {@see WhatsAppCipherInterface} implementation to automatically decrypt
 * any data read from the stream. Integrity is verified via HMAC-SHA256,
 * where only the first 10 bytes of the hash are used as the MAC.
 *
 * Decryption and MAC verification are performed per chunk, so large
 * streams can be processed incrementally without loading the entire
 * content into memory.
 *
 * Example usage:
 * ```php
 * $decryptedStream = new WhatsAppDecryptingStream($encryptedStream, $cipher);
 * $data = $decryptedStream->read(1024); // reads and decrypts 1024 bytes
 * ```
 */
class WhatsAppDecryptingStream extends AesDecryptingStream implements StreamInterface
{
    private const MAC_SIZE = 10;

    private WhatsAppFinalizeStream $finalizer;

    /**
     * Creates a decrypting stream wrapper.
     *
     * @param StreamInterface $cipherStream Encrypted WhatsApp media stream (must be readable).
     * @param WhatsAppCipherInterface $cipher Cipher providing encryption key, IV and MAC key.
     *
     * @throws InvalidArgumentException If the provided stream is not readable.
     */
    public function __construct(
        StreamInterface $cipherStream,
        WhatsAppCipherInterface $cipher,
    ) {
        if (!$cipherStream->isReadable()) {
            throw new InvalidArgumentException('This stream must be readable');
        }

        $key = $cipher->getKey();
        $macKey = $cipher->getMacKey();
        $iv = $cipher->getIV();
        $cipherMethod = new Cbc($iv);

        // Finalizer splits the last chunk into encrypted data and MAC.
        $this->finalizer = new WhatsAppFinalizeStream($cipherStream);

        // Computes HMAC-SHA256 while reading, then calls validateMac() at the end.
        $hashStream = new HashingStream(
            $this->finalizer,
            $iv,
            $macKey,
            [$this, 'validateMac']
        );

        parent::__construct($hashStream, $key, $cipherMethod);
    }

    /**
     * Callback executed by {@see HashingStream} to verify the MAC.
     *
     * @param string $hmac Full 32-byte HMAC-SHA256 calculated from stream chunks.
     * @return void
     * @throws RuntimeException If the MAC from the stream does not match the calculated value.
     */
    public function validateMac(string $hmac): void
    {
        // Take the first 10 bytes of the HMAC as the expected MAC.
        $macCalculated  = substr($hmac, 0, self::MAC_SIZE);

        // Extract the MAC from the end of the encrypted stream.
        $macFromFile = $this->finalizer->getMac();

        // Order of parameters is important:
        // $macFromFile - expected (from file), known correct
        // $macCalculated - computed during streaming
        if (!hash_equals($macFromFile, $macCalculated)) {
            throw new RuntimeException("MAC verification failed");
        }
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
