<?php

namespace Pikulsky\EncryptedStreams;

use GuzzleHttp\Psr7\Utils;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pikulsky\EncryptedStreams\Cipher\WhatsAppAudioCipher;
use Pikulsky\EncryptedStreams\Cipher\WhatsAppCipherInterface;
use Pikulsky\EncryptedStreams\Cipher\WhatsAppImageCipher;
use Pikulsky\EncryptedStreams\Cipher\WhatsAppVideoCipher;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

class WhatsAppDecryptingStreamTest extends TestCase
{
    use AudioSamplesTrait;
    use ImageSamplesTrait;
    use VideoSamplesTrait;

    #[DataProvider('mediaProvider')]
    public function testDecodingSamples(
        string $keyPath,
        string $encryptedPath,
        string $originalPath,
        string $cipherClass
    ): void {
        // Arrange
        $key = file_get_contents($keyPath);
        $encryptedStream = Utils::streamFor(fopen($encryptedPath, 'r'));
        $originalStream  = Utils::streamFor(fopen($originalPath, 'r'));

        $cipher = new $cipherClass($key);
        $decodingStream = new WhatsAppDecryptingStream($encryptedStream, $cipher);

        // Act
        // Assert
        $this->assertSame((string)$originalStream, (string)$decodingStream);
    }

    /**
     * @return array<array{
     *     keyPath: string,
     *     encryptedPath: string,
     *     originalPath: string,
     *     cipherClass: string,
     *  }>
     */
    public static function mediaProvider(): array
    {
        return [
            'audio' => [
                'keyPath' => self::PATH_AUDIO_KEY,
                'encryptedPath' => self::PATH_AUDIO_ENCRYPTED,
                'originalPath' => self::PATH_AUDIO_ORIGINAL,
                'cipherClass' => WhatsAppAudioCipher::class,
            ],
            'image' => [
                'keyPath' => self::PATH_IMAGE_KEY,
                'encryptedPath' => self::PATH_IMAGE_ENCRYPTED,
                'originalPath' => self::PATH_IMAGE_ORIGINAL,
                'cipherClass' => WhatsAppImageCipher::class,
            ],
            'video' => [
                'keyPath' => self::PATH_VIDEO_KEY,
                'encryptedPath' => self::PATH_VIDEO_ENCRYPTED,
                'originalPath' => self::PATH_VIDEO_ORIGINAL,
                'cipherClass' => WhatsAppVideoCipher::class,
            ],
        ];
    }

    public function testThrowsExceptionIfStreamNotReadable(): void
    {
        // Arrange: create a mock stream that is not readable
        $mockStream = $this->createMock(StreamInterface::class);
        $mockStream->method('isReadable')->willReturn(false);

        $cipherMock = $this->createMock(WhatsAppCipherInterface::class);

        // Assert: expect exception
        $this->expectException(InvalidArgumentException::class);

        // Act
        new WhatsAppDecryptingStream($mockStream, $cipherMock);
    }

    public function testIsWritableReturnsFalse(): void
    {
        // Arrange
        $stream = Utils::streamFor('test data');
        $cipherMock = $this->createMock(WhatsAppCipherInterface::class);
        $decryptingStream = new WhatsAppDecryptingStream($stream, $cipherMock);

        // Act
        // Assert
        $this->assertFalse($decryptingStream->isWritable());
    }

    public function testWriteThrowsRuntimeException(): void
    {
        // Arrange
        $stream = Utils::streamFor('test data');
        $cipherMock = $this->createMock(WhatsAppCipherInterface::class);
        $decryptingStream = new WhatsAppDecryptingStream($stream, $cipherMock);

        // Assert
        $this->expectException(RuntimeException::class);

        // Act
        $decryptingStream->write('some data');
    }
}
