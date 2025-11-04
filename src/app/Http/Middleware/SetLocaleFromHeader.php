<?php

namespace Backpack\Articles\app\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromHeader
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->header('Accept-Language');
        
        if ($locale) {
            // Get first preferred language from Accept-Language
            $locale = substr($locale, 0, 2);
            
            // Check if locale is in allowed locales list
            // if (in_array($locale, array_keys(config('backpack.crud.locales', ['en' => 'English'])))) {
            //     app()->setLocale($locale);
            // }
            app()->setLocale($locale);
        }

        return $next($request);
    }
}