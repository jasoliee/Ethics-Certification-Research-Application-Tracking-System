<?php

use App\Enums\UserRole;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Support\RoleHome;
use Illuminate\Support\Facades\Route;

Route::middleware('no-store')->group(function (): void {
    Route::get('/', function () {
        if (auth()->check()) {
            return redirect()->route(RoleHome::routeNameFor(auth()->user()->role));
        }

        return view('auth.login');
    })->name('home');

    Route::middleware('guest.role')->group(function (): void {
        Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    });

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->middleware('auth')
        ->name('logout');

    Route::middleware('auth')->group(function (): void {
        Route::view('/student-faculty-researcher/landing', 'landing.role', [
            'title' => UserRole::Applicant->landingTitle(),
        ])->middleware('role:'.UserRole::Applicant->value)->name('applicant.landing');

        Route::view('/adviser/landing', 'landing.role', [
            'title' => UserRole::Adviser->landingTitle(),
        ])->middleware('role:'.UserRole::Adviser->value)->name('adviser.landing');

        Route::view('/reviewer/landing', 'landing.role', [
            'title' => UserRole::Reviewer->landingTitle(),
        ])->middleware('role:'.UserRole::Reviewer->value)->name('reviewer.landing');

        Route::view('/res-lead/landing', 'landing.role', [
            'title' => UserRole::ResLead->landingTitle(),
        ])->middleware('role:'.UserRole::ResLead->value)->name('res.landing');
    });
});
