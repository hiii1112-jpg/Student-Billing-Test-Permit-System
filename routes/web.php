<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CashierController;
use App\Http\Controllers\RegistrarController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TeacherController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('home'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/activate', [AuthController::class, 'showActivate'])->name('activate');
    Route::post('/activate', [AuthController::class, 'activate']);
});

Route::middleware('auth')->group(function () {
    Route::get('/home', [AuthController::class, 'home'])->name('home');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/password', [AuthController::class, 'showPassword'])->name('password');
    Route::post('/password', [AuthController::class, 'updatePassword'])->name('password.update');
    Route::get('/notifications', [AuthController::class, 'notifications'])->name('notifications');
    Route::post('/notifications/read', [AuthController::class, 'markRead'])->name('notifications.read');
});

Route::controller(RegistrarController::class)->prefix('registrar')->name('registrar.')
    ->middleware(['auth', 'role:registrar'])->group(function () {
        Route::get('/', 'dashboard')->name('dashboard');
        Route::get('/students', 'students')->name('students');
        Route::post('/students', 'studentStore')->name('students.store');
        Route::post('/students/import', 'import')->name('students.import');
        Route::get('/students/{student}', 'show')->name('students.show');
        Route::put('/students/{student}', 'update')->name('students.update');
        Route::delete('/students/{student}', 'destroy')->name('students.destroy');
        Route::post('/students/{student}/coe', 'coe')->name('students.coe');
        Route::post('/students/{student}/retake', 'retake')->name('students.retake');
        Route::post('/students/{student}/exit', 'exitStatus')->name('students.exit');
        Route::get('/students/{student}/print', 'clearancePrint')->name('students.print');
        Route::get('/staff', 'staff')->name('staff');
        Route::post('/staff', 'staffStore')->name('staff.store');
        Route::post('/users/{user}/toggle', 'userToggle')->name('users.toggle');
        Route::post('/users/{user}/reset', 'userReset')->name('users.reset');
        Route::get('/setup', 'setup')->name('setup');
        Route::post('/setup', 'setupStore')->name('setup.store');
        Route::get('/loads', 'loads')->name('loads');
        Route::post('/loads', 'loadStore')->name('loads.store');
        Route::get('/terms', 'terms')->name('terms');
        Route::post('/terms', 'termStore')->name('terms.store');
        Route::get('/logs', 'logs')->name('logs');
        Route::post('/maintenance', 'maintenance')->name('maintenance');
    });

Route::controller(CashierController::class)->prefix('cashier')->name('cashier.')
    ->middleware(['auth', 'role:cashier'])->group(function () {
        Route::get('/', 'dashboard')->name('dashboard');
        Route::get('/search', 'search')->name('search');
        Route::get('/student/{student}', 'ledger')->name('ledger');
        Route::post('/term/{st}/cash', 'cashPay')->name('cash');
        Route::get('/receipt/{payment}', 'receipt')->name('receipt');

        Route::middleware('role:head')->group(function () {
            Route::get('/report', 'report')->name('report');
            Route::post('/payment/{payment}/verify', 'verify')->name('verify');
            Route::post('/payment/{payment}/flag', 'flag')->name('flag');
            Route::post('/payment/{payment}/void', 'void')->name('void');
            Route::post('/term/{st}/promissory', 'promissory')->name('promissory');
            Route::post('/student/{student}/hold', 'hold')->name('hold');
            Route::post('/hold/{hold}/release', 'release')->name('release');
        });
    });

Route::controller(StudentController::class)->prefix('student')->name('student.')
    ->middleware(['auth', 'role:student'])->group(function () {
        Route::get('/', 'dashboard')->name('dashboard');
        Route::post('/unlock', 'unlock')->name('unlock');
        Route::post('/term/{st}/pay', 'pay')->name('pay');
        Route::get('/receipt/{payment}', 'receipt')->name('receipt');
        Route::get('/permit/{periodNo}', 'permit')->whereNumber('periodNo')->name('permit');
    });

Route::controller(TeacherController::class)->prefix('teacher')->name('teacher.')
    ->middleware(['auth', 'role:teacher'])->group(function () {
        Route::get('/', 'home')->name('home');
        Route::post('/claim', 'claim')->name('claim');
        Route::get('/class/{offering}', 'showClass')->name('class');
        Route::post('/class/{offering}/add', 'addStudent')->name('add');
        Route::delete('/class/{offering}/enrollment/{enrollment}', 'removeStudent')->name('remove');
        Route::post('/class/{offering}/enrollment/{enrollment}/absent', 'markAbsent')->name('absent');
        Route::post('/class/{offering}/enrollment/{enrollment}/special', 'markSpecial')->name('special');
        Route::post('/class/{offering}/bulk-ok', 'bulkOk')->name('bulk');
    });