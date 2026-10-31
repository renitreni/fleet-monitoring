<?php

namespace App\Services;

use App\Models\AnalyticsPageView;
use App\Models\BlogPost;
use App\Models\Car;
use App\Models\OilChange;
use App\Models\RouteAttempt;
use App\Models\TripParticipant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;

class AnalyticsReport
{
    /**
     * @return array{
     *     overview: array{page_views: int, unique_sessions: int, registrations: int, activation_rate: float},
     *     product: array{cars_added: int, oil_changes: int, route_joins: int, completed_attempts: int, published_posts: int, blog_views: int},
     *     trend: array<int, array{date: string, page_views: int, sessions: int, registrations: int}>,
     *     top_pages: array<int, array{route_name: string, route_uri: string, views: int}>,
     *     top_referrers: array<int, array{host: string, views: int}>,
     *     top_countries: array<int, array{country_code: string, views: int}>,
     *     unknown_country_views: int,
     *     registered_users: array<int, array{id: int, name: string, email: string, created_at: ?string, has_car: bool}>,
     *     generated_at: string
     * }
     */
    public function forRange(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $pageViews = $this->pageViewsBetween($from, $to);
        $registrations = User::withTrashed()->whereBetween('created_at', [$from, $to])->count();
        $activatedUsers = User::withTrashed()
            ->whereBetween('created_at', [$from, $to])
            ->whereHas('cars', fn (Builder $query): Builder => $query->where('created_at', '<=', $to))
            ->count();

        $registeredUsers = User::withTrashed()
            ->whereBetween('created_at', [$from, $to])
            ->withCount(['cars' => fn (Builder $query): Builder => $query->where('created_at', '<=', $to)])
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'email', 'created_at'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at?->toIso8601String(),
                'has_car' => $user->cars_count > 0,
            ])
            ->values()
            ->all();

        return [
            'overview' => [
                'page_views' => (clone $pageViews)->count(),
                'unique_sessions' => (clone $pageViews)->distinct()->count('session_hash'),
                'registrations' => $registrations,
                'activation_rate' => $registrations === 0 ? 0.0 : round(($activatedUsers / $registrations) * 100, 1),
            ],
            'registered_users' => $registeredUsers,
            'product' => [
                'cars_added' => Car::whereBetween('created_at', [$from, $to])->count(),
                'oil_changes' => OilChange::whereBetween('created_at', [$from, $to])->count(),
                'route_joins' => TripParticipant::whereBetween('created_at', [$from, $to])->count(),
                'completed_attempts' => RouteAttempt::where('status', 'completed')->whereBetween('finished_at', [$from, $to])->count(),
                'published_posts' => BlogPost::withTrashed()->whereBetween('published_at', [$from, $to])->count(),
                'blog_views' => (clone $pageViews)->where('route_name', 'blog.show')->count(),
            ],
            'trend' => $this->trend($from, $to),
            'top_pages' => (clone $pageViews)
                ->select(['route_name', 'route_uri'])
                ->selectRaw('COUNT(*) as views')
                ->groupBy('route_name', 'route_uri')
                ->orderByDesc('views')
                ->orderBy('route_name')
                ->limit(8)
                ->get()
                ->map(fn (AnalyticsPageView $pageView): array => [
                    'route_name' => $pageView->route_name,
                    'route_uri' => $pageView->route_uri,
                    'views' => (int) $pageView->views,
                ])->all(),
            'top_referrers' => (clone $pageViews)
                ->whereNotNull('referrer_host')
                ->select('referrer_host')
                ->selectRaw('COUNT(*) as views')
                ->groupBy('referrer_host')
                ->orderByDesc('views')
                ->orderBy('referrer_host')
                ->limit(8)
                ->get()
                ->map(fn (AnalyticsPageView $pageView): array => [
                    'host' => $pageView->referrer_host,
                    'views' => (int) $pageView->views,
                ])->all(),
            'top_countries' => (clone $pageViews)
                ->whereNotNull('country_code')
                ->select('country_code')
                ->selectRaw('COUNT(*) as views')
                ->groupBy('country_code')
                ->orderByDesc('views')
                ->orderBy('country_code')
                ->limit(8)
                ->get()
                ->map(fn (AnalyticsPageView $pageView): array => [
                    'country_code' => $pageView->country_code,
                    'views' => (int) $pageView->views,
                ])->all(),
            'unknown_country_views' => (clone $pageViews)->whereNull('country_code')->count(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function pageViewsBetween(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return AnalyticsPageView::query()->whereBetween('occurred_at', [$from, $to]);
    }

    /**
     * Ad placement intelligence answering "where should we put the ads".
     *
     * @return array{
     *     from: string,
     *     to: string,
     *     total_views: int,
     *     placements: array<int, array{route_name: string, route_uri: string, views: int, share_of_views: float, sessions: int, views_per_session: float, suggested_format: string, rationale: string}>,
     *     channels: array<int, array{host: string, views: int, sessions: int, share_of_views: float}>,
     *     countries: array<int, array{country_code: string, views: int, share_of_views: float}>,
     *     unknown_country_views: int
     * }
     */
    public function adPlacementsForRange(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $pageViews = $this->pageViewsBetween($from, $to);
        $totalViews = (clone $pageViews)->count();

        $placements = (clone $pageViews)
            ->select(['route_name', 'route_uri'])
            ->selectRaw('COUNT(*) as views')
            ->selectRaw('COUNT(DISTINCT session_hash) as sessions')
            ->groupBy('route_name', 'route_uri')
            ->orderByDesc('views')
            ->orderBy('route_name')
            ->get()
            ->map(function (AnalyticsPageView $pageView) use ($totalViews): array {
                $views = (int) $pageView->views;
                $sessions = (int) $pageView->sessions;
                [$format, $rationale] = $this->suggestAdFormat($pageView->route_name);

                return [
                    'route_name' => $pageView->route_name,
                    'route_uri' => $pageView->route_uri,
                    'views' => $views,
                    'share_of_views' => $totalViews === 0 ? 0.0 : round(($views / $totalViews) * 100, 1),
                    'sessions' => $sessions,
                    'views_per_session' => $sessions === 0 ? 0.0 : round($views / $sessions, 2),
                    'suggested_format' => $format,
                    'rationale' => $rationale,
                ];
            })->all();

        $channels = (clone $pageViews)
            ->whereNotNull('referrer_host')
            ->select('referrer_host')
            ->selectRaw('COUNT(*) as views')
            ->selectRaw('COUNT(DISTINCT session_hash) as sessions')
            ->groupBy('referrer_host')
            ->orderByDesc('views')
            ->orderBy('referrer_host')
            ->get()
            ->map(fn (AnalyticsPageView $pageView): array => [
                'host' => $pageView->referrer_host,
                'views' => (int) $pageView->views,
                'sessions' => (int) $pageView->sessions,
                'share_of_views' => $totalViews === 0 ? 0.0 : round(((int) $pageView->views / $totalViews) * 100, 1),
            ])->all();

        $countries = (clone $pageViews)
            ->whereNotNull('country_code')
            ->select('country_code')
            ->selectRaw('COUNT(*) as views')
            ->groupBy('country_code')
            ->orderByDesc('views')
            ->orderBy('country_code')
            ->get()
            ->map(fn (AnalyticsPageView $pageView): array => [
                'country_code' => $pageView->country_code,
                'views' => (int) $pageView->views,
                'share_of_views' => $totalViews === 0 ? 0.0 : round(((int) $pageView->views / $totalViews) * 100, 1),
            ])->all();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'total_views' => $totalViews,
            'placements' => $placements,
            'channels' => $channels,
            'countries' => $countries,
            'unknown_country_views' => (clone $pageViews)->whereNull('country_code')->count(),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function suggestAdFormat(string $routeName): array
    {
        return match (true) {
            str_starts_with($routeName, 'blog.') => [
                'In-content + sidebar',
                'Content pages hold attention; ads blend into the reading flow without touching product workflows.',
            ],
            $routeName === 'home' => [
                'Below-the-fold banner',
                'Landing attention is high; place ads after the value proposition to protect conversion.',
            ],
            str_starts_with($routeName, 'routes.') || str_starts_with($routeName, 'trips.') => [
                'Inline list cards',
                'List browsing tolerates sponsored cards between rows without breaking core actions.',
            ],
            str_starts_with($routeName, 'dashboard')
                || str_starts_with($routeName, 'admin.')
                || str_starts_with($routeName, 'account.')
                || str_starts_with($routeName, 'cars.') => [
                    'Keep ad-free',
                    'Product and account surfaces drive activation and retention; ads here would cost more than they earn.',
                ],
            default => [
                'Standard banner',
                'Moderate traffic; test a banner and measure bounce before expanding.',
            ],
        };
    }

    /**
     * @return array<int, array{date: string, page_views: int, sessions: int, registrations: int}>
     */
    private function trend(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $traffic = $this->pageViewsBetween($from, $to)
            ->selectRaw('DATE(occurred_at) as date, COUNT(*) as page_views, COUNT(DISTINCT session_hash) as sessions')
            ->groupByRaw('DATE(occurred_at)')
            ->get()
            ->keyBy('date');
        $registrations = User::withTrashed()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as registrations')
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->keyBy('date');

        return collect(CarbonPeriod::create($from->startOfDay(), $to->startOfDay()))
            ->map(function ($date) use ($traffic, $registrations): array {
                $dateString = $date->toDateString();

                return [
                    'date' => $dateString,
                    'page_views' => (int) ($traffic->get($dateString)?->page_views ?? 0),
                    'sessions' => (int) ($traffic->get($dateString)?->sessions ?? 0),
                    'registrations' => (int) ($registrations->get($dateString)?->registrations ?? 0),
                ];
            })->values()->all();
    }
}
