<?php

namespace App\Modules\Catalog\Repositories\Eloquent;

use App\Modules\Catalog\Enums\MessageSender;
use App\Modules\Catalog\Enums\RequestPriority;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared list / unread queries of the threaded request repositories.
 *
 * @template TModel of Model
 *
 * @extends BaseRepository<TModel>
 */
abstract class ThreadedRequestRepository extends BaseRepository
{
    /**
     * Subject column used by search ("title", "product_name", "brand_name").
     */
    abstract protected function subjectColumn(): string;

    /**
     * @return list<string>
     */
    abstract protected function statusValues(): array;

    /**
     * @return list<string>
     */
    abstract protected function openStatusValues(): array;

    /**
     * Statuses counted as "completed" on the vendor stats cards.
     *
     * @return list<string>
     */
    abstract protected function completedStatusValues(): array;

    protected function archivable(): bool
    {
        return false;
    }

    public function query(): Builder
    {
        return parent::query()->withoutGlobalScopes();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<TModel>
     */
    public function listing(array $filters = []): Builder
    {
        $query = $this->query();
        $table = $query->getModel()->getTable();
        $status = is_string($filters['status'] ?? null) ? $filters['status'] : '';
        $priority = RequestPriority::tryFrom((string) ($filters['priority'] ?? ''));
        $tenantId = filter_var($filters['tenant_id'] ?? null, FILTER_VALIDATE_INT);
        $search = is_string($filters['search'] ?? null) ? trim($filters['search']) : '';
        $subject = $table.'.'.$this->subjectColumn();

        $query->select($table.'.*', 'tenants.name as store_name', 'tenants.slug as store_slug')
            ->join('tenants', 'tenants.id', '=', $table.'.tenant_id')
            ->withCount(['messages as admin_unread_count' => static function (Builder $messages): void {
                $messages->where('sender', MessageSender::Vendor->value)->whereNull('read_at');
            }]);

        if ($status === 'open') {
            $query->whereIn($table.'.status', $this->openStatusValues());
        } elseif ($status !== '' && in_array($status, $this->statusValues(), true)) {
            $query->where($table.'.status', $status);
        }

        if ($this->archivable()) {
            ($filters['archived'] ?? null) === '1'
                ? $query->whereNotNull($table.'.archived_at')
                : $query->whereNull($table.'.archived_at');

            if ($priority !== null) {
                $query->where($table.'.priority', $priority->value);
            }
        }

        if ($tenantId !== false && $tenantId !== null) {
            $query->where($table.'.tenant_id', $tenantId);
        }

        if ($search !== '') {
            $term = '%'.addcslashes($search, '%_\\').'%';
            $digits = (string) preg_replace('/\D+/', '', $search);
            $query->where(static function (Builder $inner) use ($table, $subject, $term, $digits): void {
                $inner->where($subject, 'like', $term)
                    ->orWhere('tenants.name', 'like', $term)
                    ->orWhere('tenants.slug', 'like', $term);

                if ($digits !== '') {
                    $inner->orWhere($table.'.id', (int) $digits);
                }
            });
        }

        return $query;
    }

    public function adminUnreadCount(): int
    {
        return $this->query()
            ->when($this->archivable(), static fn (Builder $query) => $query->whereNull('archived_at'))
            ->where(static function (Builder $inner): void {
                $inner->whereNull('admin_seen_at')->orWhereHas('messages', static function (Builder $messages): void {
                    $messages->where('sender', MessageSender::Vendor->value)->whereNull('read_at');
                });
            })
            ->count();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<TModel>
     */
    public function vendorListing(int $tenantId, array $filters = []): Builder
    {
        $query = $this->query();
        $table = $query->getModel()->getTable();
        $status = is_string($filters['status'] ?? null) ? $filters['status'] : '';
        $search = is_string($filters['search'] ?? null) ? trim($filters['search']) : '';

        $query->select($table.'.*')
            ->where($table.'.tenant_id', $tenantId)
            ->withCount(['messages as vendor_unread_count' => static function (Builder $messages): void {
                $messages->where('sender', MessageSender::Admin->value)->whereNull('read_at');
            }]);

        if ($status === 'open') {
            $query->whereIn($table.'.status', $this->openStatusValues());
        } elseif ($status !== '' && in_array($status, $this->statusValues(), true)) {
            $query->where($table.'.status', $status);
        }

        if ($search !== '') {
            $term = '%'.addcslashes($search, '%_\\').'%';
            $digits = (string) preg_replace('/\D+/', '', $search);
            $subject = $table.'.'.$this->subjectColumn();
            $query->where(static function (Builder $inner) use ($table, $subject, $term, $digits): void {
                $inner->where($subject, 'like', $term);

                if ($digits !== '') {
                    $inner->orWhere($table.'.id', (int) $digits);
                }
            });
        }

        return $query;
    }

    /**
     * @return TModel|null
     */
    public function findForTenant(int $tenantId, int $id): ?Model
    {
        return $this->query()->where('tenant_id', $tenantId)->whereKey($id)->first();
    }

    public function vendorUnreadCount(int $tenantId): int
    {
        return $this->query()
            ->where('tenant_id', $tenantId)
            ->whereHas('messages', static function (Builder $messages): void {
                $messages->where('sender', MessageSender::Admin->value)->whereNull('read_at');
            })
            ->count();
    }

    /**
     * @return array{total: int, open: int, completed: int}
     */
    public function vendorStats(int $tenantId): array
    {
        $counts = $this->query()->where('tenant_id', $tenantId)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(static fn (mixed $count): int => (int) $count);

        return [
            'total' => (int) $counts->sum(),
            'open' => (int) $counts->only($this->openStatusValues())->sum(),
            'completed' => (int) $counts->only($this->completedStatusValues())->sum(),
        ];
    }
}
