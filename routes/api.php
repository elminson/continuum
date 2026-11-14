<?php

use App\Http\Controllers\Api\V1\AlertController;
use App\Http\Controllers\Api\V1\CalendarController;
use App\Http\Controllers\Api\V1\CalibrationController;
use App\Http\Controllers\Api\V1\CriticalPeriodController;
use App\Http\Controllers\Api\V1\DetectionController;
use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\HotspotExportController;
use App\Http\Controllers\Api\V1\ModisRetirementController;
use App\Http\Controllers\Api\V1\RegionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->middleware('throttle:api')->group(function () {
    Route::get('regions', [RegionController::class, 'index'])->name('regions.index');
    Route::get('regions/{region}', [RegionController::class, 'show'])->name('regions.show');
    Route::get('regions/{region}/calendar', CalendarController::class)->name('regions.calendar');
    Route::get('regions/{region}/calibration', CalibrationController::class)->name('regions.calibration');
    Route::get('regions/{region}/alerts', AlertController::class)->name('regions.alerts');
    Route::get('regions/{region}/critical-periods', CriticalPeriodController::class)->name('regions.critical-periods');
    Route::get('regions/{region}/modis-retirement', ModisRetirementController::class)->name('regions.modis-retirement');
    Route::get('regions/{region}/export', ExportController::class)->name('regions.export');
    Route::get('regions/{region}/hotspots.csv', HotspotExportController::class)->name('regions.hotspots');
    Route::get('hotspots', DetectionController::class)->name('hotspots');
});
