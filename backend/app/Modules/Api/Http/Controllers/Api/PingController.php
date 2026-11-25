<?php

namespace App\Modules\Api\Http\Controllers\Api;

use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;

class PingController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return JsonResponder::success('pong', [
            'tenant' => tenant()?->getTenantKey(),
            'version' => 'v1',
        ]);
    }
}
