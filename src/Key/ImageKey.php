<?php

namespace Pikulsky\EncryptedStreams\Key;

/**
 * Image-specific implementation of {@see MediaTypeKeyInterface}.
 *
 * Associates a key with the corresponding application info value.
 */
class ImageKey extends MediaTypeKey implements MediaTypeKeyInterface
{
    public function __construct(string $key)
    {
        parent::__construct($key, ApplicationInfoEnum::IMAGE);
    }
}
