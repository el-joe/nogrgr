<?php

namespace App\Modules\Platform\Models;

use App\Modules\Platform\Concerns\Auditable;
use App\Modules\Platform\Database\Factories\DnsRecordTemplateFactory;
use App\Modules\Platform\Enums\DnsRecordType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * DNS record a vendor must add for a custom domain (central DB, platform-wide).
 * Placeholders in host / value: {domain} (the custom host), {subdomain} (store default
 * host), {token} (the request's verification token).
 *
 * @property int $id
 * @property DnsRecordType $type
 * @property string $host
 * @property string $value
 * @property int $ttl
 * @property int|null $priority
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DnsRecordTemplate extends Model
{
    use Auditable;
    use CentralConnection;

    /** @use HasFactory<DnsRecordTemplateFactory> */
    use HasFactory;

    public const PLACEHOLDERS = ['{domain}', '{subdomain}', '{token}'];

    protected $table = 'dns_record_templates';

    /**
     * @var list<string>
     */
    protected $fillable = ['type', 'host', 'value', 'ttl', 'priority', 'sort_order'];

    protected static function newFactory(): DnsRecordTemplateFactory
    {
        return DnsRecordTemplateFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DnsRecordType::class,
            'ttl' => 'integer',
            'priority' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  array<string, string>  $replacements  placeholder => value
     * @return array{type: string, host: string, value: string, ttl: int, priority: int|null}
     */
    public function render(array $replacements): array
    {
        return [
            'type' => $this->type->value,
            'host' => strtr($this->host, $replacements),
            'value' => strtr($this->value, $replacements),
            'ttl' => $this->ttl,
            'priority' => $this->priority,
        ];
    }
}
