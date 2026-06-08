<?php

use App\Http\Controllers\Api\ApiKeysController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\EnvironmentsController;
use App\Http\Controllers\Api\EvaluateController;
use App\Http\Controllers\Api\FlagsController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\SegmentsController;
use App\Http\Controllers\Api\SnapshotController;
use App\Http\Controllers\Api\WorkspacesController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('healthz', HealthController::class)->name('v1.healthz');

    Route::middleware(['api.key', 'workspace.rate-limit'])->group(function () {
        Route::get('workspaces/current', [WorkspacesController::class, 'current'])->name('v1.workspaces.current');

        // Snapshot is allowed for both server + client keys.
        Route::get('snapshot', SnapshotController::class)->name('v1.snapshot');

        // Evaluation requires a server key (rule logic is server-only).
        Route::middleware(['api.key:server'])->group(function () {
            Route::post('evaluate', EvaluateController::class)->name('v1.evaluate');

            // Environments
            Route::get('environments', [EnvironmentsController::class, 'index'])->name('v1.environments.index');
            Route::post('environments', [EnvironmentsController::class, 'store'])->name('v1.environments.store');
            Route::get('environments/{id}', [EnvironmentsController::class, 'show'])->name('v1.environments.show');
            Route::patch('environments/{id}', [EnvironmentsController::class, 'update'])->name('v1.environments.update');
            Route::delete('environments/{id}', [EnvironmentsController::class, 'destroy'])->name('v1.environments.destroy');

            // Flags
            Route::get('flags', [FlagsController::class, 'index'])->name('v1.flags.index');
            Route::post('flags', [FlagsController::class, 'store'])->name('v1.flags.store');
            Route::get('flags/{id}', [FlagsController::class, 'show'])->name('v1.flags.show');
            Route::patch('flags/{id}', [FlagsController::class, 'update'])->name('v1.flags.update');
            Route::delete('flags/{id}', [FlagsController::class, 'destroy'])->name('v1.flags.destroy');
            Route::get('flags/{id}/configurations/{environmentKey}', [FlagsController::class, 'getConfiguration'])->name('v1.flags.config.show');
            Route::put('flags/{id}/configurations/{environmentKey}', [FlagsController::class, 'putConfiguration'])->name('v1.flags.config.put');

            // Segments
            Route::get('segments', [SegmentsController::class, 'index'])->name('v1.segments.index');
            Route::post('segments', [SegmentsController::class, 'store'])->name('v1.segments.store');
            Route::get('segments/{id}', [SegmentsController::class, 'show'])->name('v1.segments.show');
            Route::patch('segments/{id}', [SegmentsController::class, 'update'])->name('v1.segments.update');
            Route::delete('segments/{id}', [SegmentsController::class, 'destroy'])->name('v1.segments.destroy');

            // API keys
            Route::get('api-keys', [ApiKeysController::class, 'index'])->name('v1.api-keys.index');
            Route::post('api-keys', [ApiKeysController::class, 'store'])->name('v1.api-keys.store');
            Route::delete('api-keys/{id}', [ApiKeysController::class, 'destroy'])->name('v1.api-keys.destroy');

            // Audit
            Route::get('audit', AuditController::class)->name('v1.audit.index');
        });
    });
});
