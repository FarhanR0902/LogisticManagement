<?php

namespace App\Http\Controllers\Spv;

use App\Http\Controllers\Manager\GabunganController;

class SpvPlannerGabunganController extends GabunganController
{
    protected string $listRoute      = 'spvplanner.gabungan.list';
    protected string $inTransitRoute = 'spvplanner.gabungan.intransit';
    protected string $plannerRoute    = 'spvplanner.datalogistik';
protected string $monitoringRoute = 'spvmonitoring.datalogistik';
}