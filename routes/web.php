<?php

use App\Http\Controllers\AccessLogController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\GuardRegistrationController;
use App\Http\Controllers\Admin\RegistrationController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RfidController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\ViolationController as AdminViolationController;
use App\Http\Controllers\Auth\CampusIdScanController;
use App\Http\Controllers\Auth\EmailCheckController;
use App\Http\Controllers\Auth\LicenseScanController;
use App\Http\Controllers\Auth\OrCrScanController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Guard\DashboardController as GuardDashboardController;
use App\Http\Controllers\Guard\GateMonitorController;
use App\Http\Controllers\Guard\NotificationController as GuardNotificationController;
use App\Http\Controllers\Guard\PlateLookupController;
use App\Http\Controllers\Guard\UserMonitorController;
use App\Http\Controllers\Guard\ViolationController as GuardViolationController;
use App\Http\Controllers\LiveCameraController;
use App\Http\Controllers\ParkingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\User\DashboardController as UserDashboardController;
use App\Http\Controllers\User\EntryExitController;
use App\Http\Controllers\User\NotificationController;
use App\Http\Controllers\User\ParkingController as UserParkingController;
use App\Http\Controllers\User\ViolationController as UserViolationController;
use App\Http\Controllers\VisitorPreRegistrationController;
use App\Http\Controllers\VisitorController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('home'))->name('home')->middleware('no.cache');

Route::middleware(['no.cache'])->group(function () {
    Route::get('/visitor/pre-register', [VisitorPreRegistrationController::class, 'show'])->name('visitor.pre-register');
    Route::post('/visitor/pre-register', [VisitorPreRegistrationController::class, 'store'])
        ->middleware('throttle:visitor-pre-register')
        ->name('visitor.pre-register.store');
    Route::get('/visitor/pre-register/success', [VisitorPreRegistrationController::class, 'success'])->name('visitor.pre-register.success');
});

Route::middleware(['auth', 'verified', 'granted', 'no.cache', 'role:Admin,Guard'])->group(function () {
    Route::get('/visitor/pre-register/qr', [VisitorPreRegistrationController::class, 'qr'])->name('visitor.pre-register.qr');
});

Route::middleware(['guest', 'no.cache'])->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login');
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])
        ->middleware('throttle:20,1')
        ->name('auth.google');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
        ->middleware('throttle:20,1')
        ->name('auth.google.callback');
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:register');
    Route::get('/register/check-email', [EmailCheckController::class, 'check'])
        ->middleware('throttle:10,1')
        ->name('register.check-email');
    Route::post('/register/scan-id', [CampusIdScanController::class, 'scan'])
        ->middleware('throttle:register-scan-id')
        ->name('register.scan-id');
    Route::post('/register/scan-license', [LicenseScanController::class, 'scan'])
        ->middleware('throttle:register-scan-id')
        ->name('register.scan-license');
    Route::post('/register/scan-orcr', [OrCrScanController::class, 'scan'])
        ->middleware('throttle:register-scan-id')
        ->name('register.scan-orcr');
});

// GET allows safe sign-out when the session/CSRF token is already stale (never show 419).
Route::match(['get', 'post'], '/logout', [LoginController::class, 'logout'])->name('logout');

Route::middleware(['auth', 'no.cache'])->group(function () {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

Route::middleware(['auth', 'verified', 'granted', 'no.cache'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::prefix('admin')->middleware(['auth', 'verified', 'granted', 'no.cache', 'role:Admin'])->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/registrations', [RegistrationController::class, 'index'])->name('registrations');
    Route::post('/registrations/{id}/approve', [RegistrationController::class, 'approve'])->name('registrations.approve');
    Route::post('/registrations/{id}/decline', [RegistrationController::class, 'decline'])->name('registrations.decline');
    Route::get('/users', [UserManagementController::class, 'index'])
        ->middleware('permission:manage_users')
        ->name('users');
    Route::get('/users/{id}', [UserManagementController::class, 'show'])
        ->middleware('permission:manage_users')
        ->name('users.show');
    Route::get('/users/{id}/document/{doc}', [UserManagementController::class, 'document'])
        ->middleware('permission:manage_users')
        ->whereIn('doc', ['license', 'orcr', 'or', 'cr', 'id'])
        ->name('users.document');
    Route::get('/guards/create', [GuardRegistrationController::class, 'create'])
        ->middleware('permission:manage_admins')
        ->name('guards.create');
    Route::post('/guards', [GuardRegistrationController::class, 'store'])
        ->middleware('permission:manage_admins')
        ->name('guards.store');
    Route::get('/rfid', [RfidController::class, 'index'])->name('rfid');
    Route::post('/rfid/{id}/approve', [RfidController::class, 'approve'])
        ->middleware('permission:manage_users')
        ->name('rfid.approve');
    Route::post('/rfid', [RfidController::class, 'update'])
        ->middleware('permission:manage_users')
        ->name('rfid.update');
    Route::get('/visitors/active', [VisitorController::class, 'active'])->name('visitors.active');
    Route::get('/visitors/history', [VisitorController::class, 'history'])->name('visitors.history');
    Route::get('/parking', [ParkingController::class, 'index'])->name('parking');
    Route::get('/parking/status', [LiveCameraController::class, 'status'])->name('parking.status');
    Route::get('/parking/zone-access', [ParkingController::class, 'zoneAccess'])
        ->middleware('permission:manage_parking')
        ->name('parking.zone-access');
    Route::get('/parking/layout', [ParkingController::class, 'layout'])
        ->middleware('permission:manage_parking')
        ->name('parking.layout');
    Route::post('/parking/areas', [ParkingController::class, 'updateAreas'])
        ->middleware('permission:manage_parking')
        ->name('parking.areas.update');
    Route::post('/parking/areas/store', [ParkingController::class, 'storeArea'])
        ->middleware('permission:manage_parking')
        ->name('parking.areas.store');
    Route::post('/parking/areas/{id}/delete', [ParkingController::class, 'destroyArea'])
        ->middleware('permission:manage_parking')
        ->name('parking.areas.destroy');
    Route::post('/parking/slots/status', [ParkingController::class, 'updateSlotStatus'])
        ->middleware('permission:manage_parking')
        ->name('parking.slots.update');
    Route::post('/parking/slots/store', [ParkingController::class, 'storeSlots'])
        ->middleware('permission:manage_parking')
        ->name('parking.slots.store');
    Route::post('/parking/slots/{id}/delete', [ParkingController::class, 'destroySlot'])
        ->middleware('permission:manage_parking')
        ->name('parking.slots.destroy');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::post('/settings/general', [SettingsController::class, 'updateGeneral'])
        ->middleware('permission:system_settings')
        ->name('settings.general');
    Route::post('/settings/general/add', [SettingsController::class, 'storeGeneralInfo'])
        ->middleware('permission:system_settings')
        ->name('settings.general.store');
    Route::post('/settings/system', [SettingsController::class, 'updateSystemInfo'])
        ->middleware('permission:system_settings')
        ->name('settings.system');
    Route::post('/settings/preferences', [SettingsController::class, 'updatePreferences'])
        ->middleware('permission:system_settings')
        ->name('settings.preferences');
    Route::post('/settings/admins', [SettingsController::class, 'storeAdmin'])
        ->middleware('permission:manage_admins')
        ->name('settings.admins.store');
    Route::delete('/settings/staff/{id}', [SettingsController::class, 'destroyStaffUser'])
        ->middleware('permission:manage_admins')
        ->name('settings.staff.destroy');
    Route::post('/settings/parking', [SettingsController::class, 'updateParking'])
        ->middleware('permission:system_settings')
        ->name('settings.parking');
    Route::post('/settings/violations/add', [SettingsController::class, 'storeViolationType'])
        ->middleware('permission:system_settings')
        ->name('settings.violations.store');
    Route::put('/settings/violations/{id}', [SettingsController::class, 'updateViolationType'])
        ->middleware('permission:system_settings')
        ->name('settings.violations.update');
    Route::post('/settings/violations/{id}/toggle', [SettingsController::class, 'toggleViolationType'])
        ->middleware('permission:system_settings')
        ->name('settings.violations.toggle');
    Route::delete('/settings/violations/{id}', [SettingsController::class, 'destroyViolationType'])
        ->middleware('permission:system_settings')
        ->name('settings.violations.destroy');
    Route::get('/violations', [AdminViolationController::class, 'index'])->name('violations');
    Route::get('/violations/{id}/evidence/{index?}', [AdminViolationController::class, 'evidence'])
        ->whereNumber('index')
        ->name('violations.evidence');
    Route::get('/access-logs', [AccessLogController::class, 'index'])->name('access-logs');
    Route::get('/access-logs/events', [AccessLogController::class, 'events'])->name('access-logs.events');
    Route::get('/live-cameras', [LiveCameraController::class, 'index'])->name('live-cameras');
    Route::get('/ai-parking/stream/{camera?}', [LiveCameraController::class, 'stream'])->name('ai-parking.stream');
    Route::get('/reports', [ReportController::class, 'index'])
        ->middleware('permission:view_reports')
        ->name('reports');
    Route::get('/reports/export', [ReportController::class, 'export'])
        ->middleware('permission:view_reports')
        ->name('reports.export');
    Route::get('/reports/export-pdf', [ReportController::class, 'exportPdf'])
        ->middleware('permission:view_reports')
        ->name('reports.export-pdf');
    Route::get('/reports/export-excel', [ReportController::class, 'exportExcel'])
        ->middleware('permission:view_reports')
        ->name('reports.export-excel');
});

Route::prefix('guard')->middleware(['auth', 'verified', 'granted', 'no.cache', 'role:Guard'])->name('guard.')->group(function () {
    Route::get('/', [GuardDashboardController::class, 'index'])->name('dashboard');
    Route::get('/violations', [GuardViolationController::class, 'index'])->name('violations');
    Route::get('/violations/{id}/evidence/{index?}', [GuardViolationController::class, 'evidence'])
        ->whereNumber('index')
        ->name('violations.evidence');
    Route::post('/violations', [GuardViolationController::class, 'store'])
        ->middleware(['throttle:10,1', 'permission:log_violations'])
        ->name('violations.store');
    Route::get('/parking', [ParkingController::class, 'index'])->name('parking');
    Route::get('/parking/status', [LiveCameraController::class, 'status'])->name('parking.status');
    Route::get('/access-logs', [AccessLogController::class, 'index'])->name('access-logs');
    Route::get('/access-logs/events', [AccessLogController::class, 'events'])->name('access-logs.events');
    Route::get('/live-cameras', [LiveCameraController::class, 'index'])->name('live-cameras');
    Route::get('/ai-parking', [LiveCameraController::class, 'aiMonitor'])->name('ai-parking');
    Route::post('/ai-parking/correct-plate', [LiveCameraController::class, 'correctPlate'])
        ->middleware('throttle:30,1')
        ->name('ai-parking.correct-plate');
    Route::get('/ai-parking/stream/{camera?}', [LiveCameraController::class, 'stream'])->name('ai-parking.stream');
    Route::get('/plate-lookup', [PlateLookupController::class, 'index'])->name('plate-lookup');
    Route::post('/plate-lookup', [PlateLookupController::class, 'index'])->name('plate-lookup.submit');
    Route::post('/plate-lookup/search', [PlateLookupController::class, 'lookup'])
        ->middleware('throttle:120,1')
        ->name('plate-lookup.lookup');
    Route::get('/monitor', [UserMonitorController::class, 'index'])->name('monitor');
    Route::get('/visitors/register', [VisitorController::class, 'register'])->name('visitors.register');
    Route::post('/visitors', [VisitorController::class, 'store'])->name('visitors.store');
    Route::get('/visitors/active', [VisitorController::class, 'active'])->name('visitors.active');
    Route::get('/visitors/history', [VisitorController::class, 'history'])->name('visitors.history');
    Route::patch('/visitors/{id}', [VisitorController::class, 'update'])->name('visitors.update');
    Route::post('/visitors/{id}/assign-rfid', [VisitorController::class, 'assignRfid'])->name('visitors.assign-rfid');
    Route::post('/visitors/{id}/return-rfid', [VisitorController::class, 'returnRfid'])->name('visitors.return-rfid');
    Route::get('/gate', [GateMonitorController::class, 'index'])->name('gate');
    Route::get('/gate/status', [GateMonitorController::class, 'status'])->name('gate.status');
    Route::post('/gate/open', [GateMonitorController::class, 'open'])->middleware('throttle:20,1')->name('gate.open');
    Route::post('/gate/scan', [GateMonitorController::class, 'scan'])->middleware('throttle:30,1')->name('gate.scan');
    Route::get('/notifications', [GuardNotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/{action}', [GuardNotificationController::class, 'action'])->name('notifications.action');
});

Route::prefix('user')->middleware(['auth', 'verified', 'portal', 'no.cache', 'role:Student,Staff'])->name('user.')->group(function () {
    Route::get('/', [UserDashboardController::class, 'index'])->name('dashboard');
    Route::get('/registration/fix', [\App\Http\Controllers\User\RegistrationResubmitController::class, 'show'])->name('registration.fix');
    Route::post('/registration/resubmit', [\App\Http\Controllers\User\RegistrationResubmitController::class, 'store'])->name('registration.resubmit');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/{action}', [NotificationController::class, 'action'])->name('notifications.action');
    Route::get('/entry-exit', [EntryExitController::class, 'index'])->name('entry-exit');
    Route::get('/parking', [UserParkingController::class, 'index'])->name('parking');
    Route::get('/parking/status', [UserParkingController::class, 'status'])->name('parking.status');
    Route::get('/violations', [UserViolationController::class, 'index'])->name('violations');
    Route::get('/violations/{id}/evidence/{index?}', [UserViolationController::class, 'evidence'])
        ->whereNumber('index')
        ->name('violations.evidence');
});
