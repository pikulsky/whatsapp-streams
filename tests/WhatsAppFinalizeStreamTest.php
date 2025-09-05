<?php

namespace Pikulsky\EncryptedStreams;

use GuzzleHttp\Psr7\Stream;
use PHPUnit\Framework\TestCase;
use Pikulsky\EncryptedStreams\Stream\WhatsAppFinalizeStream;
use RuntimeException;

class WhatsAppFinalizeStreamTest extends TestCase
{
    /**
     * Helper method to create a stream from string
     */
    private function createStream(string $content): Stream
    {
        $resource = fopen('php://memory', 'r+');
        fwrite($resource, $content);
        rewind($resource);
        return new Stream($resource);
    }

    /**
     * Test basic functionality with data larger than MAC size
     */
    public function testBasicFunctionality(): void
    {
        $data = 'Hello, World!';
        $mac = '1234567890';
        $fullContent = $data . $mac;

        $innerStream = $this->createStream($fullContent);
        $stream = new WhatsAppFinalizeStream($innerStream);

        $result = $stream->getContents();

        $this->assertEquals($data, $result);
        $this->assertEquals($mac, $stream->getMac());
        $this->assertTrue($stream->eof());
    }

    /**
     * Test reading in small chunks
     */
    public function testReadInSmallChunks(): void
    {
        $data = 'This is a longer test message for chunked reading';
        $mac = 'ABCDEFGHIJ';
        $fullContent = $data . $mac;

        $innerStream = $this->createStream($fullContent);
        $stream = new WhatsAppFinalizeStream($innerStream);

        $result = '';
        while (!$stream->eof()) {
            $chunk = $stream->read(5);
            $result .= $chunk;
        }

        $this->assertEquals($data, $result);
        $this->assertEquals($mac, $stream->getMac());
    }

    /**
     * Test reading in large chunks
     */
    public function testReadInLargeChunks(): void
    {
        $data = 'Short data';
        $mac = 'KLMNOPQRST';
        $fullContent = $data . $mac;

        $innerStream = $this->createStream($fullContent);
        $stream = new WhatsAppFinalizeStream($innerStream);

        // Request more data than available
        $result = $stream->read(1000);

        $this->assertEquals($data, $result);
        $this->assertEquals($mac, $stream->getMac());
        $this->assertTrue($stream->eof());
    }

    /**
     * Test with exactly MAC_SIZE bytes
     */
    public function testExactlyMacSizeBytes(): void
    {
        $mac = '1234567890';

        $innerStream = $this->createStream($mac);
        $stream = new WhatsAppFinalizeStream($innerStream);

        $result = $stream->getContents();

        $this->assertEquals('', $result);
        $this->assertEquals($mac, $stream->getMac());
        $this->assertTrue($stream->eof());
    }

    /**
     * Test with less than MAC_SIZE bytes
     */
    public function testLessThanMacSizeBytes(): void
    {
        $data = 'tiny';

        $innerStream = $this->createStream($data);
        $stream = new WhatsAppFinalizeStream($innerStream);

        // Assert: expect exception
        $this->expectException(RuntimeException::class);

        $stream->getContents();
    }

    /**
     * Test with an empty stream
     */
    public function testEmptyStream(): void
    {
        $innerStream = $this->createStream('');
        $stream = new WhatsAppFinalizeStream($innerStream);

        $result = $stream->getContents();

        $this->assertEquals('', $result);
        $this->assertEquals('', $stream->getMac());
        $this->assertTrue($stream->eof());
    }

    /**
     * Test multiple reads with exact boundaries
     */
    public function testMultipleReadsExactBoundaries(): void
    {
        $data = 'AAAABBBBCCCCDDDD'; // 16 bytes
        $mac = '0987654321'; // 10 bytes
        $fullContent = $data . $mac;

        $innerStream = $this->createStream($fullContent);
        $stream = new WhatsAppFinalizeStream($innerStream);

        $result1 = $stream->read(4);  // Read "AAAA"
        $result2 = $stream->read(4);  // Read "BBBB"
        $result3 = $stream->read(4);  // Read "CCCC"
        $result4 = $stream->read(4);  // Read "DDDD"
        $result5 = $stream->read(4);  // Should return empty

        $this->assertEquals('AAAA', $result1);
        $this->assertEquals('BBBB', $result2);
        $this->assertEquals('CCCC', $result3);
        $this->assertEquals('DDDD', $result4);
        $this->assertEquals('', $result5);
        $this->assertEquals($mac, $stream->getMac());
        $this->assertTrue($stream->eof());
    }

    /**
     * Test that MAC is not returned even when requesting more data
     */
    public function testMacNotReturnedWhenRequestingMore(): void
    {
        $data = 'Test';
        $mac = 'SECRETMAC!';
        $fullContent = $data . $mac;

        $innerStream = $this->createStream($fullContent);
        $stream = new WhatsAppFinalizeStream($innerStream);

        // Request exact data size
        $result1 = $stream->read(4);
        $this->assertEquals($data, $result1);

        // Request more data - should get empty string, not MAC
        $result2 = $stream->read(100);
        $this->assertEquals('', $result2);

        $this->assertEquals($mac, $stream->getMac());
        $this->assertTrue($stream->eof());
    }

    /**
     * Test rewind functionality
     */
    public function testRewind(): void
    {
        $data = 'Rewindable content here';
        $mac = 'MAC123456!';
        $fullContent = $data . $mac;

        $innerStream = $this->createStream($fullContent);
        $stream = new WhatsAppFinalizeStream($innerStream);

        // First read
        $result1 = $stream->getContents();
        $mac1 = $stream->getMac();

        // Rewind and read again
        $stream->rewind();
        $result2 = $stream->getContents();
        $mac2 = $stream->getMac();

        $this->assertEquals($result1, $result2);
        $this->assertEquals($mac1, $mac2);
        $this->assertEquals($data, $result1);
        $this->assertEquals($mac, $mac1);
    }

    /**
     * Test reading byte by byte
     */
    public function testByteByByteReading(): void
    {
        $data = 'ABC';
        $mac = 'XYZ1234567';
        $fullContent = $data . $mac;

        $innerStream = $this->createStream($fullContent);
        $stream = new WhatsAppFinalizeStream($innerStream);

        $result = '';
        while (!$stream->eof()) {
            $byte = $stream->read(1);
            if ($byte === '') {
                break;
            }
            $result .= $byte;
        }

        $this->assertEquals($data, $result);
        $this->assertEquals($mac, $stream->getMac());
    }

    /**
     * Test with binary data
     */
    public function testBinaryData(): void
    {
        $data = pack('H*', 'deadbeefcafe');
        $mac = pack('H*', '00112233445566778899');
        $fullContent = $data . $mac;

        $innerStream = $this->createStream($fullContent);
        $stream = new WhatsAppFinalizeStream($innerStream);

        $result = $stream->getContents();

        $this->assertEquals($data, $result);
        $this->assertEquals($mac, $stream->getMac());
    }

    /**
     * Test EOF behavior before reading everything
     */
    public function testEofBeforeFullRead(): void
    {
        $data = 'Some test data here';
        $mac = 'ENDMAC1234';
        $fullContent = $data . $mac;

        $innerStream = $this->createStream($fullContent);
        $stream = new WhatsAppFinalizeStream($innerStream);

        // Read only part of the data
        $stream->read(5);

        // Should not be EOF yet
        $this->assertFalse($stream->eof());

        // Read the rest
        $stream->getContents();

        // Now should be EOF
        $this->assertTrue($stream->eof());
        $this->assertEquals($mac, $stream->getMac());
    }
}
