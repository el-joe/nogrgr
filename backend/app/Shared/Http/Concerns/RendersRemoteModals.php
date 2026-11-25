<?php

namespace App\Shared\Http\Concerns;

use App\Shared\Http\RemoteModalPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Remote-modal endpoints return the bare fragment to the modal loader
 * (fetch with X-Requested-With) and a full panel page to everything else.
 */
trait RendersRemoteModals
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function remoteModal(Request $request, string $view, array $data, RemoteModalPage $page): View
    {
        if ($request->ajax()) {
            return view($view, $data);
        }

        return view('panels.remote-modal-page', ['fragment' => $view, 'fragmentData' => $data, 'page' => $page]);
    }
}
