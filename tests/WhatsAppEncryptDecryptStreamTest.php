<?php

namespace Pikulsky\EncryptedStreams;

use GuzzleHttp\Psr7\Stream;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pikulsky\EncryptedStreams\Cipher\WhatsAppAudioCipher;
use Pikulsky\EncryptedStreams\Cipher\WhatsAppImageCipher;
use Pikulsky\EncryptedStreams\Cipher\WhatsAppVideoCipher;
use Psr\Http\Message\StreamInterface;

class WhatsAppEncryptDecryptStreamTest extends TestCase
{
    private const MB = 1024 * 1024;
    private const KEY_LENGTH = 32;

    #[DataProvider('cipherProvider')]
    public function testEncryptDecrypt(string $cipherClass): void
    {
        // Arrange
        $key = random_bytes(self::KEY_LENGTH);
        $originalContent = random_bytes(2 * self::MB);
        $original = $this->createStreamFromString($originalContent);

        $cipher = new $cipherClass($key);
        $encrypted = new WhatsAppEncryptingStream($original, $cipher);

        // Act
        $encryptedContent = (string) $encrypted;

        // Assert
        $this->assertNotSame($originalContent, $encryptedContent);
        $this->assertEquals(2 * self::MB, strlen($originalContent));

        // Arrange
        $encrypted = $this->createStreamFromString($encryptedContent);
        $decrypted = new WhatsAppDecryptingStream($encrypted, $cipher);

        // Act
        $decryptedContent = (string) $decrypted;
        // Assert
        $this->assertSame($originalContent, $decryptedContent);
    }

    #[DataProvider('cipherProvider')]
    public function testVariousStreamSizes(string $cipherClass): void
    {
        $sizes = [
            0 => 'Empty stream',
            1 => '1 byte',
            15 => 'Smaller than block size',
            16 => 'Exactly block size',
            17 => 'Just over block size',
            1024 => '1KB',
            64 * 1024 => '64KB',
            1024 * 1024 => '1MB',
            2 * 1024 * 1024 => '2MB',
            3 * 1024 * 1024 + 1 => '3MB+1B',
            24 * 1024 * 1024 + 10 => '24MB + 10B',
        ];

        $key = random_bytes(self::KEY_LENGTH);

        foreach ($sizes as $size => $sizeDescription) {
            $plaintext = $size > 0 ? random_bytes($size) : '';

            $stream = $this->createStreamFromString($plaintext);

            $cipher = new $cipherClass($key);
            $encryptedStream = new WhatsAppEncryptingStream($stream, $cipher);

            // Act
            $encryptedContent = $encryptedStream->getContents();

            // Arrange
            $stream = $this->createStreamFromString($encryptedContent);
            $decryptedStream = new WhatsAppDecryptingStream($stream, $cipher);

            // Act
            $decryptedContent = $decryptedStream->getContents();

            // Assert
            $this->assertSame($plaintext, $decryptedContent, "Decrypted content mismatch for $sizeDescription");
        }
    }

    /**
     * @return array{
     *     audio: array{string},
     *     image: array{string},
     *     video: array{string},
     *  }
     */
    public static function cipherProvider(): array
    {
        return [
            'audio' => [WhatsAppAudioCipher::class],
            'image' => [WhatsAppImageCipher::class],
            'video' => [WhatsAppVideoCipher::class],
        ];
    }

    private function createStreamFromString(string $content): StreamInterface
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $content);
        rewind($stream);
        return new Stream($stream);
    }
}
