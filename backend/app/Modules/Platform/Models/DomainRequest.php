<?php

namespace App\Modules\Platform\Models;

use App\Modules\Platform\Concerns\Auditable;
use App\Modules\Platform\Database\Factories\DomainRequestFactory;
use App\Modules\Platform\Enums\DnsCheckStatus;
use App\Modules\Platform\Enums\DomainRequestStatus;
use App\Modules\Platform\Enums\SslStatus;
use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Custom-domain request (central DB). `dns_result` = list of {type, host, expected, found, ok}.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $host
 * @property DomainRequestStatus $status
 * @property string $verification_token
 * @property DnsCheckStatus $dns_status
 * @property list<array{type: string, host: string, expected: string, found: list<string>, ok: bool}>|null $dns_result
 * @property SslStatus $ssl_status
 * @property Carbon|null $ssl_expires_at
 * @property Carbon|null $last_checked_at
 * @property int|null $domain_id
 * @property Carbon|null $connected_at
 * @property Carbon|null $removed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 */
class DomainRequest extends Model
{
    use Auditable;
    use BelongsToCurrentTenant;
    use CentralConnection;

    /** @use HasFactory<DomainRequestFactory> */
    use HasFactory;

    protected $table = 'domain_requests';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id', 'host', 'status', 'verification_token', 'dns_status', 'dns_result', 'ssl_status', 'ssl_expires_at',
        'last_checked_at', 'domain_id', 'connected_at', 'removed_at',
    ];

    protected static function newFactory(): DomainRequestFactory
    {
        return DomainRequestFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DomainRequestStatus::class,
            'dns_status' => DnsCheckStatus::class,
            'dns_result' => 'array',
            'ssl_status' => SslStatus::class,
            'ssl_expires_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'connected_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Domain, $this>
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Record types that failed the last DNS check ("CNAME", "TXT").
     *
     * @return list<string>
     */
    public function missingRecordTypes(): array
    {
        return array_values(array_unique(array_map(
            static fn (array $row): string => (string) $row['type'],
            array_filter($this->dns_result ?? [], static fn (array $row): bool => ! ($row['ok'] ?? false)),
        )));
    }
}
