<?php

use App\Http\Controllers\Settings\CompanyController as CompanySettingsController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\TeamController;
use App\Http\Controllers\TeamInvitationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/company', [CompanySettingsController::class, 'edit'])->name('company.settings');
    Route::patch('settings/company', [CompanySettingsController::class, 'update'])->name('company.settings.update');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');

    // Team management (requires company selected)
    Route::middleware(\App\Http\Middleware\EnsureCompanySelected::class)->group(function () {
        Route::get('settings/team', [TeamController::class, 'index'])->name('team.index');
        Route::post('settings/team/invite', [TeamController::class, 'invite'])->name('team.invite');
        Route::patch('settings/team/{user}/role', [TeamController::class, 'updateRole'])->name('team.update-role');
        Route::delete('settings/team/{user}', [TeamController::class, 'remove'])->name('team.remove');
        Route::post('settings/team/invitations/{invitation}/resend', [TeamController::class, 'resend'])->name('team.invitations.resend');
        Route::delete('settings/team/invitations/{invitation}', [TeamController::class, 'cancelInvitation'])->name('team.invitations.cancel');
    });
});

// Invitation flow — all guest-accessible (controller handles auth checks internally)
Route::get('invitations/{token}', [TeamInvitationController::class, 'show'])->name('invitations.show');
Route::post('invitations/{token}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
Route::post('invitations/{token}/decline', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
Route::get('invitations/{token}/register', [TeamInvitationController::class, 'showRegister'])->name('invitations.register');
Route::post('invitations/{token}/register', [TeamInvitationController::class, 'register'])->name('invitations.register.store');
