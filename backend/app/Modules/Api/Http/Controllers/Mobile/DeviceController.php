<?php

namespace App\Modules\Api\Http\Controllers\Mobile;

use App\Modules\Api\Http\Concerns\ResolvesMobileCustomer;
use App\Modules\Api\Http\Requests\Mobile\RegisterDeviceRequest;
use App\Modules\Api\Http\Resources\Mobile\DeviceResource;
use App\Modules\Notifications\Services\CustomerDeviceService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Push device registration (APP-029): the app reports its install id + FCM token at start
 * and whenever the token rotates; guests too (bound to the customer on sign-in).
 */
class DeviceController extends Controller
{
    use ResolvesMobileCustomer;

    public function __construct(private readonly CustomerDeviceService $devices) {}

    public function index(Request $request): JsonResponse
    {
        return JsonResponder::success('', ['devices' => DeviceResource::collection($this->devices->forCustomer($this->customer($request)))->resolve($request)]);
    }

    public function store(RegisterDeviceRequest $request): JsonResponse
    {
        $device = $this->devices->register($request->toData(), $this->optionalCustomer($request));

        return JsonResponder::success((string) __('api::mobile.device_saved'), ['device' => (new DeviceResource($device))->resolve($request)]);
    }

    public function destroy(Request $request, string $device): JsonResponse
    {
        abort_unless($this->devices->remove($device, $this->optionalCustomer($request)), 404);

        return JsonResponder::success((string) __('api::mobile.device_removed'));
    }
}
