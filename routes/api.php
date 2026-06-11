<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

use App\Http\Controllers\Api\RouteController;
use App\Http\Controllers\Api\TripController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\FavouriteRouteController;
use App\Http\Controllers\Api\NotificationController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public Endpoints
Route::get('/routes', [RouteController::class, 'index']);
Route::get('/routes/{id}', [RouteController::class, 'show']);
Route::get('/trips', [TripController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    
    // Booking
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::post('/bookings/{id}/tap-in', [BookingController::class, 'tapIn']);
    Route::post('/bookings/{id}/tap-out', [BookingController::class, 'tapOut']);

    // Wallet
    Route::get('/wallet', [WalletController::class, 'show']);
    Route::post('/wallet/topup', [WalletController::class, 'topup']);
    Route::get('/wallet/history', [WalletController::class, 'history']);

    // Rating
    Route::post('/ratings', [RatingController::class, 'store']);

    // Favourites
    Route::get('/favourites', [FavouriteRouteController::class, 'index']);
    Route::post('/favourites', [FavouriteRouteController::class, 'store']);
    Route::delete('/favourites/{id}', [FavouriteRouteController::class, 'destroy']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
});

use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\BusController;
use App\Http\Controllers\Api\Admin\RouteController as AdminRouteController;
use App\Http\Controllers\Api\Admin\HalteController;
use App\Http\Controllers\Api\Admin\DriverController;
use App\Http\Controllers\Api\Admin\ConductorController;
use App\Http\Controllers\Api\Admin\ScheduleController;
use App\Http\Controllers\Api\Admin\TripController as AdminTripController;
use App\Http\Controllers\Api\Admin\CsvUploadController;

Route::middleware(['auth:sanctum', 'role:admin,operator'])->prefix('admin')->group(function () {
    Route::apiResource('users', UserController::class);
    Route::apiResource('buses', BusController::class);
    Route::apiResource('routes', AdminRouteController::class);
    Route::apiResource('haltes', HalteController::class);
    Route::apiResource('drivers', DriverController::class);
    Route::apiResource('conductors', ConductorController::class);
    Route::apiResource('schedules', ScheduleController::class);
    Route::apiResource('trips', AdminTripController::class);
    Route::apiResource('csv-uploads', CsvUploadController::class)->except(['update']);
});

