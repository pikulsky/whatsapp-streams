<?php

namespace Pikulsky\EncryptedStreams;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use InvalidArgumentException;
use Pikulsky\EncryptedStreams\Cipher\WhatsAppCipherInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Stream wrapper that encrypts WhatsApp media data on-the-fly.
 *
 * This class decorates an existing {@see StreamInterface} and uses a
 * {@see WhatsAppCipherInterface} implementation to automatically encrypt
 * any data read from the underlying stream.
 *
 * Note: Encryption happens per chunk, so large streams can be processed
 * incrementally without loading the entire content into memory.
 *
 * Example usage:
 * ```php
 * $encryptingStream = new WhatsAppEncryptingStream($plainStream, $cipher);
 * $data = $encryptingStream->read(1024); // reads and encrypts 1024 bytes
 * ```
 */
class WhatsAppEncryptingStream implements StreamInterface
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
     * Reads from the underlying stream and encrypts the data.
     *
     * The encryption happens per chunk, allowing large streams to be processed
     * incrementally without loading the entire content into memory.
     *
     * @param int $length Number of bytes to read
     * @return string Encrypted data
     */
    public function read(int $length): string
    {
        $read = $this->stream->read($length);

        if (strlen($read) > 0) {
            return $this->cipher->encrypt($read);
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
