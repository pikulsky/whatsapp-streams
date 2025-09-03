<?php

namespace Pikulsky\EncryptedStreams\Key;

/**
 * Video-specific implementation of {@see MediaTypeKeyInterface}.
 *
 * Associates a key with the corresponding application info value.
 */
class VideoKey extends MediaTypeKey implements MediaTypeKeyInterface
{
    public function __construct(string $key)
    {
        parent::__construct($key, ApplicationInfoEnum::VIDEO);
    }
}
