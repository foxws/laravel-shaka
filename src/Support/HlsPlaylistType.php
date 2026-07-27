<?php

declare(strict_types=1);

namespace Foxws\Shaka\Support;

/**
 * HLS playlist type (EXT-X-PLAYLIST-TYPE).
 *
 * For Live, the EXT-X-PLAYLIST-TYPE tag is omitted entirely.
 */
enum HlsPlaylistType: string
{
    case Vod = 'VOD';
    case Event = 'EVENT';
    case Live = 'LIVE';
}
