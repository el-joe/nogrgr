<?php

namespace App\Shared\Http\Controllers;

use App\Shared\Http\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Answers 404 for an unknown URL under a panel prefix after the panel middleware ran
 * (SurfaceRouter::mountPanelFallback), so resources/views/errors/404 renders the panel's
 * own error page with the signed-in user.
 */
class PanelFallbackController extends Controller
{
    public function __invoke(): never
    {
        throw new NotFoundHttpException;
    }
}
