<?php

namespace App\Modules\Platform\Http\Requests\Admin;

use App\Modules\Platform\DTOs\MaintenanceWindowData;
use App\Modules\Platform\Enums\MaintenanceScope;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Maintenance window (ADM-113): scope (+ store for "store"), title, notes, message en/ar,
 * allowed IPs (IPv4 / IPv6 or CIDR, one per line or comma separated, max 50), start
 * (now or a date-time) and an optional end after the start.
 */
class MaintenanceWindowRequest extends FormRequest
{
    public const MAX_IPS = 50;

    public function authorize(): bool
    {
        return (bool) $this->user('admin')?->can('system.maintenance.manage');
    }

    protected function prepareForValidation(): void
    {
        $ips = preg_split('/[\s,]+/', (string) $this->input('allowed_ips', ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $this->merge([
            'allowed_ips' => array_values(array_unique($ips)),
            'starts_now' => $this->boolean('starts_now'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scope' => ['required', 'string', Rule::enum(MaintenanceScope::class)],
            'tenant_id' => ['nullable', 'required_if:scope,'.MaintenanceScope::Store->value, 'integer', Rule::exists('tenants', 'id')],
            'title' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:255'],
            'message_en' => ['nullable', 'string', 'max:500'],
            'message_ar' => ['nullable', 'string', 'max:500'],
            'allowed_ips' => ['array', 'max:'.self::MAX_IPS],
            'allowed_ips.*' => ['string', 'max:64', function (string $attribute, mixed $value, Closure $fail): void {
                if (! self::validIpOrRange((string) $value)) {
                    $fail(__('platform::maintenance.validation.ip', ['value' => (string) $value]));
                }
            }],
            'starts_now' => ['boolean'],
            'starts_at' => ['nullable', 'required_unless:starts_now,true', 'date'],
            'ends_at' => ['nullable', 'date', function (string $attribute, mixed $value, Closure $fail): void {
                if ($value !== null && $value !== '' && Carbon::parse((string) $value)->lessThanOrEqualTo($this->startsAt())) {
                    $fail(__('platform::maintenance.validation.ends_after'));
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tenant_id.required_if' => __('platform::maintenance.validation.store_required'),
            'starts_at.required_unless' => __('platform::maintenance.validation.start_required'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'scope' => __('platform::maintenance.fields.scope'),
            'tenant_id' => __('platform::maintenance.fields.store'),
            'title' => __('platform::maintenance.fields.title'),
            'notes' => __('platform::maintenance.fields.notes'),
            'message_en' => __('platform::maintenance.fields.message_en'),
            'message_ar' => __('platform::maintenance.fields.message_ar'),
            'allowed_ips' => __('platform::maintenance.fields.allowed_ips'),
            'starts_at' => __('platform::maintenance.fields.starts_at'),
            'ends_at' => __('platform::maintenance.fields.ends_at'),
        ];
    }

    public static function validIpOrRange(string $value): bool
    {
        [$address, $bits] = array_pad(explode('/', $value, 2), 2, null);

        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if ($bits === null) {
            return true;
        }

        $max = str_contains((string) $address, ':') ? 128 : 32;

        return ctype_digit($bits) && (int) $bits <= $max && IpUtils::checkIp((string) $address, $value);
    }

    public function toData(): MaintenanceWindowData
    {
        $endsAt = $this->validated('ends_at');

        return new MaintenanceWindowData(
            scope: MaintenanceScope::from((string) $this->validated('scope')),
            tenantId: $this->validated('tenant_id') !== null ? (int) $this->validated('tenant_id') : null,
            title: trim((string) $this->validated('title')),
            notes: filled($this->validated('notes')) ? trim((string) $this->validated('notes')) : null,
            message: ['en' => $this->validated('message_en'), 'ar' => $this->validated('message_ar')],
            allowedIps: array_values((array) $this->validated('allowed_ips', [])),
            startsAt: $this->startsAt(),
            endsAt: filled($endsAt) ? Carbon::parse((string) $endsAt) : null,
        );
    }

    private function startsAt(): Carbon
    {
        return $this->boolean('starts_now') || blank($this->input('starts_at')) ? now() : Carbon::parse((string) $this->input('starts_at'));
    }
}
