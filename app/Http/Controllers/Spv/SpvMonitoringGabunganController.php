<?php

namespace App\Http\Controllers\Spv;

use App\Http\Controllers\Manager\GabunganController;

class SpvMonitoringGabunganController extends GabunganController
{
    protected string $listRoute      = 'spvmonitoring.gabungan.list';
    protected string $inTransitRoute = 'spvmonitoring.gabungan.intransit';
    protected string $plannerRoute    = 'spvplanner.datalogistik';
protected string $monitoringRoute = 'spvmonitoring.datalogistik';
}