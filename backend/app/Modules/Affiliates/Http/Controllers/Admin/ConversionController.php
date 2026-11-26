<?php

namespace App\Modules\Affiliates\Http\Controllers\Admin;

use App\Modules\Affiliates\DataTables\ConversionsDataTable;
use App\Modules\Affiliates\Enums\ConversionSource;
use App\Modules\Affiliates\Enums\ConversionStatus;
use App\Modules\Affiliates\Http\Requests\NoteRequest;
use App\Modules\Affiliates\Http\Requests\ReverseConversionRequest;
use App\Modules\Affiliates\Models\AffiliateConversion;
use App\Modules\Affiliates\Repositories\Contracts\ConversionRepositoryContract;
use App\Modules\Affiliates\Services\ConversionService;
use App\Modules\Identity\Models\Admin;
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RemoteModalPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin › Affiliates › Conversions (ADM-061, Figma 74:67899 · 74:68877): status tabs,
 * search + source filter, detail, reverse (approved / paid, reason required) and reject
 * (pending).
 */
class ConversionController extends Controller
{
    use RendersRemoteModals;

    public function __construct(
        private readonly ConversionRepositoryContract $conversions,
        private readonly ConversionService $service,
    ) {}

    public function index(Request $request): View
    {
        $status = ConversionStatus::tryFrom((string) $request->query('status'));

        return view('affiliates::admin.conversions.index', [
            'counts' => $this->conversions->counts(),
            'active' => $status->value ?? 'all',
            'sources' => ConversionSource::options(),
        ]);
    }

    public function data(Request $request, ConversionsDataTable $table): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        return $table->abilities($admin->can('affiliates.conversions.manage'))->toJsonFromRequest($request, ConversionsDataTable::FILTERS);
    }

    public function show(Request $request, int $conversion): View
    {
        $row = $this->find($conversion);
        $row->loadMissing(['affiliate', 'tenant', 'promoCode']);
        /** @var Admin $admin */
        $admin = $request->user('admin');

        return $this->remoteModal($request, 'affiliates::admin.conversions._show', [
            'conversion' => $row,
            'canManage' => $admin->can('affiliates.conversions.manage'),
        ], $this->page($row->reference()));
    }

    public function reverseForm(Request $request, int $conversion): View
    {
        $row = $this->find($conversion);

        return $this->remoteModal($request, 'affiliates::admin.conversions._reverse-form', ['conversion' => $row], $this->page($row->reference()));
    }

    public function reverse(ReverseConversionRequest $request, int $conversion): JsonResponse
    {
        $row = $this->service->reverse($this->find($conversion), $request->reason());

        return JsonResponder::success((string) __('affiliates::admin.conversions.reversed', ['reference' => $row->reference()]));
    }

    public function reject(NoteRequest $request, int $conversion): JsonResponse
    {
        $row = $this->service->reject($this->find($conversion), $request->note());

        return JsonResponder::success((string) __('affiliates::admin.conversions.rejected', ['reference' => $row->reference()]));
    }

    private function find(int $id): AffiliateConversion
    {
        /** @var AffiliateConversion|null $row */
        $row = $this->conversions->find($id);

        return $row ?? abort(404);
    }

    private function page(string $title): RemoteModalPage
    {
        return new RemoteModalPage('admin', $title, route('admin.affiliates.conversions.index'), 'admin-affiliates', null, null, [
            __('navigation.admin.affiliates') => null,
            __('navigation.admin.conversions') => route('admin.affiliates.conversions.index'),
            $title => null,
        ]);
    }
}
