<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\BillController;
use App\Http\Controllers\Api\CalendarEventController;
use App\Http\Controllers\Api\SavingGoalController;
use App\Http\Controllers\Api\SavingGoalMemberController;
use App\Http\Controllers\Api\TandaController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\CalendarController;




// 🔐 AUTH (las rutas que tu app está usando: /api/auth/register, /api/auth/login)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

// Todo lo de abajo requiere estar logueado con Sanctum
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);

    // 📊 Dashboard
    Route::get('/dashboard', [DashboardController::class, 'show']);
    Route::post('/dashboard/weekly-income', [DashboardController::class, 'updateWeeklyIncome']);

    // 🧾 Recibos
    Route::apiResource('bills', BillController::class)->except(['create', 'edit']);

    // 📅 Calendario financiero
    Route::get('/calendar/events', [CalendarEventController::class, 'index']);

    // 🎯 Metas de ahorro
    Route::get('/saving-goals', [SavingGoalController::class, 'index']);
    Route::post('/saving-goals', [SavingGoalController::class, 'store']);

    // Aportar a una meta específica
    Route::post('/saving-goals/{savingGoal}/contribute', [SavingGoalController::class, 'addContribution']);

    // 👥 Tandas
    Route::apiResource('tandas', TandaController::class)->only(['index', 'store', 'show']);

    // 💸 Gastos
     Route::apiResource('expenses', ExpenseController::class)
        ->only(['index', 'store', 'destroy']);
        
    // 📅 Calendario
    Route::middleware('auth:sanctum')->group(function () {
    Route::get('/calendar', [CalendarController::class, 'index']);
});


});
