<?php

namespace Pikulsky\EncryptedStreams;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use InvalidArgumentException;
use Pikulsky\EncryptedStreams\Cipher\WhatsAppCipherInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Stream wrapper that decrypts WhatsApp media data on-the-fly.
 *
 * This class decorates an existing {@see StreamInterface} and uses a
 * {@see WhatsAppCipherInterface} implementation to automatically decrypt
 * any data read from the stream.
 *
 * Note: Decryption happens per chunk, so large streams can be processed
 * incrementally without loading the entire content into memory.
 *
 * Example usage:
 * ```php
 * $decryptedStream = new WhatsAppDecryptingStream($encryptedStream, $cipher);
 * $data = $decryptedStream->read(1024); // reads and decrypts 1024 bytes
 * ```
 */

class WhatsAppDecryptingStream implements StreamInterface
{
    use StreamDecoratorTrait;

    /**
     * @param StreamInterface $stream note: parameter name should be the same as in StreamDecoratorTrait
     * @param WhatsAppCipherInterface $cipher
     */
    public function __construct(
        private readonly StreamInterface $stream,
        private readonly WhatsAppCipherInterface $cipher,
    ) {
        if (!$stream->isReadable()) {
            throw new InvalidArgumentException('This stream must be readable');
        }
    }

    /**
     * Reads from the underlying stream and decrypts the data.
     *
     * The decryption happens per chunk, allowing large streams to be processed
     * incrementally without loading the entire content into memory.
     *
     * @param int $length Number of bytes to read
     * @return string Decrypted data
     */
    public function read(int $length): string
    {
        $read = $this->stream->read($length);

        if (strlen($read) > 0) {
            return $this->cipher->decrypt($read);
        }

        return $read;
    }

    /**
     * This stream is read-only.
     *
     * @return bool
     */
    public function isWritable(): bool
    {
        return false;
    }

    /**
     * Writing is not supported for this stream.
     *
     * @param string $string
     * @throws RuntimeException
     */
    public function write($string): int
    {
        throw new RuntimeException('This stream is read-only.');
    }
}
