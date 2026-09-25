<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureTwoFactorEnabled;
use App\Modules\Organization\Http\Controllers\OrganizationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', EnsureTwoFactorEnabled::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('organizations', [OrganizationController::class, 'index'])->name('organizations.index');
        Route::get('organizations/create', [OrganizationController::class, 'create'])->name('organizations.create');
        Route::post('organizations', [OrganizationController::class, 'store'])->name('organizations.store');
        Route::get('organizations/{organization}/edit', [OrganizationController::class, 'edit'])
            ->whereUuid('organization')->name('organizations.edit');
        Route::put('organizations/{organization}', [OrganizationController::class, 'update'])
            ->whereUuid('organization')->name('organizations.update');
        Route::post('organizations/{organization}/deactivate', [OrganizationController::class, 'deactivate'])
            ->whereUuid('organization')->name('organizations.deactivate');
    });
