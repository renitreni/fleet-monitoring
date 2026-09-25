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

        return [
            'overview' => [
                'page_views' => (clone $pageViews)->count(),
                'unique_sessions' => (clone $pageViews)->distinct()->count('session_hash'),
                'registrations' => $registrations,
                'activation_rate' => $registrations === 0 ? 0.0 : round(($activatedUsers / $registrations) * 100, 1),
            ],
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
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function pageViewsBetween(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return AnalyticsPageView::query()->whereBetween('occurred_at', [$from, $to]);
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
