<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalyticsRangeRequest;
use App\Services\AnalyticsReport;
use Inertia\Response;

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
}
