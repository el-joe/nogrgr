<?php

namespace App\Shared\Tenancy;

final class CentralDomains
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        /** @var list<string> $domains */
        $domains = config('tenancy.central_domains', []);

        return array_values($domains);
    }

    /**
     * The domain that serves the central website, admin and affiliate panels.
     *
     * @param  list<string>|null  $domains  defaults to config('tenancy.central_domains')
     */
    public static function primary(?array $domains = null): string
    {
        return ($domains ?? self::all())[0] ?? 'localhost';
    }

    /**
     * @return list<string>
     */
    public static function secondary(): array
    {
        return array_values(array_slice(self::all(), 1));
    }

    /**
     * Parse a comma separated env value into a clean list of hosts.
     *
     * @return list<string>
     */
    public static function parse(?string $value): array
    {
        return array_values(array_filter(array_map(
            static fn (string $domain): string => strtolower(trim($domain)),
            explode(',', (string) $value),
        )));
    }
}
