<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\ApiDocsController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EngineeringController;
use App\Http\Controllers\LabController;
use App\Http\Controllers\MethodController;
use App\Http\Controllers\RegionController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('home');
Route::get('dashboard', DashboardController::class)->name('dashboard');

Route::get('regions', [RegionController::class, 'index'])->name('regions.index');
Route::get('regions/{region}', CalendarController::class)->name('regions.show');
Route::get('regions/{region}/lab', LabController::class)->name('regions.lab');

Route::get('alerts', AlertController::class)->name('alerts.index');
Route::get('method', MethodController::class)->name('method');
Route::get('how-it-works', EngineeringController::class)->name('engineering');
Route::get('api-docs', ApiDocsController::class)->name('api-docs');

Route::middleware('auth')->group(function () {
    Route::get('my/regions/new', [RegionController::class, 'create'])->name('regions.create');
    Route::post('regions', [RegionController::class, 'store'])->name('regions.store');
    Route::get('regions/{region}/edit', [RegionController::class, 'edit'])->name('regions.edit');
    Route::put('regions/{region}', [RegionController::class, 'update'])->name('regions.update');
    Route::delete('regions/{region}', [RegionController::class, 'destroy'])->name('regions.destroy');
    Route::post('regions/{region}/harmonize', [RegionController::class, 'harmonize'])->name('regions.harmonize');
    Route::post('regions/{region}/backfill', [RegionController::class, 'backfill'])->name('regions.backfill');
});

require __DIR__.'/settings.php';
