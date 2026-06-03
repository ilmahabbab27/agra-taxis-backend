<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\ChatbotController;
use App\Http\Controllers\Api\EstimateController;
use App\Http\Controllers\Api\PlaceController;
use App\Http\Controllers\Api\QuickBookingController;
use App\Http\Controllers\Api\VehicleController;

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

Route::post('/admin/login', [AdminAuthController::class, 'login']);
Route::get('/places/predict', [PlaceController::class, 'predict']);
Route::post('/estimate', [EstimateController::class, 'calculate']);
Route::post('/chatbot/message', [ChatbotController::class, 'message']);
Route::post('/chatbot/estimate', [ChatbotController::class, 'estimate']);
Route::post('/chatbot/booking', [QuickBookingController::class, 'store']);
Route::post('/quick-booking', [QuickBookingController::class, 'store']);
Route::post('/bookings', [BookingController::class, 'store']);
Route::get('/vehicles', [VehicleController::class, 'index']);
Route::get('/vehicle-categories', [VehicleController::class, 'categories']);
Route::post('/vehicle-categories', [VehicleController::class, 'storeCategory']);
Route::post('/vehicles', [VehicleController::class, 'store']);
Route::put('/vehicles/{vehicle}', [VehicleController::class, 'update']);
Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/admin/logout', [AdminAuthController::class, 'logout']);
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::patch('/bookings/{booking}/status', [BookingController::class, 'updateStatus']);
    Route::delete('/bookings/{booking}', [BookingController::class, 'destroy']);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
