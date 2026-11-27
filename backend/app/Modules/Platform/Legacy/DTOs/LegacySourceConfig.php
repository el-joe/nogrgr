<?php

namespace App\Modules\Platform\Legacy\DTOs;

use Dotenv\Dotenv;
use RuntimeException;
use SensitiveParameter;

/**
 * Where the old system lives: its DB server, central DB name, APP_KEY (to decrypt
 * gateway credentials / secret settings) and public storage root (media files).
 * Read once from the old project's `.env`; never logged or printed.
 */
final readonly class LegacySourceConfig
{
    public function __construct(
        public string $host,
        public int $port,
        public string $database,
        public string $username,
        #[SensitiveParameter] public string $password,
        #[SensitiveParameter] public ?string $appKey,
        public string $storageRoot,
    ) {}

    public static function fromProject(string $projectPath): self
    {
        $envFile = rtrim($projectPath, '/').'/.env';

        if (! is_readable($envFile)) {
            throw new RuntimeException("Old project .env not readable at [{$envFile}].");
        }

        $env = Dotenv::parse((string) file_get_contents($envFile));

        return new self(
            host: (string) ($env['DB_HOST'] ?? '127.0.0.1'),
            port: (int) ($env['DB_PORT'] ?? 3306),
            database: (string) ($env['DB_DATABASE'] ?? ''),
            username: (string) ($env['DB_USERNAME'] ?? 'root'),
            password: (string) ($env['DB_PASSWORD'] ?? ''),
            appKey: isset($env['APP_KEY']) && $env['APP_KEY'] !== '' ? (string) $env['APP_KEY'] : null,
            storageRoot: rtrim($projectPath, '/').'/storage/app/public',
        );
    }

    /**
     * Same server and credentials, another database (tests and per-store DBs).
     */
    public function withDatabase(string $database): self
    {
        return new self($this->host, $this->port, $database, $this->username, $this->password, $this->appKey, $this->storageRoot);
    }
}
