<?php

use App\Http\Controllers\SensorsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('/v1/sensors')->group(function () {

    Route::post('{uuid}/measurements', [SensorsController::class, 'postMeasurements']);
    Route::get('{uuid}', [SensorsController::class, 'getStatus']);
    Route::get('{uuid}/metrics', [SensorsController::class, 'getMetrics']);
    Route::get('{uuid}/alerts', [SensorsController::class, 'getAlerts']);
});
