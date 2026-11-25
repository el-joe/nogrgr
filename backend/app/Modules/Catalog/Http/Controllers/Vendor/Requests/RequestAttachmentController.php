<?php

namespace App\Modules\Catalog\Http\Controllers\Vendor\Requests;

use App\Modules\Catalog\Services\Requests\RequestThreadService;
use App\Shared\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Streams an attachment of the store's own request after RequestAttachmentPolicy::view
 * (same store + view permission of the request type). ?download=1 forces a download.
 */
class RequestAttachmentController extends Controller
{
    public function __construct(private readonly RequestThreadService $threads) {}

    public function __invoke(Request $request, int $attachment): StreamedResponse
    {
        $file = $this->threads->findAttachment($attachment) ?? throw new NotFoundHttpException;
        Gate::forUser($request->user('vendor'))->authorize('view', $file);

        return $this->threads->stream($file, $request->boolean('download'));
    }
}
