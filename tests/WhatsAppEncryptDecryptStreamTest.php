<?php

namespace Pikulsky\EncryptedStreams;

use GuzzleHttp\Psr7\PumpStream;
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

    private function getRandomBytesStream(int $maxLength): StreamInterface
    {
        $stream = new PumpStream(function ($length) use (&$maxLength) {
            $length = min($length, $maxLength);
            $maxLength -= $length;
            return $length > 0 ? random_bytes($length) : false;
        });
        return $stream;
    }

    #[DataProvider('cipherProvider')]
    public function testEncryptDecrypt(string $cipherClass): void
    {
        // Arrange
        $key = random_bytes(self::KEY_LENGTH);
        $original = $this->getRandomBytesStream(2 * self::MB);

        $cipher = new $cipherClass($key);
        $encrypted = new WhatsAppEncryptingStream($original, $cipher);

        // Act
        // Assert
        $this->assertNotSame((string) $original, (string) $encrypted);

        // Arrange
        $decrypted = new WhatsAppDecryptingStream($encrypted, $cipher);

        // Act
        // Assert
        $this->assertSame((string) $original, (string) $decrypted);
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
}
