<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LogistikController;

/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/

Route::get('/login', [AuthController::class, 'index'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);

/*
|--------------------------------------------------------------------------
| REDIRECT ROOT
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect('/login');
});

/*
|--------------------------------------------------------------------------
| AUTH MIDDLEWARE GROUP
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {

    /*
    | DASHBOARD
    */
    Route::get('/dashboard', [LogistikController::class, 'dashboard']);

    /*
    | DATA LOGISTIK
    */
    Route::get('/datalogistik', [LogistikController::class, 'dataLogistik']);

    Route::post('/logistik/store', [LogistikController::class, 'store']);
    Route::post('/logistik/update/{id}', [LogistikController::class, 'update']);
    Route::get('/logistik/delete/{id}', [LogistikController::class, 'delete']);

    Route::post('/logistik/import', [LogistikController::class, 'import']);

    /*
    | ARMADA
    */
    Route::get('/armada', [LogistikController::class, 'armada']);
    Route::get('/belum-armada', [LogistikController::class, 'belumArmada']);

    /*
    | SLA
    */
    Route::get('/sla/ontime', [LogistikController::class, 'slaOntime']);
    Route::get('/sla/delay', [LogistikController::class, 'slaDelay']);

    /*
    | CHART & EXPORT
    */
    Route::get('/chart/status', [LogistikController::class, 'chartStatus']);
    Route::get('/export', [LogistikController::class, 'export']);
});