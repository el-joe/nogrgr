<?php

namespace App\Modules\Platform\Http\Middleware;

use App\Modules\Platform\Services\Auditor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records every state-changing request (POST/PUT/PATCH/DELETE) on panel surfaces.
 * Only input *keys* are stored (values may hold PII); model-level diffs come from
 * the Auditable trait.
 */
class AuditRequests
{
    public function __construct(private readonly Auditor $auditor) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $route = $request->route();
            $name = $route?->getName() ?? $request->path();

            $this->auditor->record('http.'.$name, null, [
                'method' => $request->method(),
                'status' => $response->getStatusCode(),
                'parameters' => $route?->originalParameters() ?? [],
                'input_keys' => array_keys($request->except(['_token', '_method'])),
            ]);
        }

        return $response;
    }
}
