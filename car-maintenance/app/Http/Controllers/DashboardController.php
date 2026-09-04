<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $cars = $request->user()
            ->cars()
            ->with('oilChanges')
            ->latest()
            ->get();

        return inertia('Dashboard', [
            'stats' => [
                'total_cars' => $cars->count(),
                'overdue' => $cars->where('oil_status', 'overdue')->count(),
                'due_soon' => $cars->where('oil_status', 'due_soon')->count(),
            ],
            'cars' => $cars,
        ]);
    }
}
