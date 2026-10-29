<?php

use App\Http\Controllers\CommunityController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CommunityController::class, 'home']);
Route::get('/auth', [CommunityController::class, 'authPage'])->name('login');
Route::post('/auth/login', [CommunityController::class, 'login'])->middleware('throttle:10,1');
Route::post('/auth/register', [CommunityController::class, 'register'])->middleware('throttle:5,1');
Route::get('/staff/login', [CommunityController::class, 'staffLoginPage'])->name('staff.login');
Route::post('/staff/login', [CommunityController::class, 'staffLogin'])->middleware('throttle:10,1');
Route::post('/logout', [CommunityController::class, 'logout'])->middleware('auth');
Route::get('/track', [CommunityController::class, 'track']);
Route::get('/privacy', [CommunityController::class, 'privacy']);
Route::get('/partner/{token}', [CommunityController::class, 'partner'])->where('token', '[A-Za-z0-9]+');
Route::post('/partner/{token}', [CommunityController::class, 'partnerUpdate'])->where('token', '[A-Za-z0-9]+')->middleware('throttle:10,1');

Route::middleware('auth')->group(function () {
    Route::post('/reports/notice', [CommunityController::class, 'notice']);
    Route::get('/reports', [CommunityController::class, 'reports']);
    Route::get('/reports/new', [CommunityController::class, 'newReport']);
    Route::post('/reports', [CommunityController::class, 'storeReport'])->middleware('throttle:10,1');
    Route::get('/reports/{id}', [CommunityController::class, 'reportDetail'])->whereNumber('id');
    Route::get('/profile', [CommunityController::class, 'profile']);
    Route::post('/profile', [CommunityController::class, 'updateProfile']);

    Route::get('/staff', [CommunityController::class, 'staffInbox']);
    Route::get('/staff/reports/{id}', [CommunityController::class, 'staffReport'])->whereNumber('id');
    Route::get('/staff/reports/{id}/print', [CommunityController::class, 'printStaffReport'])->whereNumber('id');
    Route::post('/staff/reports/{id}', [CommunityController::class, 'updateStaffReport'])->whereNumber('id');
    Route::post('/staff/reports/{id}/break-glass', [CommunityController::class, 'breakGlass'])->whereNumber('id')->middleware('throttle:5,1');
    Route::post('/staff/reports/{id}/referrals', [CommunityController::class, 'createReferral'])->whereNumber('id');
    Route::get('/staff/tasks', [CommunityController::class, 'tasks']);
    Route::get('/staff/referrals', [CommunityController::class, 'referrals']);
    Route::get('/staff/analytics', [CommunityController::class, 'analytics']);
    Route::get('/staff/analytics/export', [CommunityController::class, 'exportAnalytics']);

    Route::get('/admin', [CommunityController::class, 'admin']);
    Route::post('/admin/staff', [CommunityController::class, 'createStaff']);
    Route::delete('/admin/users/{id}', [CommunityController::class, 'deactivateUser'])->whereNumber('id');
    Route::post('/admin/users/{id}/restore', [CommunityController::class, 'reactivateUser'])->whereNumber('id');
    Route::post('/admin/users/{id}/role', [CommunityController::class, 'updateRole'])->whereNumber('id');
    Route::post('/admin/settings', [CommunityController::class, 'saveAdminSettings']);
    Route::post('/admin/categories', [CommunityController::class, 'saveCategory']);
    Route::post('/admin/keywords', [CommunityController::class, 'saveKeyword']);
    Route::get('/admin/audit', [CommunityController::class, 'audit']);
    Route::post('/admin/audit/verify', [CommunityController::class, 'verifyAudit']);
});
