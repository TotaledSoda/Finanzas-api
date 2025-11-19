<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\BillController;
use App\Http\Controllers\Api\CalendarEventController;
use App\Http\Controllers\Api\TandaController;
use App\Http\Controllers\Api\SavingGoalController;
use App\Http\Controllers\Api\SavingGoalMemberController;

// Rutas públicas de auth
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'login']);
});

// Rutas protegidas con Sanctum
Route::middleware('auth:sanctum')->group(function () {

    // Perfil / sesión
    Route::get('/auth/me',      [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'show']);

    // Recibos y pagos
    Route::get('/bills',        [BillController::class, 'index']);   // ?status=pending|paid|all
    Route::post('/bills',       [BillController::class, 'store']);
    Route::get('/bills/{id}',   [BillController::class, 'show']);
    Route::put('/bills/{id}',   [BillController::class, 'update']);
    Route::delete('/bills/{id}', [BillController::class, 'destroy']);

    // Calendario financiero
    Route::get('/calendar/events', [CalendarEventController::class, 'index']);

 
    // Tandas
    Route::get('/tandas',       [TandaController::class, 'index']);
    Route::post('/tandas',      [TandaController::class, 'store']);
    Route::get('/tandas/{id}',  [TandaController::class, 'show']);


    // Metas de ahorro
    Route::get('/goals',        [SavingGoalController::class, 'index']);
    Route::post('/goals',       [SavingGoalController::class, 'store']);
    Route::get('/goals/{id}',   [SavingGoalController::class, 'show']);
    Route::put('/goals/{id}',   [SavingGoalController::class, 'update']);


    //METAS DE AHORRO - MIEBROS DE LA META

    // MIENBOROS DE METAS DE AHORRO 
    Route::post('/goals/{goal}/members', [SavingGoalMemberController::class, 'store']);
    Route::post('/goals/{id}/deposit', [SavingGoalController::class, 'deposit']);
});
