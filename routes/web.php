<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| CONTROLLER
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
use App\Http\Controllers\Cmd\CmdController;
use App\Http\Controllers\Jess\JessController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SummaryController;


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
| LOGISTIK
|--------------------------------------------------------------------------
*/

Route::get('/dashboard', [LogistikController::class, 'dashboard']);
Route::get('/logistik', [LogistikController::class, 'index']);
Route::get('/datalogistik', [LogistikController::class, 'dataLogistik']);

Route::post('/logistik/store', [LogistikController::class, 'store']);
Route::post('/logistik/update/{id}', [LogistikController::class, 'update']);
Route::get('/logistik/delete/{id}', [LogistikController::class, 'delete']);
Route::post('/logistik/import', [LogistikController::class, 'import']);

Route::get('/export', [LogistikController::class, 'export']);
Route::get('/chart/status', [LogistikController::class, 'chartStatus']);

/*
|--------------------------------------------------------------------------
| PLANNER (FIXED + CLEAN PREFIX)
|--------------------------------------------------------------------------
*/

Route::prefix('planner')->group(function () {

    Route::get('/dashboard', [PlannerController::class, 'dashboard'])
        ->name('planner.dashboard');
        Route::post('/planner/store', [PlannerController::class, 'store'])
    ->name('planner.store');

    Route::get('/sla-ontime', [PlannerController::class, 'slaOntime'])
        ->name('planner.sla.ontime');

    Route::get('/sla-delay', [PlannerController::class, 'slaDelay'])
        ->name('planner.sla.delay');

    Route::get('/armada', [PlannerController::class, 'armada'])
        ->name('armada');
        Route::get('/armada-delay', [PlannerController::class, 'armadaDelay'])
        ->name('armada.delay');
 Route::get('/planner/belum-armada', [PlannerController::class, 'belumArmada'])
    ->name('planner.belum_armada');

   

    // Route::get('/data', [PlannerController::class, 'index'])
    //     ->name('index');
Route::get('/data', [PlannerController::class, 'dataLogistik'])
    ->name('planner.data');
    Route::put('/update/{id}', [PlannerController::class, 'update'])
        ->name('planner.update');
Route::get('/planner/delete/{id}', [PlannerController::class, 'delete'])
    ->name('planner.delete');
    

Route::post(
    '/planner/update-gudang23',
    [PlannerController::class, 'updateGudang23']
)->name('planner.updateGudang23');

Route::post('/planner/autosave-row/{id}', [PlannerController::class, 'autosaveRow']);

});

/*
|--------------------------------------------------------------------------
| MONITORING
|--------------------------------------------------------------------------
*/


   
Route::get(
    '/planner/data-logistik',
    [PlannerController::class, 'dataLogistik']
)
    ->name('planner.datalogistik');

Route::get(
    '/monitoring/data-logistik',
    [MonitoringController::class, 'dataLogistik']
)
    ->name('monitoring.datalogistik');

Route::put('/monitoring/update/{id}', [MonitoringController::class, 'updateMonitoring']);

Route::prefix('monitoring')->group(function () {
    Route::post('/monitoring/update-transport-laut', [MonitoringController::class, 'updateTransportLaut']);
    Route::post('/update-transport-laut', 
        [MonitoringController::class, 'updateTransportLaut']
    )->name('monitoring.update-transport-laut');

    Route::get('/dashboard', [MonitoringController::class, 'dashboard'])
        ->name('monitoring.dashboard');

  
    // ================= SLA =================
    Route::get('/sla-ontime', [MonitoringController::class, 'slaOntime'])
        ->name('monitoring.sla.ontime');

    Route::get('/sla-delay', [MonitoringController::class, 'slaDelay'])
        ->name('monitoring.sla.delay');

    // ================= BONGKAR =================
    Route::get('/bongkar/ontime', [MonitoringController::class, 'bongkarOnTime'])
        ->name('monitoring.bongkar.ontime');

    Route::get('/bongkar/delay', [MonitoringController::class, 'bongkarDelay'])
        ->name('monitoring.bongkar.delay');

    // ================= AREA SUMMARY =================
    Route::get('/monitoring/summary-area', [MonitoringController::class, 'summaryArea'])
        ->name('monitoring.summary.area');

    Route::get('/monitoring/summary-area/detail', [MonitoringController::class, 'summaryAreaDetail'])
        ->name('monitoring.summary.area.detail');
});

// Route::prefix('monitoring')->group(function () { (sebelum monitoring baru)

//     Route::get('/dashboard', [MonitoringController::class, 'dashboard'])
//         ->name('monitoring.dashboard');

//     Route::get('/sla-ontime', [MonitoringController::class, 'slaOntime'])
//         ->name('monitoring.sla.ontime');

//     Route::get('/sla-delay', [MonitoringController::class, 'slaDelay'])
//         ->name('monitoring.sla.delay');

//     // ================= BONGKAR =================
//     Route::get('/bongkar/ontime', [MonitoringController::class, 'bongkarOntime'])
//         ->name('monitoring.bongkar.ontime');

//     Route::get('/bongkar/delay', [MonitoringController::class, 'bongkarDelay'])
//         ->name('monitoring.bongkar.delay');
// });

Route::prefix('manager')->group(function () {
    Route::get('/dashboard', [ManagerController::class, 'dashboard'])
        ->name('manager.dashboard');

    // ================= GUDANG =================
    Route::get('/gudang/ontime', [ManagerController::class, 'gudangOnTime'])
        ->name('manager.gudang.ontime');

    Route::get('/gudang/delay', [ManagerController::class, 'gudangDelay'])
        ->name('manager.gudang.delay');

    // ================= CUSTOMER (TUJUAN) =================
    Route::get('/customer/ontime', [ManagerController::class, 'tujuanOnTime'])
        ->name('manager.customer.ontime');

    Route::get('/customer/delay', [ManagerController::class, 'tujuanDelay'])
        ->name('manager.customer.delay');

    // ================= BONGKAR =================
    Route::get('/bongkar/ontime', [ManagerController::class, 'bongkarOnTime'])
        ->name('manager.bongkar.ontime');

    Route::get('/bongkar/delay', [ManagerController::class, 'bongkarDelay'])
        ->name('manager.bongkar.delay');

    // ================= SUMMARY =================
    Route::get('/summary-total', [ManagerController::class, 'summaryTotal'])
        ->name('manager.summary.total');

    Route::get('/summary-area', [ManagerController::class, 'summaryArea'])
        ->name('manager.summary.area');
});
/*
|--------------------------------------------------------------------------
| MANAGER (AUTH REQUIRED)
|--------------------------------------------------------------------------
*/

// Route::middleware(['auth'])->prefix('manager')->group(function () {

//     Route::get('/dashboard', [ManagerController::class, 'dashboard'])
//         ->name('manager.dashboard');

//     Route::get('/gudang', [ManagerController::class, 'gudang'])
//         ->name('manager.gudang');

//     Route::get('/gudang/ontime', [ManagerController::class, 'gudangOnTime'])
//         ->name('manager.gudang.ontime');

//     Route::get('/gudang/delay', [ManagerController::class, 'gudangDelay'])
//         ->name('manager.gudang.delay');

//     Route::get('/customer', [ManagerController::class, 'customer'])
//         ->name('manager.customer');

//     Route::get('/customer/ontime', [ManagerController::class, 'customerOnTime'])
//         ->name('manager.customer.ontime');

//     Route::get('/customer/delay', [ManagerController::class, 'customerDelay'])
//         ->name('manager.customer.delay');

//     Route::get('/bongkar', [ManagerController::class, 'bongkar'])
//         ->name('manager.bongkar');

//     Route::get('/bongkar/ontime', [ManagerController::class, 'bongkarOnTime'])
//         ->name('manager.bongkar.ontime');

//     Route::get('/bongkar/delay', [ManagerController::class, 'bongkarDelay'])
//         ->name('manager.bongkar.delay');

//     Route::get('/summary-area', [ManagerController::class, 'summaryArea'])
//         ->name('manager.summary.area');

//     Route::get('/summary-total', [ManagerController::class, 'summaryTotal'])
//         ->name('manager.summary.total');

//     Route::get('/planner', [ManagerController::class, 'planner'])
//         ->name('manager.planner');

//     Route::get('/monitoring', [ManagerController::class, 'monitoring'])
//         ->name('manager.monitoring');

// });

/*
|--------------------------------------------------------------------------
| USER
|--------------------------------------------------------------------------
*/

// ================= SALES =================


Route::get('/logistik/edit/{id}', [LogistikController::class, 'edit']);
Route::post('/logistik/update/{id}', [LogistikController::class, 'update']);
Route::delete('/logistik/delete/{id}', [LogistikController::class, 'destroy']);

Route::post('/logistik/archive-all', [LogistikController::class, 'archiveAll']);
Route::get('/storage', [StorageController::class, 'index']);

Route::post(
    '/logistik/delete-all',
    [LogistikController::class, 'deleteAll']
);



Route::prefix('sales')->name('sales.')->group(function () {
Route::get('/summary-area', [SalesController::class, 'summaryArea'])
    ->name('sales.summary.area');
    Route::get(
    '/summary-area/{area}',
    [SalesController::class, 'summaryAreaDetail']
)->name('summary.area.detail');
    Route::get('/dashboard', [SalesController::class, 'dashboard'])
        ->name('dashboard');

    // DATA LOGISTIK
    Route::get('/data-logistik', [SalesController::class, 'dataLogistik'])
        ->name('datalogistik');
    // GUDANG
    Route::get('/gudang/ontime', [SalesController::class, 'gudangOntime'])
        ->name('gudang.ontime');

    Route::get('/gudang/delay', [SalesController::class, 'gudangDelay'])
        ->name('gudang.delay');

    // CUSTOMER
    Route::get('/customer/ontime', [SalesController::class, 'tujuanOntime'])
        ->name('customer.ontime');

    Route::get('/customer/delay', [SalesController::class, 'tujuanDelay'])
        ->name('customer.delay');

    // BONGKAR
    Route::get('/bongkar/ontime', [SalesController::class, 'bongkarOntime'])
        ->name('bongkar.ontime');

    Route::get('/bongkar/delay', [SalesController::class, 'bongkarDelay'])
        ->name('bongkar.delay');

    // SUMMARY
    Route::get('/summary-area', [SalesController::class, 'summaryArea'])
        ->name('summary.area');

    Route::get('/sales/customer/ontime', [SalesController::class, 'ontime'])
        ->name('customer.ontime');
});

Route::middleware(['auth'])->prefix('spvplanner')->group(function () {

    Route::get('/dashboard', [SpvPlannerController::class, 'dashboard'])
        ->name('spvplanner.dashboard');

    Route::get('/dashboard-full', [SpvPlannerController::class, 'dashboardFull'])
        ->name('spvplanner.dashboard.full');

Route::post('/spvplanner/store', [SpvPlannerController::class, 'store'])
    ->name('spvplanner.store');
     Route::put('spvplanner/update/{id}', [SpvPlannerController::class, 'update'])
    ->name('spvplanner.update');
    Route::get('/datalogistik', [SpvPlannerController::class, 'dataLogistik'])
        ->name('spvplanner.datalogistik');

    Route::get('/sla-ontime', [SpvPlannerController::class, 'slaOntime'])
        ->name('spvplanner.sla.ontime');

    Route::get('/sla-delay', [SpvPlannerController::class, 'slaDelay'])
        ->name('spvplanner.sla.delay');

    Route::get('/summary-area', [SpvPlannerController::class, 'summaryArea'])
        ->name('spvplanner.summary.area');

    Route::get('/armada', [SpvPlannerController::class, 'armada'])
        ->name('spvplanner.armada');

    Route::get('/belum-armada', [SpvPlannerController::class, 'belumArmada'])
        ->name('spvplanner.belum.armada');
        Route::delete('/delete/{id}', [SpvPlannerController::class, 'destroy'])
    ->name('spvplanner.delete');
    Route::get(
        '/full-data-logistik',
        [SpvPlannerController::class, 'fullDataLogistik']
    )->name('full.data.logistik');
    Route::post(
    '/spvplanner/update-gudang23',
    [PlannerController::class, 'updateGudang23']
)->name('spvplanner.updateGudang23');
});
Route::middleware(['auth'])
    ->prefix('spvmonitoring')
    ->name('spvmonitoring.')
    ->group(function () {
        Route::put(
            '/spvmonitoring/update/{id}',
            [SpvMonitoringController::class, 'update']
        )->name('spvmonitoring.update');
        Route::get('/dashboard', [SpvMonitoringController::class, 'dashboard'])
            ->name('dashboard');
            


        Route::get('/datalogistik', [SpvMonitoringController::class, 'dataLogistik'])
            ->name('datalogistik');

        Route::get('/sla-ontime', [SpvMonitoringController::class, 'slaOntime'])
            ->name('sla.ontime');

        Route::get('/sla-delay', [SpvMonitoringController::class, 'slaDelay'])
            ->name('sla.delay');

        Route::get('/bongkar-ontime', [SpvMonitoringController::class, 'bongkarOntime'])
            ->name('bongkar.ontime');

        Route::get('/bongkar-delay', [SpvMonitoringController::class, 'bongkarDelay'])
            ->name('bongkar.delay');

        Route::get('/summary-area', [SpvMonitoringController::class, 'summaryArea'])
            ->name('summary.area');

        Route::get(
            '/full-data-logistik',
            [SpvMonitoringController::class, 'fullDataLogistik']
        )->name('full.data.logistik');
    });

// Route::middleware(['auth', 'role:developer'])->group(function () {

//     Route::get('/developer/dashboard', [DeveloperController::class, 'dashboard'])
//         ->name('developer.dashboard');

//     Route::get('/developer/planner', [DeveloperController::class, 'dataLogistik'])
//         ->name('developer.planner');

//     Route::get('/developer/monitoring', fn () => redirect()->route('monitoring.dashboard'))
//         ->name('developer.monitoring');

//     Route::get('/developer/manager', fn () => redirect()->route('manager.dashboard'))
//         ->name('developer.manager');

//     Route::get('/developer/spvplanner', fn () => redirect()->route('spvplanner.dashboard'))
//         ->name('developer.spvplanner');

//     Route::get('/developer/spvmonitoring', fn () => redirect()->route('spvmonitoring.dashboard'))
//         ->name('developer.spvmonitoring');

//     Route::get('/developer/sales', fn () => redirect()->route('sales.dashboard'))
//         ->name('developer.sales');

//         Route::get(
//     '/sales/customer/ontime',
//     [SalesController::class, 'ontime']
// );


Route::prefix('developer')->group(function () {

    Route::get('/dashboard', [DeveloperController::class, 'dashboard'])
        ->name('developer.dashboard');

    Route::get('/planner', [DeveloperController::class, 'dataLogistik'])
        ->name('developer.planner');

    Route::get('/monitoring', fn() => redirect()->route('monitoring.dashboard'))
        ->name('developer.monitoring');

    Route::get('/manager', fn() => redirect()->route('manager.dashboard'))
        ->name('developer.manager');

    Route::get('/spvplanner', fn() => redirect()->route('spvplanner.dashboard'))
        ->name('developer.spvplanner');

    Route::get('/spvmonitoring', fn() => redirect()->route('spvmonitoring.dashboard'))
        ->name('developer.spvmonitoring');

    Route::get('/sales', fn() => redirect()->route('sales.dashboard'))
        ->name('developer.sales');
});
Route::get(
    '/users',
    [UserController::class, 'index']
)->name('users.index');
Route::get('/register', [UserController::class, 'register'])
    ->name('register');

Route::post('/register', [UserController::class, 'registerStore'])
    ->name('register.store');


Route::middleware(['auth', 'role:manager,spvplanner,spvmonitoring,developer'])->group(function () {

    Route::get(
        '/users',
        [UserController::class, 'index']
    )->name('users.index');

    Route::get(
        '/users/create',
        [UserController::class, 'create']
    )->name('users.create');

    Route::post(
        '/users',
        [UserController::class, 'store']
    )->name('users.store');

    Route::get(
        '/users/{id}/edit',
        [UserController::class, 'edit']
    )->name('users.edit');

    Route::put(
        '/users/{id}',
        [UserController::class, 'update']
    )->name('users.update');

    Route::delete(
        '/users/{id}',
        [UserController::class, 'destroy']
    )->name('users.destroy');
});
Route::get(
    '/spvplanner/full-dashboard',
    [SpvPlannerController::class, 'fullDashboard']
)->name('spvplanner.full.dashboard');


Route::get(
    '/spvmonitoring/full-dashboard',
    [SpvMonitoringController::class, 'fullDashboard']
)->name('spvmonitoring.full.dashboard');

Route::get(
    '/full-data-logistik',
    [SpvMonitoringController::class, 'fullDataLogistik']
)->name('full.data.logistik');

Route::get(
    '/full-data-logistik',
    [SpvPlannerController::class, 'fullDataLogistik']
)->name('spvplanner.full.data.logistik');


Route::get('/storage/export', [StorageController::class, 'export']);

Route::delete(
    '/storage/delete-all',
    [StorageController::class, 'deleteAll']
);

Route::put('/spvmonitoring/update/{id}', [SpvMonitoringController::class, 'update'])
    ->name('spvmonitoring.update');

// Route::get('/spvmonitoring/dashboard', [SPVMonitoringController::class, 'dashboardspvmonitoring'])
//     ->name('spvmonitoring.dashboardspvmonitoring');

// ================= SPV PLANNER =================
// Route::get('/spvplanner/dashboard', [SPVPlannerController::class, 'dashboardspvplanner'])
//     ->name('spvplanner.dashboardspvplanner');


Route::prefix('cmd')->name('cmd.')->group(function () {

    Route::get('/dashboard', [CmdController::class, 'dashboard'])
        ->name('dashboard');



    // ================= GUDANG =================
    Route::get('/gudang/ontime', [CmdController::class, 'gudangOntime'])
        ->name('gudang.ontime');

    Route::get('/gudang/delay', [CmdController::class, 'gudangDelay'])
        ->name('gudang.delay');

    // ================= CUSTOMER =================
    Route::get('/customer/ontime', [CmdController::class, 'tujuanOntime'])
        ->name('customer.ontime');

    Route::get('/customer/delay', [CmdController::class, 'tujuanDelay'])
        ->name('customer.delay');

    // ================= BONGKAR =================
    Route::get('/bongkar/ontime', [CmdController::class, 'bongkarOntime'])
        ->name('bongkar.ontime');

    Route::get('/bongkar/delay', [CmdController::class, 'bongkarDelay'])
        ->name('bongkar.delay');

    // ================= SUMMARY =================
    Route::get('/summary/area', [CmdController::class, 'summaryArea'])
        ->name('summary.area');

    Route::get('/summary/total', [CmdController::class, 'summaryTotal'])
        ->name('summary.total');

});


Route::prefix('jess')
    ->as('jess.')
    ->group(function () {

        // DASHBOARD
        Route::get('/dashboard', [JessController::class, 'dashboard'])
            ->name('dashboard');

        // GUDANG
        Route::get('/gudang/ontime', [JessController::class, 'gudangOntime'])
            ->name('gudang.ontime');

        Route::get('/gudang/delay', [JessController::class, 'gudangDelay'])
            ->name('gudang.delay');

        // CUSTOMER
    Route::get('/customer/ontime',
            [JessController::class, 'tujuanOntime'])
            ->name('customer.ontime');

        Route::get('/customer/delay',
            [JessController::class, 'tujuanDelay'])
            ->name('customer.delay');

        // BONGKAR
        Route::get('/bongkar/ontime', [JessController::class, 'bongkarOntime'])
            ->name('bongkar.ontime');

        Route::get('/bongkar/delay', [JessController::class, 'bongkarDelay'])
            ->name('bongkar.delay');

        // SUMMARY
        Route::get('/summary/area', [JessController::class, 'summaryArea'])
            ->name('summary.area');

        Route::get('/summary/total', [JessController::class, 'summaryTotal'])
            ->name('summary.total');
    });
/*
|--------------------------------------------------------------------------
| FALLBACK
|--------------------------------------------------------------------------
*/




Route::fallback(fn() => redirect('/login'));
