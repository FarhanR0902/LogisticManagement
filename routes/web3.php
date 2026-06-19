<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| CONTROLLERS
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LogistikController;
use App\Http\Controllers\Planner\PlannerController;
use App\Http\Controllers\Monitoring\MonitoringController;
use App\Http\Controllers\Manager\ManagerController;
use App\Http\Controllers\Sales\SalesController;
use App\Http\Controllers\Spv\SpvPlannerController;
use App\Http\Controllers\Spv\SpvMonitoringController;
use App\Http\Controllers\Developer\DeveloperController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\StorageController;
use App\Http\Controllers\DashboardController;

/*
|--------------------------------------------------------------------------
| ROOT
|--------------------------------------------------------------------------
*/

Route::get('/', fn() => redirect('/login'));

/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/

Route::get('/login', [AuthController::class, 'index'])->name('login');
Route::post('/login', [AuthController::class, 'login']);

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/login');
});

/*
|--------------------------------------------------------------------------
| DASHBOARD (GLOBAL - ALL ROLES)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'dashboard'])
        ->name('dashboard');
});

/*
|--------------------------------------------------------------------------
| LOGISTIK (CRUD UTAMA)
|--------------------------------------------------------------------------
*/

Route::prefix('logistik')->group(function () {

    Route::get('/', [LogistikController::class, 'index']);
    Route::get('/data', [LogistikController::class, 'dataLogistik']);

    Route::post('/store', [LogistikController::class, 'store']);
    Route::post('/update/{id}', [LogistikController::class, 'update']);
    Route::get('/delete/{id}', [LogistikController::class, 'delete']);

    Route::get('/edit/{id}', [LogistikController::class, 'edit']);
    Route::delete('/destroy/{id}', [LogistikController::class, 'destroy']);

    Route::post('/import', [LogistikController::class, 'import']);
    Route::post('/delete-all', [LogistikController::class, 'deleteAll']);
    Route::post('/archive-all', [LogistikController::class, 'archiveAll']);

    Route::get('/export', [LogistikController::class, 'export']);
    Route::get('/chart/status', [LogistikController::class, 'chartStatus']);
});

/*
|--------------------------------------------------------------------------
| PLANNER
|--------------------------------------------------------------------------
*/

Route::prefix('planner')->name('planner.')->group(function () {

    Route::get('/dashboard', [PlannerController::class, 'dashboard'])->name('dashboard');
    Route::get('/sla-ontime', [PlannerController::class, 'slaOntime'])->name('sla.ontime');
    Route::get('/sla-delay', [PlannerController::class, 'slaDelay'])->name('sla.delay');

    Route::get('/armada', [PlannerController::class, 'armada'])->name('armada');
    Route::get('/belum-armada', [PlannerController::class, 'belumArmada'])->name('belum.armada');

    Route::get('/data', [PlannerController::class, 'index'])->name('index');
    Route::put('/update/{id}', [PlannerController::class, 'update'])->name('update');

    Route::get('/data-logistik', [PlannerController::class, 'dataLogistik'])
        ->name('datalogistik');
});

/*
|--------------------------------------------------------------------------
| MONITORING
|--------------------------------------------------------------------------
*/

Route::prefix('monitoring')->name('monitoring.')->group(function () {

    Route::get('/dashboard', [MonitoringController::class, 'dashboard'])->name('dashboard');
    Route::get('/data-logistik', [MonitoringController::class, 'dataLogistik'])->name('datalogistik');

    Route::put('/update/{id}', [MonitoringController::class, 'updateMonitoring']);

    Route::get('/sla-ontime', [MonitoringController::class, 'slaOntime'])->name('sla.ontime');
    Route::get('/sla-delay', [MonitoringController::class, 'slaDelay'])->name('sla.delay');

    Route::get('/bongkar/ontime', [MonitoringController::class, 'bongkarOnTime'])->name('bongkar.ontime');
    Route::get('/bongkar/delay', [MonitoringController::class, 'bongkarDelay'])->name('bongkar.delay');

    Route::get('/summary-area', [MonitoringController::class, 'summaryArea'])->name('summary.area');
    Route::get('/summary-area/detail', [MonitoringController::class, 'summaryAreaDetail'])->name('summary.area.detail');
});

/*
|--------------------------------------------------------------------------
| MANAGER (DETAIL PAGE ONLY)
|--------------------------------------------------------------------------
*/
Route::prefix('manager')->name('manager.')->group(function () {

    Route::get('/gudang/ontime', [ManagerController::class, 'gudangOnTime'])->name('gudang.ontime');
    Route::get('/gudang/delay', [ManagerController::class, 'gudangDelay'])->name('gudang.delay');

    Route::get('/customer/ontime', [ManagerController::class, 'tujuanOnTime'])->name('customer.ontime');
    Route::get('/customer/delay', [ManagerController::class, 'tujuanDelay'])->name('customer.delay');

    Route::get('/bongkar/ontime', [ManagerController::class, 'bongkarOnTime'])->name('bongkar.ontime');
    Route::get('/bongkar/delay', [ManagerController::class, 'bongkarDelay'])->name('bongkar.delay');

    Route::get('/summary-total', [ManagerController::class, 'summaryTotal'])->name('summary.total');
    Route::get('/summary-area', [ManagerController::class, 'summaryArea'])->name('summary.area');
});
/*
|--------------------------------------------------------------------------
| SALES
|--------------------------------------------------------------------------
*/

Route::prefix('sales')->name('sales.')->group(function () {

    Route::get('/dashboard', [SalesController::class, 'dashboard'])->name('dashboard');

    Route::get('/logistik', [SalesController::class, 'logistik'])->name('logistik');

    Route::get('/gudang/ontime', [SalesController::class, 'gudangOntime'])->name('gudang.ontime');
    Route::get('/gudang/delay', [SalesController::class, 'gudangDelay'])->name('gudang.delay');

    Route::get('/customer/ontime', [SalesController::class, 'tujuanOntime'])->name('customer.ontime');
    Route::get('/customer/delay', [SalesController::class, 'tujuanDelay'])->name('customer.delay');

    Route::get('/bongkar/ontime', [SalesController::class, 'bongkarOntime'])->name('bongkar.ontime');
    Route::get('/bongkar/delay', [SalesController::class, 'bongkarDelay'])->name('bongkar.delay');

    Route::get('/summary-area', [SalesController::class, 'summaryArea'])->name('summary.area');
});

/*
|--------------------------------------------------------------------------
| SPV PLANNER
|--------------------------------------------------------------------------
*/

Route::prefix('spvplanner')->name('spvplanner.')->group(function () {

    Route::get('/dashboard', [SpvPlannerController::class, 'dashboard'])->name('dashboard');
    Route::get('/dashboard-full', [SpvPlannerController::class, 'dashboardFull'])->name('dashboard.full');

    Route::get('/datalogistik', [SpvPlannerController::class, 'dataLogistik'])->name('datalogistik');

    Route::get('/sla-ontime', [SpvPlannerController::class, 'slaOntime'])->name('sla.ontime');
    Route::get('/sla-delay', [SpvPlannerController::class, 'slaDelay'])->name('sla.delay');

    Route::get('/summary-area', [SpvPlannerController::class, 'summaryArea'])->name('summary.area');

    Route::get('/armada', [SpvPlannerController::class, 'armada'])->name('armada');
    Route::get('/belum-armada', [SpvPlannerController::class, 'belumArmada'])->name('belum.armada');

    Route::get('/full-data-logistik', [SpvPlannerController::class, 'fullDataLogistik'])
        ->name('full.data.logistik');
});

/*
|--------------------------------------------------------------------------
| SPV MONITORING
|--------------------------------------------------------------------------
*/

Route::prefix('spvmonitoring')->name('spvmonitoring.')->group(function () {

    Route::get('/dashboard', [SpvMonitoringController::class, 'dashboard'])->name('dashboard');

    Route::get('/datalogistik', [SpvMonitoringController::class, 'dataLogistik'])->name('datalogistik');

    Route::put('/update/{id}', [SpvMonitoringController::class, 'update'])->name('update');

    Route::get('/sla-ontime', [SpvMonitoringController::class, 'slaOntime'])->name('sla.ontime');
    Route::get('/sla-delay', [SpvMonitoringController::class, 'slaDelay'])->name('sla.delay');

    Route::get('/bongkar-ontime', [SpvMonitoringController::class, 'bongkarOntime'])->name('bongkar.ontime');
    Route::get('/bongkar-delay', [SpvMonitoringController::class, 'bongkarDelay'])->name('bongkar.delay');

    Route::get('/summary-area', [SpvMonitoringController::class, 'summaryArea'])->name('summary.area');

    Route::get('/full-data-logistik', [SpvMonitoringController::class, 'fullDataLogistik'])
        ->name('full.data.logistik');
});

/*
|--------------------------------------------------------------------------
| USER MANAGEMENT
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:manager,spvplanner,spvmonitoring,developer'])->group(function () {

    Route::resource('users', UserController::class);
});

/*
|--------------------------------------------------------------------------
| STORAGE
|--------------------------------------------------------------------------
*/

Route::get('/storage', [StorageController::class, 'index']);
Route::get('/storage/export', [StorageController::class, 'export']);
Route::delete('/storage/delete-all', [StorageController::class, 'deleteAll']);

/*
|--------------------------------------------------------------------------
| DEVELOPER
|--------------------------------------------------------------------------
*/

Route::prefix('developer')->name('developer.')->group(function () {

    Route::get('/dashboard', [DeveloperController::class, 'dashboard'])->name('dashboard');
    Route::get('/planner', [DeveloperController::class, 'dataLogistik'])->name('planner');

    Route::get('/monitoring', fn() => redirect()->route('monitoring.dashboard'))->name('monitoring');
    Route::get('/manager', fn() => redirect()->route('manager.summary.total'))->name('manager');
    Route::get('/spvplanner', fn() => redirect()->route('spvplanner.dashboard'))->name('spvplanner');
    Route::get('/spvmonitoring', fn() => redirect()->route('spvmonitoring.dashboard'))->name('spvmonitoring');
    Route::get('/sales', fn() => redirect()->route('sales.dashboard'))->name('sales');
});

/*
|--------------------------------------------------------------------------
| FALLBACK
|--------------------------------------------------------------------------
*/

Route::fallback(fn() => redirect('/login'));