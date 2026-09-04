<?php

use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\CarMileageController;
use App\Http\Controllers\CarsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\OilChangesController;
use App\Http\Controllers\OilSuggestionsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return inertia('Welcome');
});

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
