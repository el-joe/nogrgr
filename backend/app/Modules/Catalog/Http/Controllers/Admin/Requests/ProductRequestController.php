<?php

namespace App\Modules\Catalog\Http\Controllers\Admin\Requests;

use App\Modules\Catalog\DataTables\ProductRequestsDataTable;
use App\Modules\Catalog\Enums\RequestKind;
use App\Modules\Catalog\Http\Requests\Admin\Requests\RequestArchiveRequest;
use App\Modules\Catalog\Http\Requests\Admin\Requests\RequestPriorityRequest;
use App\Modules\Catalog\Models\Requests\ProductRequest;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;

/**
 * Requests center › Product requests (ADM-018c/d): chat with files, status, priority, archive.
 */
class ProductRequestController extends ThreadedRequestController
{
    protected function kind(): RequestKind
    {
        return RequestKind::Product;
    }

    protected function table(): ProductRequestsDataTable
    {
        return app(ProductRequestsDataTable::class);
    }

    public function priority(RequestPriorityRequest $request, int $id): JsonResponse
    {
        $thread = $this->productRequest($id);
        $this->requests->changePriority($thread, $request->priority());

        return JsonResponder::success(__('catalog::requests.priority_changed', ['reference' => $thread->reference(), 'priority' => $thread->priority->label()]));
    }

    public function archive(RequestArchiveRequest $request, int $id): JsonResponse
    {
        $thread = $this->productRequest($id);
        $this->requests->archive($thread, $request->archived());

        return JsonResponder::success(__($request->archived() ? 'catalog::requests.archived' : 'catalog::requests.restored', ['reference' => $thread->reference()]));
    }

    private function productRequest(int $id): ProductRequest
    {
        /** @var ProductRequest $thread */
        $thread = $this->find($id);

        return $thread;
    }
}
