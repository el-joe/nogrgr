<?php

namespace App\Modules\Ai\Support;

use App\Modules\Ai\Events\StoreTranslationRunUpdated;

/**
 * Settings of the live translation-run progress on vendor pages: Reverb (Pusher
 * protocol) connection of the store's "tenant.<id>.translation-runs" channel — null when
 * broadcasting is not Reverb — plus the JSON status URL the page re-reads on every push
 * (and polls while a run is active, as fallback).
 */
final class StoreTranslationLive
{
    public const POLL_MS = 10000;

    /**
     * @return array{statusUrl: string, pollMs: int, realtime: array{key: string, host: string, port: int, tls: bool, channel: string, authUrl: string, event: string}|null}
     */
    public static function config(int $tenantId, string $statusUrl): array
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
                'channel' => 'private-'.StoreTranslationRunUpdated::channelName($tenantId),
                'authUrl' => vendor_route('notifications.broadcast-auth'),
                'event' => 'translation-run.updated',
            ];
        }

        return ['statusUrl' => $statusUrl, 'pollMs' => self::POLL_MS, 'realtime' => $realtime];
    }
}
