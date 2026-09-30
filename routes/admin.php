<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureTwoFactorEnabled;
use App\Modules\MasterData\Http\Controllers\MasterDataController;
use App\Modules\MasterData\Http\Controllers\PersonController;
use App\Modules\Metadata\Http\Controllers\ApplicationController;
use App\Modules\Metadata\Http\Controllers\ConsumerController;
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
        Route::post('applications/{application}/consumers', [ConsumerController::class, 'store'])->name('consumers.store');

        Route::get('consumers', [ConsumerController::class, 'index'])->name('consumers.index');
        Route::post('consumers/{entity}/{application}', [ConsumerController::class, 'decide'])->name('consumers.decide');

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

Route::middleware(['auth', 'verified', EnsureTwoFactorEnabled::class])
    ->prefix('admin/master-data')
    ->name('admin.master-data.')
    ->whereUuid(['fiscalYear', 'person', 'employee'])
    ->group(function (): void {
        Route::get('/', [MasterDataController::class, 'index'])->name('index');
        Route::get('regions', [MasterDataController::class, 'regions'])->name('regions');
        Route::get('fiscal-years', [MasterDataController::class, 'fiscalYears'])->name('fiscal-years');
        Route::post('fiscal-years', [MasterDataController::class, 'storeFiscalYear'])->name('fiscal-years.store');
        Route::post('fiscal-years/{fiscalYear}/status', [MasterDataController::class, 'changeFiscalYearStatus'])->name('fiscal-years.status');

        Route::get('persons', [PersonController::class, 'index'])->name('persons.index');
        Route::post('persons/search', [PersonController::class, 'index'])->middleware('throttle:60,1')->name('persons.search');
        Route::get('persons/create', [PersonController::class, 'create'])->name('persons.create');
        Route::post('persons', [PersonController::class, 'store'])->name('persons.store');
        Route::get('persons/{person}', [PersonController::class, 'show'])->name('persons.show');
        Route::get('persons/{person}/edit', [PersonController::class, 'edit'])->name('persons.edit');
        Route::put('persons/{person}', [PersonController::class, 'update'])->name('persons.update');
        // Membuka NIK: konfirmasi kata sandi ulang + alasan, dibatasi laju (docs/05 §3).
        Route::post('persons/{person}/reveal-nik', [PersonController::class, 'reveal'])
            ->middleware(['password.confirm', 'throttle:10,1'])->name('persons.reveal');
        // Setelah konfirmasi kata sandi Laravel kembali ke URL ini dengan GET.
        Route::get('persons/{person}/reveal-nik', [PersonController::class, 'afterPasswordConfirm'])->name('persons.reveal.return');
        Route::post('persons/{person}/employments', [PersonController::class, 'addEmployment'])->name('persons.employments.store');
        Route::post('persons/{person}/employments/{employee}/end', [PersonController::class, 'endEmployment'])->name('persons.employments.end');
    });
