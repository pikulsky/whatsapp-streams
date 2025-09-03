<?php

namespace Pikulsky\EncryptedStreams\Key;

/**
 * Enumerates application info values used with {@see MediaTypeKeyInterface}.
 *
 * Each case represents the media-type–specific identifier required by the cipher.
 */
enum ApplicationInfoEnum: string
{
    case AUDIO = 'WhatsApp Audio Keys';
    case IMAGE = 'WhatsApp Image Keys';
    case VIDEO = 'WhatsApp Video Keys';

    // Reserved for possible future use
    case DOCUMENT = 'WhatsApp Document Keys';
}
