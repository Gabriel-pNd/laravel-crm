<?php

namespace Webkul\NeuroFlow\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Webkul\NeuroFlow\Infrastructure\Krayin\ActiveClinicResolver;

class ResolveActiveClinic
{
    public function __construct(private readonly ActiveClinicResolver $activeClinicResolver)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $clinic = $this->activeClinicResolver->resolve($request);

        if (! $clinic) {
            abort(403, 'No active NeuroFlow clinic membership was found for this user.');
        }

        $request->attributes->set('neuroflow.active_clinic', $clinic);

        return $next($request);
    }
}
