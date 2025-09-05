<?php

namespace Pikulsky\EncryptedStreams;

use GuzzleHttp\Psr7\Utils;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Pikulsky\EncryptedStreams\Cipher\WhatsAppCipherInterface;
use Pikulsky\EncryptedStreams\Cipher\WhatsAppVideoCipher;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

class WhatsAppSidecarStreamTest extends TestCase
{
    use VideoSamplesTrait;

    private const PATH_VIDEO_SIDECAR = __DIR__ . '/samples/VIDEO.sidecar';

    public function testCreateSidecarVideo(): void
    {
        // Arrange
        $key = file_get_contents(self::PATH_VIDEO_KEY);
        $sidecarExpectedContent = file_get_contents(self::PATH_VIDEO_SIDECAR);

        $plainTextStream = Utils::streamFor(fopen(self::PATH_VIDEO_ORIGINAL, 'r'));
        $expectedEncryptedContent = file_get_contents(self::PATH_VIDEO_ENCRYPTED);
        $sidecarStream = Utils::streamFor('');

        $videoCipher = new WhatsAppVideoCipher($key);

        $encodingStream = new WhatsAppEncryptingStream($plainTextStream, $videoCipher);
        $encryptedStream = new WhatsAppSidecarStream($encodingStream, $videoCipher, $sidecarStream);

        // Act
        $encryptedStreamContent = (string) $encryptedStream;
        $sidecarContent = (string) $sidecarStream;

        // Assert encrypted stream content is as expected
        $this->assertNotEmpty($encryptedStreamContent);
        $this->assertEquals(strlen($expectedEncryptedContent), strlen($encryptedStreamContent));
        $this->assertSame($expectedEncryptedContent, $encryptedStreamContent);

        // Assert sidecar content is as expected
        $this->assertEquals(strlen($sidecarExpectedContent), strlen($sidecarContent));
        $this->assertSame(bin2hex($sidecarExpectedContent), bin2hex($sidecarContent));
    }

    public function testThrowsExceptionIfInputStreamNotReadable(): void
    {
        // Arrange: create a mock stream that is not readable
        $mockStream = $this->createMock(StreamInterface::class);
        $mockStream->method('isReadable')->willReturn(false);

        $cipherMock = $this->createMock(WhatsAppCipherInterface::class);
        $sidecarStream = Utils::streamFor('');

        // Assert: expect exception
        $this->expectException(InvalidArgumentException::class);

        // Act
        new WhatsAppSidecarStream($mockStream, $cipherMock, $sidecarStream);
    }

    public function testThrowsExceptionIfSidecarStreamNotWritable(): void
    {
        // Arrange: create a sidecar stream that is not writable
        $mockSidecarStream = $this->createMock(StreamInterface::class);
        $mockSidecarStream->method('isWritable')->willReturn(false);

        $inputStream = Utils::streamFor('test data');
        $cipherMock = $this->createMock(WhatsAppCipherInterface::class);

        // Assert: expect exception
        $this->expectException(InvalidArgumentException::class);

        // Act
        new WhatsAppSidecarStream($inputStream, $cipherMock, $mockSidecarStream);
    }

    public function testInputStreamIsWritableReturnsFalse(): void
    {
        // Arrange
        $inputStream = Utils::streamFor('test data');
        $cipherMock = $this->createMock(WhatsAppCipherInterface::class);
        $sidecarStream = Utils::streamFor('');
        $decoratorStream = new WhatsAppSidecarStream($inputStream, $cipherMock, $sidecarStream);

        // Act
        // Assert
        $this->assertFalse($decoratorStream->isWritable());
    }

    public function testInputStreamWriteThrowsRuntimeException(): void
    {
        // Arrange
        $inputStream = Utils::streamFor('test data');
        $cipherMock = $this->createMock(WhatsAppCipherInterface::class);
        $sidecarStream = Utils::streamFor('');
        $decoratorStream = new WhatsAppSidecarStream($inputStream, $cipherMock, $sidecarStream);

        // Assert
        $this->expectException(RuntimeException::class);

        // Act
        $decoratorStream->write('some data');
    }
}
