<?php

namespace App\Shared\Http\Controllers;

use App\Shared\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Answers 404 for an unknown URL under a panel prefix after the panel middleware ran
 * (SurfaceRouter::mountPanelFallback), so resources/views/errors/404 renders the panel's
 * own error page with the signed-in user. A write verb on a URL that exists under another
 * verb stays a 405.
 */
class PanelFallbackController extends Controller
{
    public function __invoke(Request $request, Router $router): never
    {
        if (! $request->isMethodSafe()) {
            $allowed = [];

            foreach ($router->getRoutes()->getRoutes() as $route) {
                /** @var Route $route */
                if (! $route->isFallback && $route->matches($request, false)) {
                    array_push($allowed, ...$route->methods());
                }
            }

            if ($allowed !== []) {
                throw new MethodNotAllowedHttpException(array_values(array_unique($allowed)));
            }
        }

        throw new NotFoundHttpException;
    }
}
