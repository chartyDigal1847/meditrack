<?php

use App\Http\Controllers\MediTrackPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MediTrackPageController::class, 'dashboard'])->name('meditrack.dashboard');
Route::get('/medical-dashboard', [MediTrackPageController::class, 'dashboard'])->name('meditrack.medical-dashboard');
Route::get('/visit-history', [MediTrackPageController::class, 'dashboard'])->name('meditrack.visit-history');
Route::get('/health-reports', [MediTrackPageController::class, 'dashboard'])->name('meditrack.health-reports');
Route::get('/clinic-forms', [MediTrackPageController::class, 'dashboard'])->name('meditrack.clinic-forms');
Route::get('/emergency-alerts', [MediTrackPageController::class, 'dashboard'])->name('meditrack.emergency-alerts');
Route::get('/student-health', [MediTrackPageController::class, 'dashboard'])->name('meditrack.student-health');
Route::get('/nurse-management', [MediTrackPageController::class, 'dashboard'])->name('meditrack.nurse-management');
Route::get('/notifications', [MediTrackPageController::class, 'dashboard'])->name('meditrack.notifications');
Route::get('/audit-monitoring', [MediTrackPageController::class, 'dashboard'])->name('meditrack.audit-monitoring');

Route::post('/sso/redirect', [MediTrackPageController::class, 'ssoRedirect'])->name('meditrack.sso.redirect');
Route::post('/sso/exchange', [MediTrackPageController::class, 'ssoExchange'])->name('meditrack.sso.exchange');

