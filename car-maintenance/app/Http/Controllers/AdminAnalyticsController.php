<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalyticsRangeRequest;
use App\Services\AnalyticsReport;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAnalyticsController extends Controller
{
    public function index(AnalyticsRangeRequest $request, AnalyticsReport $analyticsReport): Response
    {
        $range = $request->range();

        return inertia('Admin/Analytics', [
            'report' => $analyticsReport->forRange($range['from'], $range['to']),
            'filters' => [
                'period' => $range['period'],
                'from' => $range['from']->toDateString(),
                'to' => $range['to']->toDateString(),
            ],
        ]);
    }

    public function export(AnalyticsRangeRequest $request, AnalyticsReport $analyticsReport): StreamedResponse
    {
        $range = $request->range();
        $report = $analyticsReport->adPlacementsForRange($range['from'], $range['to']);

        $filename = sprintf(
            'ad-placement-report_%s_%s.csv',
            $range['from']->toDateString(),
            $range['to']->toDateString(),
        );

        return response()->streamDownload(function () use ($report): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Ad placement report', "{$report['from']} to {$report['to']}"]);
            fputcsv($out, ['Generated at', now()->toIso8601String()]);
            fputcsv($out, ['Question answered', 'Where should we put the ads to monetize the system?']);
            fputcsv($out, []);

            fputcsv($out, ['SECTION 1 - Ad placement opportunities (where to place ads)']);
            fputcsv($out, [
                'Rank', 'Route URI', 'Route name', 'Page views', 'Share of traffic %',
                'Unique sessions', 'Views per session', 'Suggested ad format', 'Why',
            ]);

            foreach ($report['placements'] as $index => $row) {
                fputcsv($out, [
                    $index + 1,
                    $row['route_uri'],
                    $row['route_name'],
                    $row['views'],
                    $row['share_of_views'],
                    $row['sessions'],
                    $row['views_per_session'],
                    $row['suggested_format'],
                    $row['rationale'],
                ]);
            }

            fputcsv($out, []);
            fputcsv($out, ['SECTION 2 - Acquisition channels (who brings the traffic worth monetizing)']);
            fputcsv($out, ['Rank', 'Referrer host', 'Page views', 'Unique sessions', 'Share of traffic %']);

            foreach ($report['channels'] as $index => $row) {
                fputcsv($out, [
                    $index + 1,
                    $row['host'],
                    $row['views'],
                    $row['sessions'],
                    $row['share_of_views'],
                ]);
            }

            fputcsv($out, []);
            fputcsv($out, ['SECTION 3 - Audience geography (geo-target the ad inventory)']);
            fputcsv($out, ['Rank', 'Country code', 'Page views', 'Share of traffic %']);

            foreach ($report['countries'] as $index => $row) {
                fputcsv($out, [
                    $index + 1,
                    $row['country_code'],
                    $row['views'],
                    $row['share_of_views'],
                ]);
            }

            fputcsv($out, ['', 'Unknown country', $report['unknown_country_views'], '']);

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
