<?php

namespace App\Modules\Ai\DataTables;

use App\Modules\Ai\Enums\VideoAdPlatform;
use App\Modules\Ai\Enums\VideoAdStatus;
use App\Modules\Ai\Models\VideoAd;
use App\Modules\Ai\Repositories\Contracts\VideoAdRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\EloquentDataTable;

/**
 * Vendor › Ads & Marketing › AI Advertising Studio › Advertising Library (VEN-010c): the
 * store's generated ads (poster, product + headline, platform / size / length, status
 * with live progress, created) with Preview / Download / Try again / Delete.
 *
 * @extends BaseDataTable<VideoAd>
 */
final class VideoAdDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'status', 'platform'];

    private bool $canManage = false;

    public function __construct(private readonly VideoAdRepositoryContract $ads) {}

    public function forStaff(bool $canManage): self
    {
        $this->canManage = $canManage;

        return $this;
    }

    protected function query(): Builder
    {
        return $this->ads->listing();
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        $status = VideoAdStatus::tryFrom((string) ($filters['status'] ?? ''));
        $platform = VideoAdPlatform::tryFrom((string) ($filters['platform'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));

        if ($status === VideoAdStatus::Queued) {
            $query->whereIn('status', array_map(static fn (VideoAdStatus $active): string => $active->value, VideoAdStatus::active()));
        } elseif ($status !== null) {
            $query->where('status', $status->value);
        }

        if ($platform !== null) {
            $query->where('platform', $platform->value);
        }

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(static fn (Builder $where) => $where->where('product_name', 'like', $like)->orWhere('headline', 'like', $like));
        }

        return $query;
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $disk = Storage::disk('public');

        return $table
            ->filter(static fn (): null => null)
            ->setRowId(static fn (VideoAd $ad): string => 'ad-'.$ad->id)
            ->addColumn('poster', static fn (VideoAd $ad): string => view('ai::vendor.ad-studio._poster', ['ad' => $ad, 'url' => $ad->poster_path !== null ? $disk->url($ad->poster_path) : null])->render())
            ->addColumn('ad', static fn (VideoAd $ad): string => '<span class="grid min-w-[180px] gap-0.5"><span class="font-semibold text-ink">'.e($ad->product_name).'</span>'
                .($ad->headline !== null ? '<span class="line-clamp-2 text-xs text-ink-muted">'.e($ad->headline).'</span>' : '').'</span>')
            ->addColumn('format', static fn (VideoAd $ad): string => '<span class="grid gap-0.5 whitespace-nowrap"><span class="text-ink">'.e($ad->platform->label()).'</span><span class="text-xs text-ink-muted" dir="ltr">'.e($ad->aspect->value.' · '.__('ai::ad_studio.seconds_short', ['count' => $ad->duration])).'</span></span>')
            ->editColumn('status', static fn (VideoAd $ad): string => view('ai::vendor.ad-studio._status', ['ad' => $ad])->render())
            ->editColumn('created_at', static fn (VideoAd $ad): string => e($ad->created_at->translatedFormat('j M Y, H:i')))
            ->addColumn('actions', fn (VideoAd $ad): string => view('ai::vendor.ad-studio._actions', ['ad' => $ad, 'canManage' => $this->canManage])->render())
            ->orderColumn('created_at', static fn (Builder $query, string $order) => $query->orderBy('id', $order === 'asc' ? 'asc' : 'desc'))
            ->orderColumn('status', static fn (Builder $query, string $order) => $query->orderBy('status', $order === 'asc' ? 'asc' : 'desc'))
            ->rawColumns(['poster', 'ad', 'format', 'status', 'actions']);
    }
}
