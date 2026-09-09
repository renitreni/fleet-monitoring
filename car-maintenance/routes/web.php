<?php

use App\Http\Controllers\AdminTripController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\CarMileageController;
use App\Http\Controllers\CarsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\OilChangesController;
use App\Http\Controllers\OilSuggestionsController;
use App\Http\Controllers\RouteCatalogController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\TripTrackingController;
use App\Http\Middleware\PrivateTripResponse;
use App\Services\TripStandings;
use Illuminate\Support\Facades\Route;

Route::get('/', function (TripStandings $standings) {
    return inertia('Welcome', ['publicTrips' => $standings->publicTrips()]);
});

Route::get('/routes', [RouteCatalogController::class, 'index'])->middleware(PrivateTripResponse::class)->name('routes.index');
Route::get('/routes/{trip}', [RouteCatalogController::class, 'show'])->middleware(PrivateTripResponse::class)->name('routes.show');

Route::middleware(['guest'])->group(function () {
    Route::get('/login', fn () => inertia('Auth/Login'))->name('login');
    Route::get('/register', fn () => inertia('Auth/Register'))->name('register');

    Route::get('/auth/{provider}', [SocialAuthController::class, 'redirect'])
        ->name('social.redirect');
    Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])
        ->name('social.callback');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('cars', CarsController::class);

    Route::post('cars/{car}/oil-changes', [OilChangesController::class, 'store'])
        ->name('cars.oil-changes.store');
    Route::put('cars/{car}/oil-changes/{oilChange}', [OilChangesController::class, 'update'])
        ->name('cars.oil-changes.update');

    Route::put('cars/{car}/mileage', [CarMileageController::class, 'update'])
        ->name('cars.mileage.update');

    Route::get('cars/{car}/oil-suggestions', [OilSuggestionsController::class, 'index'])
        ->name('cars.oil-suggestions.index');
    Route::post('cars/{car}/oil-suggestions/generate', [OilSuggestionsController::class, 'generate'])
        ->middleware('throttle:5,1')
        ->name('cars.oil-suggestions.generate');

    Route::get('/notifications', [NotificationsController::class, 'index'])
        ->name('notifications.index');
    Route::get('/api/notifications/recent', [NotificationsController::class, 'recent'])
        ->name('notifications.recent');
    Route::post('/notifications/{id}/read', [NotificationsController::class, 'markAsRead'])
        ->name('notifications.read');
});

Route::middleware(['auth', PrivateTripResponse::class])->group(function () {
    Route::post('/routes/{trip}/join', [RouteCatalogController::class, 'join'])->middleware('throttle:10,1')->name('routes.join');
    Route::post('/trips/{trip}/cancel', [TripTrackingController::class, 'cancel'])->name('trips.cancel');
    Route::get('/admin/trips', [AdminTripController::class, 'index'])->name('admin.trips.index');
    Route::post('/admin/trips', [AdminTripController::class, 'store'])->middleware('throttle:10,1')->name('admin.trips.store');
    Route::patch('/admin/trips/{trip}', [AdminTripController::class, 'update'])->name('admin.trips.update');
    Route::get('/trips', [TripController::class, 'index'])->name('trips.index');
    Route::get('/trips/invitations/{token}', [TripController::class, 'invite'])->name('trips.invite');
    Route::post('/trips/invitations/{token}', [TripController::class, 'join'])->middleware('throttle:10,1')->name('trips.join');
    Route::get('/trips/{trip}', [TripController::class, 'show'])->name('trips.show');
    Route::post('/trips/{trip}/start', [TripTrackingController::class, 'start'])->middleware('throttle:10,1')->name('trips.start');
    Route::post('/trips/{trip}/stop', [TripTrackingController::class, 'stop'])->name('trips.stop');
    Route::post('/trips/{trip}/locations', [TripTrackingController::class, 'store'])->middleware('throttle:30,1')->name('trips.location');
    Route::post('/trips/{trip}/leave', [TripController::class, 'leave'])->name('trips.leave');
    Route::patch('/trips/{trip}/consent', [TripController::class, 'consent'])->name('trips.consent');
});
