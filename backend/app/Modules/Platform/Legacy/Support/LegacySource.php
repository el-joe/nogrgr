<?php

namespace App\Modules\Platform\Legacy\Support;

use App\Modules\Platform\Legacy\DTOs\LegacySourceConfig;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Read-only access to the old system: the central DB (`legacy`), one store DB at a
 * time (`legacy_store`), its APP_KEY for decryption and its public storage root.
 * Nothing here ever writes to the old databases.
 */
class LegacySource
{
    public const CENTRAL = 'legacy';

    public const STORE = 'legacy_store';

    private ?LegacySourceConfig $config = null;

    private ?Encrypter $encrypter = null;

    public function __construct(private readonly DatabaseManager $db) {}

    public function configure(LegacySourceConfig $config): void
    {
        $this->config = $config;
        $this->encrypter = null;
        $this->register(self::CENTRAL, $config->database);
    }

    public function config(): LegacySourceConfig
    {
        return $this->config ?? throw new RuntimeException('Legacy source is not configured.');
    }

    public function central(): Connection
    {
        return $this->db->connection(self::CENTRAL);
    }

    /**
     * The old store DB, or null when it does not exist on the server.
     */
    public function store(string $database): ?Connection
    {
        $exists = $this->central()->selectOne('SELECT 1 AS ok FROM information_schema.schemata WHERE schema_name = ?', [$database]);

        if ($exists === null) {
            return null;
        }

        $this->register(self::STORE, $database);

        return $this->db->connection(self::STORE);
    }

    public function hasTable(Connection $connection, string $table): bool
    {
        return $connection->getSchemaBuilder()->hasTable($table);
    }

    /**
     * Decrypts a value encrypted by the old app (Crypt / `encrypted` casts). Null when
     * the value is empty, the key is missing or the payload does not decrypt.
     */
    public function decrypt(?string $payload): ?string
    {
        if ($payload === null || $payload === '') {
            return null;
        }

        $encrypter = $this->encrypter();

        if ($encrypter === null) {
            return null;
        }

        try {
            $plain = $encrypter->decrypt($payload, false);
        } catch (DecryptException) {
            return null;
        }

        if (is_string($plain) && preg_match('/^(s|a|i|b|d|N):/', $plain) === 1) {
            $value = @unserialize($plain, ['allowed_classes' => false]);

            if ($value !== false || $plain === 'b:0;') {
                return is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
            }
        }

        return is_string($plain) ? $plain : null;
    }

    public function mediaPath(string $relative): string
    {
        return $this->config()->storageRoot.'/'.ltrim($relative, '/');
    }

    private function encrypter(): ?Encrypter
    {
        if ($this->encrypter !== null) {
            return $this->encrypter;
        }

        $key = $this->config()->appKey;

        if ($key === null) {
            return null;
        }

        try {
            $raw = Str::startsWith($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;

            return $this->encrypter = new Encrypter((string) $raw, 'AES-256-CBC');
        } catch (Throwable) {
            return null;
        }
    }

    private function register(string $name, string $database): void
    {
        $config = $this->config();

        config(["database.connections.{$name}" => [
            'driver' => 'mysql',
            'host' => $config->host,
            'port' => $config->port,
            'database' => $database,
            'username' => $config->username,
            'password' => $config->password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => false,
        ]]);

        $this->db->purge($name);
    }
}
