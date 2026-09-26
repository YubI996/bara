<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureTwoFactorEnabled;
use App\Modules\Metadata\Http\Controllers\ApplicationController;
use App\Modules\Metadata\Http\Controllers\EntityController;
use App\Modules\Metadata\Http\Controllers\FieldController;
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

Route::middleware(['auth', 'verified', EnsureTwoFactorEnabled::class])
    ->prefix('admin')
    ->name('admin.')
    ->whereUuid(['application', 'entity', 'field'])
    ->group(function (): void {
        Route::get('applications', [ApplicationController::class, 'index'])->name('applications.index');
        Route::get('applications/create', [ApplicationController::class, 'create'])->name('applications.create');
        Route::post('applications', [ApplicationController::class, 'store'])->name('applications.store');
        Route::get('applications/{application}', [ApplicationController::class, 'show'])->name('applications.show');
        Route::get('applications/{application}/edit', [ApplicationController::class, 'edit'])->name('applications.edit');
        Route::put('applications/{application}', [ApplicationController::class, 'update'])->name('applications.update');

        Route::get('applications/{application}/entities/create', [EntityController::class, 'create'])->name('entities.create');
        Route::post('applications/{application}/entities', [EntityController::class, 'store'])->name('entities.store');
        Route::get('entities/{entity}', [EntityController::class, 'show'])->name('entities.show');
        Route::put('entities/{entity}', [EntityController::class, 'update'])->name('entities.update');
        Route::post('entities/{entity}/draft', [EntityController::class, 'createDraft'])->name('entities.draft.create');
        Route::delete('entities/{entity}/draft', [EntityController::class, 'discardDraft'])->name('entities.draft.discard');
        Route::post('entities/{entity}/privacy-review', [EntityController::class, 'reviewPrivacy'])->name('entities.privacy-review');
        Route::post('entities/{entity}/publish', [EntityController::class, 'publish'])->name('entities.publish');

        Route::get('entities/{entity}/fields/create', [FieldController::class, 'create'])->name('fields.create');
        Route::post('entities/{entity}/fields', [FieldController::class, 'store'])->name('fields.store');
        Route::get('entities/{entity}/fields/{field}/edit', [FieldController::class, 'edit'])->name('fields.edit');
        Route::put('entities/{entity}/fields/{field}', [FieldController::class, 'update'])->name('fields.update');
        Route::delete('entities/{entity}/fields/{field}', [FieldController::class, 'destroy'])->name('fields.destroy');
        Route::post('entities/{entity}/fields/{field}/move', [FieldController::class, 'move'])->name('fields.move');
    });
