<?php

namespace Pikulsky\EncryptedStreams\Key;

/**
 * Audio-specific implementation of {@see MediaTypeKeyInterface}.
 *
 * Associates a key with the corresponding application info value.
 */
class AudioKey extends MediaTypeKey implements MediaTypeKeyInterface
{
    public function __construct(string $key)
    {
        parent::__construct($key, ApplicationInfoEnum::AUDIO);
    }
}
