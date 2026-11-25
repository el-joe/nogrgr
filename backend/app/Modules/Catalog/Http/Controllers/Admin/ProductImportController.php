<?php

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Modules\Catalog\DataTables\ImportLogsDataTable;
use App\Modules\Catalog\DataTables\ImportRunsDataTable;
use App\Modules\Catalog\Enums\ImportDriver;
use App\Modules\Catalog\Http\Requests\Admin\ImportCategoryMapRequest;
use App\Modules\Catalog\Http\Requests\Admin\ImportDriverRequest;
use App\Modules\Catalog\Http\Requests\Admin\ImportStartRequest;
use App\Modules\Catalog\Models\CatalogImportRun;
use App\Modules\Catalog\Services\Imports\CatalogImportService;
use App\Modules\Catalog\Services\Imports\CategoryMappingService;
use App\Modules\Identity\Models\Admin;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin › Catalog › Products › Import (ADM-013, Figma Admin Panel 65:22120): source card,
 * live progress of the current run, its log / errors, runs history, preview, run /
 * pause / resume / cancel and the source category mapping.
 */
class ProductImportController extends Controller
{
    public function __construct(
        private readonly CatalogImportService $imports,
        private readonly CategoryMappingService $mapping,
    ) {}

    public function index(ImportDriverRequest $request): View
    {
        $driver = $request->driver();
        $overview = $this->imports->overview($driver);
        $selected = $request->integer('run') > 0 ? $this->imports->run($request->integer('run')) : null;
        $run = $selected !== null && $selected->driver === $driver ? $selected->load('starter') : $overview['run'];

        return view('catalog::admin.imports.index', [
            ...$overview,
            'run' => $run,
            'progress' => $run !== null ? $this->imports->progress($run) : null,
            'drivers' => ImportDriver::options(),
        ]);
    }

    public function progress(CatalogImportRun $run): JsonResponse
    {
        return JsonResponder::success(data: $this->imports->progress($run));
    }

    public function logs(Request $request, CatalogImportRun $run, ImportLogsDataTable $table): JsonResponse
    {
        return $table->forRun($run)->toJsonFromRequest($request, ImportLogsDataTable::FILTERS);
    }

    public function runs(Request $request, ImportRunsDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, ImportRunsDataTable::FILTERS);
    }

    public function preview(ImportDriverRequest $request): JsonResponse
    {
        try {
            $preview = $this->imports->preview($request->driver());
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return JsonResponder::success(data: [
            ...$preview,
            'html' => view('catalog::admin.imports._preview', ['preview' => $preview])->render(),
        ]);
    }

    public function store(ImportStartRequest $request): JsonResponse
    {
        /** @var Admin|null $admin */
        $admin = $request->user('admin');

        try {
            $run = $this->imports->start($request->toData(), $admin);
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return JsonResponder::created(
            __('catalog::imports.started'),
            ['id' => $run->id],
            route('admin.catalog.imports.index', ['driver' => $run->driver->value, 'run' => $run->id]),
        );
    }

    public function pause(CatalogImportRun $run): JsonResponse
    {
        return $this->transition(fn () => $this->imports->pause($run), $run, __('catalog::imports.paused'));
    }

    public function resume(CatalogImportRun $run): JsonResponse
    {
        return $this->transition(fn () => $this->imports->resume($run), $run, __('catalog::imports.resumed'));
    }

    public function cancel(CatalogImportRun $run): JsonResponse
    {
        return $this->transition(fn () => $this->imports->cancel($run), $run, __('catalog::imports.cancelled'));
    }

    public function categories(ImportDriverRequest $request): View
    {
        $driver = $request->driver();

        return view('catalog::admin.imports.categories', [
            'driver' => $driver,
            'rows' => $this->mapping->rows($driver, $request->boolean('unmapped')),
            'unmappedOnly' => $request->boolean('unmapped'),
            'categoryOptions' => $this->mapping->categoryOptions(),
            'overview' => $this->imports->overview($driver),
        ]);
    }

    public function saveCategories(ImportCategoryMapRequest $request): JsonResponse
    {
        try {
            $this->mapping->save($request->driver(), $request->toData());
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return JsonResponder::success(__('catalog::imports.mapping.saved'), redirect: route('admin.catalog.imports.categories', ['driver' => $request->driver()->value]));
    }

    private function transition(callable $action, CatalogImportRun $run, string $message): JsonResponse
    {
        try {
            $action();
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return JsonResponder::success($message, $this->imports->progress($run));
    }
}
