<?php

namespace App\Modules\Catalog\Http\Controllers\Admin\Requests;

use App\Modules\Catalog\Services\Requests\RequestsCenterService;
use App\Modules\Identity\Models\Admin;
use App\Shared\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Requests center hub (ADM-018): opens the first tab the admin may see (each tab page
 * shows every tab with its attention count).
 */
class RequestsCenterController extends Controller
{
    public function __invoke(Request $request, RequestsCenterService $center): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        return redirect()->to($center->firstRoute($admin) ?? abort(403));
    }
}
