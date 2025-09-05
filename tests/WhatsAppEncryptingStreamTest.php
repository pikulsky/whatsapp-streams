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

class WhatsAppEncryptingStreamTest extends TestCase
{
    use AudioSamplesTrait;
    use ImageSamplesTrait;
    use VideoSamplesTrait;

    #[DataProvider('mediaProvider')]
    public function testEncodingSamples(
        string $keyPath,
        string $originalPath,
        string $encryptedPath,
        string $cipherClass
    ): void {
        $key = file_get_contents($keyPath);
        $expectedEncryptedContent = file_get_contents($encryptedPath);
        $originalStream = Utils::streamFor(fopen($originalPath, 'r'));

        $cipher = new $cipherClass($key);
        $encryptingStream = new WhatsAppEncryptingStream($originalStream, $cipher);

        // Act
        $encryptedContent = (string)$encryptingStream;

        // Assert
        $this->assertEquals(strlen($expectedEncryptedContent), strlen($encryptedContent));
        $this->assertSame(bin2hex($expectedEncryptedContent), bin2hex($encryptedContent));
    }

    /**
     * @return array<array{
     *     keyPath: string,
     *     originalPath: string,
     *     encryptedPath: string,
     *     cipherClass: string,
     *  }>
     */
    public static function mediaProvider(): array
    {
        return [
            'audio' => [
                'keyPath' => self::PATH_AUDIO_KEY,
                'originalPath' => self::PATH_AUDIO_ORIGINAL,
                'encryptedPath' => self::PATH_AUDIO_ENCRYPTED,
                'cipherClass' => WhatsAppAudioCipher::class,
            ],
            'image' => [
                'keyPath' => self::PATH_IMAGE_KEY,
                'originalPath' => self::PATH_IMAGE_ORIGINAL,
                'encryptedPath' => self::PATH_IMAGE_ENCRYPTED,
                'cipherClass' => WhatsAppImageCipher::class,
            ],
            'video' => [
                'keyPath' => self::PATH_VIDEO_KEY,
                'originalPath' => self::PATH_VIDEO_ORIGINAL,
                'encryptedPath' => self::PATH_VIDEO_ENCRYPTED,
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
        new WhatsAppEncryptingStream($mockStream, $cipherMock);
    }

    public function testIsWritableReturnsFalse(): void
    {
        $stream = Utils::streamFor('test data');

        $cipherStub = $this->createStub(WhatsAppCipherInterface::class);
        $cipherStub->method('getIV')->willReturn(random_bytes(16));
        $cipherStub->method('getMacKey')->willReturn(random_bytes(32));
        $encryptingStream = new WhatsAppEncryptingStream($stream, $cipherStub);

        $this->assertFalse($encryptingStream->isWritable());
    }

    public function testWriteThrowsRuntimeException(): void
    {
        $stream = Utils::streamFor('test data');

        $cipherStub = $this->createStub(WhatsAppCipherInterface::class);
        $cipherStub->method('getIV')->willReturn(random_bytes(16));
        $cipherStub->method('getMacKey')->willReturn(random_bytes(32));

        $encryptingStream = new WhatsAppEncryptingStream($stream, $cipherStub);

        $this->expectException(RuntimeException::class);

        $encryptingStream->write('some data');
    }
}
