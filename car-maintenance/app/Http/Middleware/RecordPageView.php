<?php

namespace App\Http\Middleware;

use App\Models\AnalyticsPageView;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RecordPageView
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldRecord($request, $response)) {
            $attributes = [
                'user_id' => $request->user()?->id,
                'session_hash' => hash_hmac('sha256', $request->session()->getId(), (string) config('app.key')),
                'route_name' => (string) $request->route()?->getName(),
                'route_uri' => (string) $request->route()?->uri(),
                'referrer_host' => $this->externalReferrerHost($request),
                'occurred_at' => now(),
            ];

            defer(fn () => AnalyticsPageView::create($attributes));
        }

        return $response;
    }

    private function shouldRecord(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || ! $response->isSuccessful() || $request->prefetch()) {
            return false;
        }

        if (! $request->route()?->getName() || $request->is('admin/*', 'auth/*', 'api/*', 'notifications*')) {
            return false;
        }

        if ($request->routeIs('login', 'register', 'social.*', 'blog.feed', 'blog.sitemap')) {
            return false;
        }

        return ! preg_match('/bot|crawler|spider|slurp|bingpreview|facebookexternalhit/i', $request->userAgent() ?? '');
    }

    private function externalReferrerHost(Request $request): ?string
    {
        $referrer = $request->headers->get('referer');

        if (! $referrer) {
            return null;
        }

        $host = parse_url($referrer, PHP_URL_HOST);

        if (! is_string($host) || strcasecmp($host, $request->getHost()) === 0) {
            return null;
        }

        return Str::limit(Str::lower($host), 255, '');
    }
}
