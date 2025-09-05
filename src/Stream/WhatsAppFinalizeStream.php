<?php

namespace Pikulsky\EncryptedStreams\Stream;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Stream wrapper that separates encrypted WhatsApp media data from its MAC.
 *
 * WhatsApp media files store a truncated MAC (10 bytes from HMAC-SHA256)
 * at the very end of the encrypted stream. This decorator ensures that:
 *
 * - Reads only return the encrypted payload (excluding the MAC).
 * - The last 10 bytes are extracted and stored internally as the MAC.
 * - The MAC can be retrieved later via {@see getMac()} for verification.
 *
 * Example usage:
 * ```php
 * $finalizer = new WhatsAppFinalizeStream($encryptedStream);
 * $data = $finalizer->getContents(); // returns encrypted data only
 * $mac  = $finalizer->getMac();      // returns last 10 bytes
 * ```
 */
class WhatsAppFinalizeStream implements StreamInterface
{
    use StreamDecoratorTrait;

    private const MAC_SIZE = 10;

    /**
     * MAC extracted from the end of the stream.
     *
     * @var string
     */
    private string $mac = '';

    /**
     * Internal buffer used to hold data until the MAC is extracted.
     *
     * @var string
     */
    private string $buffer = '';

    /**
     * Whether the MAC has already been extracted.
     *
     * @var bool
     */
    private bool $macExtracted = false;

    public function __construct(
        private readonly StreamInterface $stream
    ) {
    }

    /**
     * Returns the MAC (last 10 bytes) from the end of the stream.
     *
     * If the MAC has not been extracted yet, it will be extracted the
     * first time this method is called when the stream has reached EOF.
     *
     * @return string
     */
    public function getMac(): string
    {
        if (!$this->macExtracted && $this->stream->eof()) {
            $this->extractMac();
        }
        return $this->mac;
    }

    /**
     * Checks if the stream has reached EOF (excluding the MAC).
     *
     * @return bool True if all encrypted data has been read.
     */
    public function eof(): bool
    {
        return $this->stream->eof() && $this->buffer === '' && $this->macExtracted;
    }

    /**
     * Reads encrypted data from the underlying stream, excluding the MAC.
     *
     * @param int $length Number of bytes to read.
     * @return string Encrypted data (never includes the MAC).
     *
     * @throws RuntimeException If the stream is too small to contain both data and MAC.
     */
    public function read($length): string
    {
        if ($length <= 0) {
            return '';
        }

        // Fill the buffer if needed
        $this->fillBuffer($length);

        if ($this->stream->eof() && !$this->macExtracted) {
            $this->extractMac();
        }

        $result = substr($this->buffer, 0, $length);
        $this->buffer = substr($this->buffer, $length);

        return $result;
    }

    /**
     * Fills the internal buffer with data from the underlying stream.
     *
     * Keeps at least {@see MAC_SIZE} bytes of lookahead to ensure the
     * final MAC can be separated from encrypted data.
     *
     * @param int $requestedLength Number of bytes the caller wants to read.
     *
     * @return void
     */
    private function fillBuffer(int $requestedLength): void
    {
        // Read the required length and extra for MAC
        if (strlen($this->buffer) < $requestedLength && !$this->stream->eof()) {
            $toRead = $requestedLength - strlen($this->buffer) + self::MAC_SIZE;
            $this->buffer .= $this->stream->read($toRead);
        }

        // Read one more time from the stream to check for EOF
        if (!$this->stream->eof()) {
            $this->buffer .= $this->stream->read(self::MAC_SIZE);
        }
    }

    /**
     * Extracts the MAC from the buffer and marks the stream as finalized.
     *
     * @return void
     * @throws RuntimeException If there is not enough data to extract the MAC.
     */
    private function extractMac(): void
    {
        $bufferLength = strlen($this->buffer);

        if ($bufferLength >= self::MAC_SIZE) {
            $this->mac = substr($this->buffer, -self::MAC_SIZE);
            $this->buffer = substr($this->buffer, 0, -self::MAC_SIZE);
            $this->macExtracted = true;
            return;
        }

        // If the buffer is too small, throw an exception
        if ($bufferLength > 0) {
            throw new RuntimeException("Final chunk is too small to contain encrypted data and MAC");
        }

        // If the buffer is empty, the MAC is empty
        $this->macExtracted = true;
    }

    /**
     * Resets the stream position (if supported) and clears internal state.
     *
     * @return void
     */
    public function rewind(): void
    {
        $this->stream->rewind();
        $this->buffer = '';
        $this->mac = '';
        $this->macExtracted = false;
    }
}
