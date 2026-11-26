<?php

namespace App\Modules\Ai\Support;

use App\Modules\Ai\Events\AdminTranslationRunUpdated;

/**
 * Live progress settings of admin AI translation runs: the Reverb (Pusher protocol)
 * connection of the private "admin.translation-runs" channel — null when broadcasting
 * is not Reverb — plus the poll interval used while a run is active (fallback).
 */
final class AdminTranslationLive
{
    public const POLL_MS = 5000;

    /**
     * @return array{pollMs: int, realtime: array{key: string, host: string, port: int, tls: bool, channel: string, authUrl: string, event: string}|null}
     */
    public static function config(): array
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
                'channel' => 'private-'.AdminTranslationRunUpdated::CHANNEL,
                'authUrl' => route('admin.notifications.broadcast-auth'),
                'event' => 'admin-translation-run.updated',
            ];
        }

        return ['pollMs' => self::POLL_MS, 'realtime' => $realtime];
    }
}
