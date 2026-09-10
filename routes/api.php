<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\ChatbotController;
use App\Http\Controllers\Api\EstimateController;
use App\Http\Controllers\Api\FareEstimateController;
use App\Http\Controllers\Api\PlaceController;
use App\Http\Controllers\Api\QuickBookingController;
use App\Http\Controllers\Api\LorryController;
use App\Http\Controllers\Api\LorryChatbotController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\DriverRideController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/debug', function (Request $request) {
    return [
        'path' => $request->getPathInfo(),
        'url' => $request->getRequestUri(),
        'method' => $request->getMethod(),
        'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
        'script_name' => $_SERVER['SCRIPT_NAME'] ?? null,
    ];
});

Route::post('/admin/login', [AdminAuthController::class, 'login']);
Route::get('/places/predict', [PlaceController::class, 'predict']);
Route::post('/estimate', [EstimateController::class, 'calculate']);
Route::post('/fare-estimate', [FareEstimateController::class, 'calculate']);
Route::post('/chatbot/message', [ChatbotController::class, 'message']);
Route::post('/chatbot/estimate', [ChatbotController::class, 'estimate']);
Route::post('/lorry-chatbot/message', [LorryChatbotController::class, 'message']);
Route::post('/chatbot/booking', [QuickBookingController::class, 'store']);
Route::post('/quick-booking', [QuickBookingController::class, 'store']);
Route::post('/bookings', [BookingController::class, 'store']);
Route::get('/vehicles', [VehicleController::class, 'index']);
Route::get('/lorries', [LorryController::class, 'index']);
Route::post('/lorry-estimate', [LorryController::class, 'estimate']);
Route::get('/vehicle-categories', [VehicleController::class, 'categories']);
Route::post('/vehicle-categories', [VehicleController::class, 'storeCategory']);
Route::post('/vehicles/image', [VehicleController::class, 'uploadImage']);
Route::post('/vehicles', [VehicleController::class, 'store']);
Route::put('/vehicles/{vehicle}', [VehicleController::class, 'update']);
Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy']);
Route::post('/lorries', [LorryController::class, 'store']);
Route::get('/lorries/{lorry}', [LorryController::class, 'show']);
Route::put('/lorries/{lorry}', [LorryController::class, 'update']);
Route::delete('/lorries/{lorry}', [LorryController::class, 'destroy']);
Route::post('/driver-registrations', [DriverController::class, 'register']);
Route::post('/driver-auth/login', [DriverController::class, 'login']);
Route::post('/driver-auth/forgot-password', [DriverController::class, 'forgotPassword']);
Route::post('/driver-auth/change-password', [DriverController::class, 'changePassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/driver-account', [DriverController::class, 'account']);
    Route::post('/driver-account', [DriverController::class, 'updateAccount']);
    Route::patch('/driver-account', [DriverController::class, 'updateAccount']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/admin/logout', [AdminAuthController::class, 'logout']);
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::patch('/bookings/{booking}/status', [BookingController::class, 'updateStatus']);
    Route::delete('/bookings/{booking}', [BookingController::class, 'destroy']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/admin/driver-registrations', [DriverController::class, 'index']);
    Route::post('/admin/driver-registrations/{driver}', [DriverController::class, 'updateStatus']);
    Route::patch('/admin/driver-registrations/{driver}', [DriverController::class, 'updateStatus']);
    Route::delete('/admin/driver-registrations/{driver}', [DriverController::class, 'destroy']);
    Route::get('/admin/driver-rides', [DriverRideController::class, 'all']);
    Route::get('/admin/drivers/{driverId}/rides', [DriverRideController::class, 'index']);
    Route::post('/admin/drivers/{driverId}/rides', [DriverRideController::class, 'store']);
    Route::post('/admin/drivers/{driverId}/rides/{ride}', [DriverRideController::class, 'update']);
    Route::delete('/admin/drivers/{driverId}/rides/{ride}', [DriverRideController::class, 'destroy']);
    Route::delete('/admin/driver-rides/{ride}', [DriverRideController::class, 'destroyAny']);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
