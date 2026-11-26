<?php

namespace App\Modules\Ai\Support;

use App\Modules\Ai\Events\VideoAdUpdated;

/**
 * Realtime settings of the AI Advertising Studio: Reverb (Pusher protocol) connection of
 * the store's "tenant.<id>.video-ads" channel — null when broadcasting is not Reverb — and
 * the poll interval used while an ad is being generated (fallback).
 */
final class VideoAdLive
{
    public const POLL_MS = 4000;

    /**
     * @return array{pollMs: int, realtime: array{key: string, host: string, port: int, tls: bool, channel: string, authUrl: string, event: string}|null}
     */
    public static function config(int $tenantId): array
    {
        $key = config('broadcasting.connections.reverb.key');
        $realtime = null;

        if (config('broadcasting.default') === 'reverb' && is_string($key) && $key !== '') {
            $scheme = (string) config('broadcasting.connections.reverb.options.scheme', 'https');
            $realtime = [
                'key' => $key,
                'host' => (string) (config('broadcasting.connections.reverb.options.host') ?: request()->getHost()),
                'port' => (int) config('broadcasting.connections.reverb.options.port', $scheme === 'https' ? 443 : 80),
                'tls' => $scheme === 'https',
                'channel' => 'private-'.VideoAdUpdated::channelName($tenantId),
                'authUrl' => vendor_route('notifications.broadcast-auth'),
                'event' => 'video-ad.updated',
            ];
        }

        return ['pollMs' => self::POLL_MS, 'realtime' => $realtime];
    }
}
