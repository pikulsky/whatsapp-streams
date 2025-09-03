# PSR-7 Encrypting/Decrypting Decorator Streams for WhatsApp

This package provides PSR-7 compatible stream decorators that allow
incremental encryption and decryption of streams of arbitrary size.  
It also supports creating sidecar streams.

## Installation

Install the package via Composer:

```bash
composer require pikulsky/whatsapp-streams
```

## Usage

### Encrypting a stream

Wrap any `StreamInterface` with `WhatsAppEncryptingStream` to encrypt data as it's being read:

```php
use GuzzleHttp\Psr7\Utils;

$key = 'some-secret-key-here-32-bytes-long';
$plainStream = Utils::streamFor(fopen('input.file', 'r'));

$cipher = new WhatsAppAudioCipher($key);
$encryptingStream = new WhatsAppEncryptingStream($plainStream, $cipher);

$outputStream = Utils::streamFor(fopen('encrypted.file', 'w'));
Utils::copyToStream($encryptingStream, $outputStream);
```

No encryption is performed until you read from the encrypting stream.

---

### Decrypting a stream

To decrypt, wrap the encrypted stream with `WhatsAppDecryptingStream`:

```php
use GuzzleHttp\Psr7\Utils;

$key = 'some-secret-key-here-32-bytes-long';
$encryptedStream = Utils::streamFor(fopen('encrypted.file', 'r'));

$cipher = new WhatsAppAudioCipher($key);
$decryptingStream = new WhatsAppDecryptingStream($encryptedStream, $cipher);

$outputStream = Utils::streamFor(fopen('decrypted.file', 'w'));
Utils::copyToStream($decryptingStream, $outputStream);
```

---

### Sidecar

A sidecar stream is used to generate additional authentication data alongside encrypted media.
To produce a sidecar, wrap the encrypting stream with `WhatsAppSidecarStream` and pass a writable stream for the sidecar output:

```php
use GuzzleHttp\Psr7\Utils;

$key = 'some-secret-key-here-32-bytes-long';
$plainStream = Utils::streamFor(fopen('input.file', 'r'));

$cipher = new WhatsAppVideoCipher($key);
$encryptingStream = new WhatsAppEncryptingStream($plainStream, $cipher);

$sidecarStream = Utils::streamFor('');
$sidecarDecorator = new WhatsAppSidecarStream($encryptingStream, $cipher, $sidecarStream);

$outputStream = Utils::streamFor(fopen('encrypted.file', 'w'));
Utils::copyToStream($sidecarDecorator, $outputStream);

$sidecarContent = (string) $sidecarStream;
```

---

## Cipher & Key Types

A cipher is required for encryption and decryption. The library provides specialized key types and ciphers
for different media types.

### Available Key Types

The library supports three media-specific key types:

- **`AudioKey`** - for audio
- **`ImageKey`** - for image
- **`VideoKey`** - for video


### Available Ciphers

Corresponding to the key types, the library provides specialized cipher implementations:

- **`WhatsAppAudioCipher`** - handles encryption/decryption of audio
- **`WhatsAppImageCipher`** - handles encryption/decryption of image
- **`WhatsAppVideoCipher`** - handles encryption/decryption of vidoe

### Usage Examples

#### Recommended (media-specific cipher):

```php
$key = 'some-secret-key-here-32-bytes-long';

// For audio
$audioCipher = new WhatsAppAudioCipher($key);
$encrypted = new WhatsAppEncryptingStream($original, $audioCipher);

// For image
$imageCipher = new WhatsAppImageCipher($key);
$encrypted = new WhatsAppEncryptingStream($original, $imageCipher);

// For video
$videoCipher = new WhatsAppVideoCipher($key);
$encrypted = new WhatsAppEncryptingStream($original, $videoCipher);
```

#### Alternative (explicit key + generic cipher):

```php
$key = 'some-secret-key-here-32-bytes-long';

// Using specific key types with generic cipher
$audioKey = new AudioKey($key);
$audioCipher = new WhatsAppCipher($audioKey);
$encrypted = new WhatsAppEncryptingStream($original, $audioCipher);

$imageKey = new ImageKey($key);
$imageCipher = new WhatsAppCipher($imageKey);
$encrypted = new WhatsAppEncryptingStream($original, $imageCipher);

$videoKey = new VideoKey($key);
$videoCipher = new WhatsAppCipher($videoKey);
$encrypted = new WhatsAppEncryptingStream($original, $videoCipher);
```
