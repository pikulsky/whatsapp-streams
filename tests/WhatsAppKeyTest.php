<?php

namespace Pikulsky\EncryptedStreams;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;
use Pikulsky\EncryptedStreams\Key\AudioKey;
use Pikulsky\EncryptedStreams\Key\VideoKey;
use Pikulsky\EncryptedStreams\Key\ImageKey;

class WhatsAppKeyTest extends TestCase
{
    #[DataProvider('provideValidLengthKeys')]
    public function testValidKeyLength(string $class, int $length): void
    {
        $key = random_bytes($length);
        $obj = new $class($key);

        $this->assertInstanceOf($class, $obj);
    }

    #[DataProvider('provideInvalidLengthKeys')]
    public function testInvalidKeyLengthThrowsException(string $class, int $length): void
    {
        $this->expectException(InvalidArgumentException::class);

        $key = random_bytes($length);
        new $class($key);
    }

    /**
     * @return array<array{0: string, 1: int}>
     */
    public static function provideValidLengthKeys(): array
    {
        return [
            [AudioKey::class, 32],
            [VideoKey::class, 32],
            [ImageKey::class, 32],
        ];
    }

    /**
     * @return array<array{0: string, 1: int}>
     */
    public static function provideInvalidLengthKeys(): array
    {
        return [
            [AudioKey::class, 31],
            [AudioKey::class, 33],
            [VideoKey::class, 31],
            [VideoKey::class, 33],
            [ImageKey::class, 31],
            [ImageKey::class, 33],
        ];
    }
}
