<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\GraduationController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\LookupController;
use App\Http\Controllers\Api\ReferenceController;
use App\Http\Controllers\Api\TraineeController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::get('dashboard', DashboardController::class);

        // Lecture : tous les rôles connectés
        Route::get('lookups', LookupController::class);
        Route::get('references', [ReferenceController::class, 'schema']);
        Route::get('references/{resource}', [ReferenceController::class, 'index']);
        Route::get('trainees', [TraineeController::class, 'index']);
        Route::get('trainees/export', [TraineeController::class, 'export']);
        Route::get('trainees/{trainee}', [TraineeController::class, 'show'])->withTrashed();
        Route::get('graduations', [GraduationController::class, 'index']);

        // Écriture des dossiers : administrateur et gestionnaire
        Route::middleware('role:administrateur,gestionnaire')->group(function () {
            Route::post('trainees', [TraineeController::class, 'store']);
            Route::put('trainees/{trainee}', [TraineeController::class, 'update']);
            Route::delete('trainees/{trainee}', [TraineeController::class, 'destroy']);
            Route::post('trainees/{trainee}/restore', [TraineeController::class, 'restore'])->withTrashed();
            Route::post('trainees/{trainee}/graduations', [GraduationController::class, 'store']);
            Route::put('graduations/{graduation}', [GraduationController::class, 'update']);
            Route::delete('graduations/{graduation}', [GraduationController::class, 'destroy']);

            Route::get('import-meta', [ImportController::class, 'meta']);
            Route::get('imports', [ImportController::class, 'index']);
            Route::post('imports', [ImportController::class, 'store']);
            Route::get('imports/{import}', [ImportController::class, 'show']);
            Route::put('imports/{import}/source', [ImportController::class, 'source']);
            Route::put('imports/{import}/mapping', [ImportController::class, 'mapping']);
            Route::post('imports/{import}/validate', [ImportController::class, 'validateBatch']);
            Route::get('imports/{import}/rows', [ImportController::class, 'rows']);
            Route::patch('imports/{import}/rows/{row}', [ImportController::class, 'updateRow']);
            Route::post('imports/{import}/rows/{row}/ignore', [ImportController::class, 'ignoreRow']);
            Route::post('imports/{import}/execute', [ImportController::class, 'execute']);
            Route::get('imports/{import}/report', [ImportController::class, 'report']);
            Route::get('imports/{import}/report/export', [ImportController::class, 'exportReport']);
        });

        // Administration : référentiels et utilisateurs
        Route::middleware('role:administrateur')->group(function () {
            Route::get('users', [UserController::class, 'index']);
            Route::post('references/{resource}', [ReferenceController::class, 'store']);
            Route::put('references/{resource}/{id}', [ReferenceController::class, 'update']);
            Route::delete('references/{resource}/{id}', [ReferenceController::class, 'destroy']);
        });
    });
});
