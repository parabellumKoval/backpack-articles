<?php

namespace Backpack\Articles\app\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AddXRegionHeadersToRequest
{
    public function handle(Request $request, Closure $next)
    {
        $region = $request->header('X-Region') ?? $request->header('X-Country');

        if (is_string($region)) {
            $region = strtolower(trim($region));
        } else {
            $region = null;
        }

        if ($region === '') {
            $region = null;
        }

        if ($region === 'global') {
            $alias = config('dress.store.global_region_code', 'zz');

            if (is_string($alias)) {
                $alias = strtolower(trim($alias));
            }

            if (! empty($alias)) {
                $region = $alias;
            }
        }

        if ($region !== null) {
            $request->merge([
                'country' => $region,
            ]);
        }

        return $next($request);
    }
}
