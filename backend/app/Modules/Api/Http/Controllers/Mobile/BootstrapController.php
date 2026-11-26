<?php

namespace App\Modules\Api\Http\Controllers\Mobile;

use App\Modules\Api\Http\Requests\Mobile\BootstrapRequest;
use App\Modules\Api\Http\Requests\Mobile\TextsRequest;
use App\Modules\Api\Services\MobileBootstrapService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;

/**
 * App start-up (APP-001 / 002 / 003) and the store's static texts per language.
 * `texts` honours `If-None-Match` with the texts version (304).
 */
class BootstrapController extends Controller
{
    public function __construct(private readonly MobileBootstrapService $bootstrap) {}

    public function show(BootstrapRequest $request): JsonResponse
    {
        return JsonResponder::success('', $this->bootstrap->bootstrap(
            $request->validated('platform'),
            $request->validated('app_version'),
            $request->validated('country'),
        ));
    }

    public function texts(TextsRequest $request): JsonResponse
    {
        $locale = app()->getLocale();
        $group = $request->validated('group');
        $etag = '"'.$this->bootstrap->textsVersion($locale).'-'.($group ?? 'all').'"';

        if ($request->header('If-None-Match') === $etag) {
            return (new JsonResponse(null, 304))->setEtag(trim($etag, '"'));
        }

        return JsonResponder::success('', $this->bootstrap->texts($locale, is_string($group) ? $group : null), headers: ['ETag' => $etag]);
    }
}
