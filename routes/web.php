<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;


/*
|--------------------------------------------------------------------------
| CONTROLLER
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Spv\TarifPengirimanController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LogistikController;
use App\Http\Controllers\Planner\PlannerController;
use App\Http\Controllers\Monitoring\MonitoringController;
use App\Http\Controllers\Manager\ManagerController;
use App\Http\Controllers\PasuruanController;
use App\Http\Controllers\Spv\SpvPlannerController;
use App\Http\Controllers\Spv\SpvMonitoringController;
use App\Http\Controllers\Developer\DeveloperController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\StorageController;
use App\Http\Controllers\Cmd\CmdController;
use App\Http\Controllers\Spv\TujuanFilterController;
use App\Http\Controllers\Sales\SalesController;
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
})->name('logout');
/*
|--------------------------------------------------------------------------
| EMAIL VERIFICATION
|--------------------------------------------------------------------------
*/

// Halaman "cek email kamu"
Route::get('/email/verify', function () {
    return view('auth.verify-email');
})->middleware('auth')->name('verification.notice');

// Link yang diklik dari email
Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill(); // otomatis tandai email_verified_at
    return redirect('/login')->with('success', 'Email berhasil diverifikasi, silakan login.');
})->middleware(['auth', 'signed'])->name('verification.verify');

// Tombol "kirim ulang email verifikasi"
Route::post('/email/verification-notification', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();
    return back()->with('message', 'Link verifikasi baru sudah dikirim!');
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');

/*
|--------------------------------------------------------------------------
| LOGISTIK
|--------------------------------------------------------------------------
*/
Route::get('/logistik/export', [LogistikController::class, 'export'])
    ->name('logistik.export');
Route::get('/dashboard', [LogistikController::class, 'dashboard']);
Route::get('/logistik', [LogistikController::class, 'index']);
Route::get('/datalogistik', [LogistikController::class, 'dataLogistik']);
Route::get('/datalogistik', [LogistikController::class, 'dataLogistik'])->name('logistik.page');

// SESUDAH — terima POST juga (GET tetap boleh dipertahankan buat kompatibilitas)
Route::match(['get', 'post'], '/datalogistik/ajax', [LogistikController::class, 'dataLogistikAjax'])
    ->name('logistik.ajax');

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

Route::post('/planner/data-ajax', [App\Http\Controllers\Planner\PlannerController::class, 'dataAjax'])
    ->name('planner.data.ajax');
Route::get('/planner/alerts', [App\Http\Controllers\Planner\PlannerController::class, 'alerts'])
    ->name('planner.alerts');

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

    Route::get('/planner/export', [PlannerController::class, 'exportPlanner'])
        ->name('planner.export');

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

    Route::post('/autosave-row/{id}', [PlannerController::class, 'autosaveRow']);
});

/*
|--------------------------------------------------------------------------
| MONITORING
|--------------------------------------------------------------------------
*/



Route::get(
    '/planner/data-logistik',
    [PlannerController::class, 'dataLogistik']
)->name('planner.datalogistik');

Route::get(
    '/monitoring/data-logistik',
    [MonitoringController::class, 'dataLogistik']
)->name('monitoring.datalogistik');

Route::get('/monitoring/data-ajax', [MonitoringController::class, 'dataAjax'])->name('monitoring.datalogistik.ajax');
Route::get('/monitoring/alerts', [MonitoringController::class, 'alerts'])->name('monitoring.alerts');
Route::put('/monitoring/update/{id}', [MonitoringController::class, 'updateMonitoring'])->name('monitoring.update');

Route::get(
    '/monitoring/export',
    [MonitoringController::class, 'export']
)->name('monitoring.export');
Route::put('/monitoring/update/{id}', [MonitoringController::class, 'updateMonitoring']);

Route::prefix('monitoring')->group(function () {
    Route::post('/monitoring/update-transport-laut', [MonitoringController::class, 'updateTransportLaut']);
    Route::post(
        '/update-transport-laut',
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

Route::prefix('manager')->group(function () {
    Route::get('/dashboard', [ManagerController::class, 'dashboard'])
        ->name('manager.dashboard');

        Route::match(['get', 'post'], '/manager/data-pasuruan/ajax', [
    \App\Http\Controllers\Manager\ManagerController::class,
    'dataLogistikPasuruanAjax'
])->name('manager.pasuruan.ajax');

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

    Route::get('/dashboard-pasuruan', [ManagerController::class, 'dashboardPasuruan'])
        ->name('manager.dashboard.pasuruan');

    Route::get('/manager/data-logistik-pasuruan', [ManagerController::class, 'dataLogistikPasuruan'])
        ->name('manager.data.pasuruan');
});

Route::get('/pasuruan/gudang/ontime', [PasuruanController::class, 'gudangOntimePasuruan'])
    ->name('pasuruan.gudang.ontime');

Route::get('/pasuruan/gudang/delay', [PasuruanController::class, 'gudangDelayPasuruan'])
    ->name('pasuruan.gudang.delay');

Route::get('/pasuruan/tujuan/ontime', [PasuruanController::class, 'tujuanOntimePasuruan'])
    ->name('pasuruan.tujuan.ontime');
Route::post('/pasuruan/data-ajax', [\App\Http\Controllers\PasuruanController::class, 'dataLogistikAjax'])
    ->name('pasuruan.dataAjax');
    Route::get('/datalogistik', [LogistikController::class, 'dataLogistik'])->name('logistik.page');
Route::match(['get','post'], '/datalogistik/ajax', [LogistikController::class, 'dataLogistikAjax'])->name('logistik.ajax');
Route::get('/pasuruan/data-logistik', [PasuruanController::class, 'dataLogistik'])->name('pasuruan.dataLogistik');
Route::match(['get', 'post'], '/pasuruan/data-logistik/ajax', [PasuruanController::class, 'dataLogistikAjaxPasuruan'])->name('pasuruan.dataLogistikAjax');

Route::get('/pasuruan/tujuan/delay', [PasuruanController::class, 'tujuanDelayPasuruan'])
    ->name('pasuruan.tujuan.delay');

Route::get('/pasuruan/bongkar/ontime', [PasuruanController::class, 'bongkarOntimePasuruan'])
    ->name('pasuruan.bongkar.ontime');

Route::get('/pasuruan/bongkar/delay', [PasuruanController::class, 'bongkarDelayPasuruan'])
    ->name('pasuruan.bongkar.delay');

/*
|--------------------------------------------------------------------------
| USER
|--------------------------------------------------------------------------
*/

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
    Route::get('/dashboard-pasuruan', [SalesController::class, 'dashboardPasuruan'])
        ->name('dashboard.pasuruan');

    Route::get('/data-logistik-pasuruan', [SalesController::class, 'dataLogistikPasuruan'])
        ->name('data.pasuruan');
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

        Route::get('/pasuruan/gudang/ontime', [SalesController::class, 'gudangOntimePasuruan'])
        ->name('pasuruan.gudang.ontime');
    Route::get('/pasuruan/gudang/delay', [SalesController::class, 'gudangDelayPasuruan'])
        ->name('pasuruan.gudang.delay');
    Route::get('/pasuruan/tujuan/ontime', [SalesController::class, 'tujuanOntimePasuruan'])
        ->name('pasuruan.tujuan.ontime');
    Route::get('/pasuruan/tujuan/delay', [SalesController::class, 'tujuanDelayPasuruan'])
        ->name('pasuruan.tujuan.delay');
    Route::get('/pasuruan/bongkar/ontime', [SalesController::class, 'bongkarOntimePasuruan'])
        ->name('pasuruan.bongkar.ontime');
    Route::get('/pasuruan/bongkar/delay', [SalesController::class, 'bongkarDelayPasuruan'])
        ->name('pasuruan.bongkar.delay');
    Route::get('/pasuruan/summary-area', [SalesController::class, 'summaryAreaPasuruan'])
        ->name('pasuruan.summary.area');
        Route::get('/pasuruan/data-ajax', [PasuruanController::class, 'dataAjaxPasuruan'])
    ->name('pasuruan.dataAjax');
  Route::post('/data-logistik-pasuruan/ajax', [SalesController::class, 'dataLogistikPasuruanAjax'])
    ->name('data.pasuruan.ajax');
});

Route::middleware(['auth'])->prefix('spvplanner')->group(function () {

Route::post('/spvplanner/data-ajax', [App\Http\Controllers\Spv\SpvPlannerController::class, 'dataAjax'])
    ->name('spvplanner.data.ajax');
    Route::match(['get', 'post'], '/logistik/full-data/ajax', [SpvPlannerController::class, 'fullDataLogistikAjax'])
    ->name('full.data.logistik.ajax');
    Route::get('/data-logistik-pasuruan', [SpvPlannerController::class, 'dataLogistikPasuruan'])
        ->name('spvplanner.data.pasuruan');

    // route baru untuk AJAX DataTables
  Route::post('/data-logistik-pasuruan/ajax', [SpvPlannerController::class, 'dataLogistikPasuruanAjax'])
    ->name('spvplanner.data.pasuruan.ajax');
Route::get('/spvplanner/alerts', [App\Http\Controllers\Spv\SpvPlannerController::class, 'alerts'])
    ->name('spvplanner.alerts');
Route::post('/autosave-row/{id}', [App\Http\Controllers\Spv\SpvPlannerController::class, 'autosaveRow'])
    ->name('spvplanner.autosave-row'); // ✅ BENAR — tanpa prefix manual

    Route::post('/spvplanner/tujuan-filter/bulk-destroy', [TujuanFilterController::class, 'bulkDestroy'])
    ->name('spvplanner.tujuan.bulk-destroy');

    Route::post('/tujuan/bulk-update', [TujuanFilterController::class, 'bulkUpdate'])
    ->name('spvplanner.tujuan.bulk-update');

Route::delete('/spvplanner/tujuan-filter/destroy-all', [TujuanFilterController::class, 'destroyAll'])
    ->name('spvplanner.tujuan.destroy-all');

Route::post('/spvplanner/data-planner/delete-filtered', [SpvPlannerController::class, 'deleteFiltered'])
    ->name('spvplanner.deleteFiltered');

Route::post('/spvplanner/data-pasuruan/delete-filtered', [SpvPlannerController::class, 'deleteFilteredPasuruan'])
    ->name('spvplanner.pasuruan.deleteFiltered');

    Route::get('/dashboard', [SpvPlannerController::class, 'dashboard'])
        ->name('spvplanner.dashboard');

    Route::get('/dashboard-full', [SpvPlannerController::class, 'dashboardFull'])
        ->name('spvplanner.dashboard.full');
 Route::get('tujuan-filter', [TujuanFilterController::class, 'index'])->name('tujuan.index');
       Route::get('tujuan-filter', [TujuanFilterController::class, 'index'])->name('spvplanner.tujuan.index');
Route::get('tujuan-filter/create', [TujuanFilterController::class, 'create'])->name('spvplanner.tujuan.create');
Route::post('tujuan-filter', [TujuanFilterController::class, 'store'])->name('spvplanner.tujuan.store');
Route::get('tujuan-filter/{id}/edit', [TujuanFilterController::class, 'edit'])->name('spvplanner.tujuan.edit');
Route::put('tujuan-filter/{id}', [TujuanFilterController::class, 'update'])->name('spvplanner.tujuan.update');
Route::delete('tujuan-filter/{id}', [TujuanFilterController::class, 'destroy'])->name('spvplanner.tujuan.destroy');
Route::post('tujuan-filter/import', [TujuanFilterController::class, 'import'])->name('spvplanner.tujuan.import');


    Route::get('/spvplanner/dashboard', [SpvPlannerController::class, 'dashboard'])
        ->name('spvplanner.dashboard');

    // Dashboard Pasuruan
    Route::get('/spvplanner/dashboard-pasuruan', [SpvPlannerController::class, 'dashboardPasuruan'])
        ->name('spvplanner.dashboard.pasuruan');

    Route::post('/import-pasuruan', [SpvPlannerController::class, 'importPasuruan'])
        ->name('spvplanner.import.pasuruan');

    Route::get('/export-pasuruan', [SpvPlannerController::class, 'exportPasuruan'])
        ->name('spvplanner.export.pasuruan');

    // Data Logistik Jakarta
    Route::get('/spvplanner/data-logistik', [SpvPlannerController::class, 'dataLogistik'])
        ->name('spvplanner.data');

    // Data Logistik Pasuruan
    Route::get('/spvplanner/data-logistik-pasuruan', [SpvPlannerController::class, 'dataLogistikPasuruan'])
        ->name('spvplanner.data.pasuruan');

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
    Route::delete('/delete/{id}', [SpvPlannerController::class, 'delete'])
        ->name('spvplanner.delete');
    Route::get(
        '/full-data-logistik',
        [SpvPlannerController::class, 'fullDataLogistik']
    )->name('full.data.logistik');
    Route::get('/full-data-logistik', [SpvPlannerController::class, 'fullDataLogistik'])
        ->name('full.data.logistik');
    Route::post(
        '/spvplanner/update-gudang23',
        [PlannerController::class, 'updateGudang23']
    )->name('spvplanner.updateGudang23');
    Route::post('/archive', [SpvPlannerController::class, 'archive'])
        ->name('spvplanner.archive');
    Route::get(
        '/data-logistik-pasuruan',
        [SpvPlannerController::class, 'dataLogistikPasuruan']
    )->name('data.pasuruan');
Route::get('/tarif-pengiriman', [TarifPengirimanController::class, 'index'])
    ->name('spvplanner.tarif.index');

  Route::post('/tarif-pengiriman', [TarifPengirimanController::class, 'store'])
    ->name('spvplanner.tarif.store');

Route::get('/tarif-pengiriman/create', [TarifPengirimanController::class, 'create'])
    ->name('spvplanner.tarif.create');

Route::get('/tarif-pengiriman/{id}/edit', [TarifPengirimanController::class, 'edit'])
    ->name('spvplanner.tarif.edit');

Route::put('/tarif-pengiriman/{id}', [TarifPengirimanController::class, 'update'])
    ->name('spvplanner.tarif.update');

Route::delete('/tarif-pengiriman/{id}', [TarifPengirimanController::class, 'destroy'])
    ->name('spvplanner.tarif.destroy');



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

       Route::get('tujuan-filter', [TujuanFilterController::class, 'index'])->name('tujuan.index');
Route::get('tujuan-filter/create', [TujuanFilterController::class, 'create'])->name('tujuan.create');
Route::post('tujuan-filter', [TujuanFilterController::class, 'store'])->name('tujuan.store');
Route::get('tujuan-filter/{id}/edit', [TujuanFilterController::class, 'edit'])->name('tujuan.edit');
Route::put('tujuan-filter/{id}', [TujuanFilterController::class, 'update'])->name('tujuan.update');
Route::delete('tujuan-filter/{id}', [TujuanFilterController::class, 'destroy'])->name('tujuan.destroy');
Route::post('tujuan-filter/import', [TujuanFilterController::class, 'import'])->name('tujuan.import');


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

Route::get('/spvmonitoring/dashboard-pasuruan', [SpvMonitoringController::class, 'dashboardPasuruan'])
    ->name('spvmonitoring.dashboard.pasuruan');

Route::get('/spvmonitoring/data-logistik-pasuruan', [SpvMonitoringController::class, 'dataLogistikPasuruan'])
    ->name('spvmonitoring.data.pasuruan');


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




Route::prefix('sales')
    ->as('sales.')
    ->group(function () {

        // DASHBOARD
        Route::get('/dashboard', [SalesController::class, 'dashboard'])
            ->name('dashboard');

        // GUDANG
        Route::get('/gudang/ontime', [SalesController::class, 'gudangOntime'])
            ->name('gudang.ontime');

        Route::get('/gudang/delay', [SalesController::class, 'gudangDelay'])
            ->name('gudang.delay');

        // CUSTOMER
        Route::get(
            '/customer/ontime',
            [SalesController::class, 'tujuanOntime']
        )
            ->name('customer.ontime');

        Route::get(
            '/customer/delay',
            [SalesController::class, 'tujuanDelay']
        )
            ->name('customer.delay');

        // BONGKAR
        Route::get('/bongkar/ontime', [SalesController::class, 'bongkarOntime'])
            ->name('bongkar.ontime');

        Route::get('/bongkar/delay', [SalesController::class, 'bongkarDelay'])
            ->name('bongkar.delay');

        // SUMMARY
        Route::get('/summary/area', [SalesController::class, 'summaryArea'])
            ->name('summary.area');

        Route::get('/summary/total', [SalesController::class, 'summaryTotal'])
            ->name('summary.total');
    });

/*
|--------------------------------------------------------------------------
| FALLBACK
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\LogistikPengiriman2Controller;

// Route::prefix('pasuruan')->group(function () {

//     // Halaman Admin
//     Route::get('/admin', [PasuruanController::class, 'admin'])
//         ->name('pasuruan.admin');
//        Route::post('/pasuruan/data-ajax-pasuruan', [PasuruanController::class, 'dataAjaxPasuruan'])
//     ->name('pasuruan.dataAjaxPasuruan');
// Route::get('/pasuruan/list-no-shipment', [PasuruanController::class, 'listNoShipmentPasuruan'])
//     ->name('pasuruan.listNoShipment');

//     // Halaman Data Logistik Pasuruan
//     Route::get('/data-logistik', [PasuruanController::class, 'dataLogistik'])
//         ->name('pasuruan.dataLogistik');

//     // CRUD
//     Route::post('/store', [PasuruanController::class, 'store'])
//         ->name('pasuruan.store');
// Route::put('/update/{id}', [PasuruanController::class, 'update'])
//     ->name('pasuruan.updateRow');
//     Route::put('/{id}', [PasuruanController::class, 'update'])
//         ->name('pasuruan.update');

//     Route::delete('/{id}', [PasuruanController::class, 'destroy'])
//         ->name('pasuruan.destroy');

//     // Autosave
//     Route::put('/autosave-row/{id}', [PasuruanController::class, 'autosaveRow'])
//         ->name('pasuruan.autosaveRow');

//     Route::get('/dashboard', [PasuruanController::class, 'dashboard'])
//         ->name('pasuruan.dashboard');

//     // IMPORT
//     Route::post('/import', [PasuruanController::class, 'import'])
//         ->name('pasuruan.import');

//     // EXPORT
//     Route::get('/export', [PasuruanController::class, 'export'])
//         ->name('pasuruan.export');

//     // ARCHIVE
//     Route::post('/archive-all', [PasuruanController::class, 'archiveAll'])
//         ->name('pasuruan.archive');

//     Route::post(
//         '/pasuruan/update-transport-laut',
//         [PasuruanController::class, 'updateTransportLaut']
//     )->name('pasuruan.updateTransportLaut');
// });

Route::prefix('pasuruan')->group(function () {

    Route::get('/admin', [PasuruanController::class, 'admin'])
        ->name('pasuruan.admin');

    Route::post('/data-ajax-pasuruan', [PasuruanController::class, 'dataAjaxPasuruan'])
        ->name('pasuruan.dataAjaxPasuruan');

    Route::get('/list-no-shipment', [PasuruanController::class, 'listNoShipmentPasuruan'])
        ->name('pasuruan.listNoShipment');

    Route::get('/data-logistik', [PasuruanController::class, 'dataLogistik'])
        ->name('pasuruan.dataLogistik');

    // CRUD
    Route::post('/store', [PasuruanController::class, 'store'])
        ->name('pasuruan.store');

    Route::put('/{id}', [PasuruanController::class, 'update'])
        ->name('pasuruan.update');

    Route::delete('/{id}', [PasuruanController::class, 'destroy'])
        ->name('pasuruan.destroy');

    // Autosave
    Route::put('/autosave-row/{id}', [PasuruanController::class, 'autosaveRow'])
        ->name('pasuruan.autosaveRow');

    Route::get('/dashboard', [PasuruanController::class, 'dashboard'])
        ->name('pasuruan.dashboard');

    // Import / Export
    Route::post('/import', [PasuruanController::class, 'import'])
        ->name('pasuruan.import');

    Route::get('/export', [PasuruanController::class, 'export'])
        ->name('pasuruan.export');

    Route::post('/archive-all', [PasuruanController::class, 'archiveAll'])
        ->name('pasuruan.archive');

    Route::post('/update-transport-laut', [PasuruanController::class, 'updateTransportLaut'])
        ->name('pasuruan.updateTransportLaut');
});

Route::fallback(fn() => redirect('/login'));