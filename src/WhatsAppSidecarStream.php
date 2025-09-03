<?php

namespace Pikulsky\EncryptedStreams;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use InvalidArgumentException;
use Pikulsky\EncryptedStreams\Cipher\WhatsAppCipherInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * A read-only stream decorator that generates and writes "sidecar" values
 * while the underlying media stream is being read.
 *
 * Sidecars are short integrity values (HMAC-based) generated per chunk of media data.
 * They are written into a separate stream (`$sidecarStream`) while this stream passes
 * through the original media bytes unmodified.
 *
 * ### Usage
 * - Wrap an encrypted media stream with this class.
 * - Provide a cipher (`WhatsAppCipherInterface`) and a writable sidecar stream.
 * - As the consumer reads from this stream, sidecars are automatically generated and written.
 *
 * ### Behavior
 * - This stream is **read-only**: writing will always throw.
 * - Data is processed in 64KB chunks (`CHUNK_SIZE`).
 * - For the first chunk, the cipher uses the IV from the key.
 * - For the next chunks, the last 16 bytes of the previous chunk are reused as IV.
 *
 * ### Example usage:
 * ```php
 * $encryptingStream = new WhatsAppEncryptingStream($plainStream, $cipher);
 * $sidecarGeneratingStream = new WhatsAppSidecarStream($encryptingStream, $cipher, $sidecarStream);
 *
 * $encryptedContent = (string) $sidecarGeneratingStream; // read encrypted bytes
 * $sidecarContent   = (string) $sidecarStream;           // sidecars written automatically
 * ```
 */
class WhatsAppSidecarStream implements StreamInterface
{
    use StreamDecoratorTrait;

    private const CHUNK_SIZE = 64 * 1024; // 64 KB
    private const EXTRA_SIZE = 16;        // 16-byte overlap between chunks

    /** @var string Internal buffer of partially read data */
    private string $buffer = '';

    /** @var bool Whether the first chunk has been processed */
    private bool $isFirstChunk = true;

    /** @var string Last 16 bytes of the most recent chunk */
    private string $overlap = '';

    /**
     * @param StreamInterface $stream The underlying media stream (must be readable).
     * Same parameter name same as in StreamDecoratorTrait
     * @param WhatsAppCipherInterface $cipher Cipher used to generate sidecars.
     * @param StreamInterface $sidecarStream Target stream for sidecar (must be writable).
     *
     * @throws InvalidArgumentException If the media stream is not readable or sidecar stream not writable.
     */
    public function __construct(
        private readonly StreamInterface $stream,
        private readonly WhatsAppCipherInterface $cipher,
        private readonly StreamInterface $sidecarStream,
    ) {
        if (!$stream->isReadable()) {
            throw new InvalidArgumentException('This stream must be readable');
        }
        if (!$sidecarStream->isWritable()) {
            throw new InvalidArgumentException('Sidecar stream must be writable');
        }
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
     * @throws RuntimeException Always thrown, because the stream is read-only.
     */
    public function write($string): int
    {
        throw new RuntimeException('This stream is read-only.');
    }

    /**
     * Reads data from the underlying media stream.
     *
     * While reading:
     * - Appends data to the internal buffer.
     * - Processes complete 64KB chunks and generates sidecar for them.
     * - Remaining incomplete data stays in the buffer until more is read.
     *
     * @param int $length Maximum number of bytes to read.
     * @return string Raw media data (unchanged).
     */
    public function read(int $length): string
    {
        $read = $this->stream->read($length);

        if ($read === '') {
            $this->flushBuffer();
            return $read;
        }

        $this->buffer .= $read;
        $this->processBuffer();

        return $read;
    }

    /**
     * Determines whether the end of the stream has been reached.
     *
     * This returns true only when:
     * - The underlying stream has reached EOF,
     * - AND the internal buffer has been fully processed.
     *
     * @return bool
     */
    public function eof(): bool
    {
        return $this->buffer === '' && $this->stream->eof();
    }

    /**
     * Processes buffered data in 64KB chunks.
     * For each complete chunk, generates and writes a sidecar.
     */
    private function processBuffer(): void
    {
        while (strlen($this->buffer) >= self::CHUNK_SIZE) {
            $chunk = substr($this->buffer, 0, self::CHUNK_SIZE);

            $this->processChunk($chunk);

            $this->buffer = substr($this->buffer, self::CHUNK_SIZE);
        }
    }

    /**
     * Flushes any remaining data in the buffer.
     *
     * If leftover data exists (smaller than 64KB), it is processed
     * as a final chunk. Larger leftover (>64KB) is currently not expected.
     */
    private function flushBuffer(): void
    {
        if ($this->buffer !== '') {
            // TODO: handle case where buffer > CHUNK_SIZE
            $this->processChunk($this->buffer);
            $this->buffer = '';
        }
    }

    /**
     * Processes a single chunk and generates its sidecar.
     *
     * For the first chunk, the cipher uses its initial IV.
     * For the next chunks, the IV is taken as the last 16 bytes of the previous chunk.
     *
     * @param string $value Chunk of media data.
     */
    private function processChunk(string $value): void
    {
        $iv = $this->isFirstChunk ? '' : $this->overlap;
        $this->isFirstChunk = false;
        $this->overlap = substr($value, -self::EXTRA_SIZE);

        $sidecar = $this->cipher->sidecar($value, $iv);
        $this->sidecarStream->write($sidecar);
    }
}
