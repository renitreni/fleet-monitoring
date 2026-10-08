<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds conservative public cache headers to public blog pages.
 * Never applied to admin, preview, or authenticated routes.
 */
class CachePublicBlogPages
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->isSuccessful() && ! $request->user()) {
            $response->headers->set(
                'Cache-Control',
                'public, max-age=300, stale-while-revalidate=600',
            );
        }

        return $response;
    }
}
