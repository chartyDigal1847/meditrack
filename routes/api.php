<?php

use App\Http\Controllers\Api\V1\ClinicVisitController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\DiagnosisController;
use App\Http\Controllers\Api\V1\EmergencyAlertController;
use App\Http\Controllers\Api\V1\HealthReportController;
use App\Http\Controllers\Api\V1\MedicalDashboardController;
use App\Http\Controllers\Api\V1\MedicalRecordController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PrescriptionController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\StudentConcernController;
use App\Http\Middleware\AuthenticateFederatedSearch;
use App\Http\Middleware\EnsureSsoAuthenticated;
use Illuminate\Support\Facades\Route;

// Federated search endpoint (bearer token only)
Route::get('/search', SearchController::class)->middleware(['api', AuthenticateFederatedSearch::class]);

// Main API routes (SSO session + header fallback)
Route::prefix('v1')->middleware(['api', EnsureSsoAuthenticated::class, 'throttle:api'])->group(function () {
    Route::get('/bootstrap', [MedicalDashboardController::class, 'bootstrap']);
    Route::get('/analytics', [MedicalDashboardController::class, 'analytics']);
    Route::get('/visit-history', [ClinicVisitController::class, 'history']);
    Route::get('/students', [StudentController::class, 'index']);
    Route::get('/search', SearchController::class);

    Route::apiResource('clinic-visits', ClinicVisitController::class);
    Route::apiResource('diagnoses', DiagnosisController::class);
    Route::apiResource('medical-records', MedicalRecordController::class);
    Route::apiResource('prescriptions', PrescriptionController::class);
    Route::apiResource('health-reports', HealthReportController::class);
    Route::apiResource('emergency-alerts', EmergencyAlertController::class);
    Route::apiResource('notifications', NotificationController::class)->only(['index', 'show', 'update', 'destroy']);
    Route::apiResource('student-concerns', StudentConcernController::class)->only(['index', 'store', 'show', 'update']);
});
