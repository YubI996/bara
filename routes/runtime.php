<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureTwoFactorForPrivilegedRoles;
use App\Modules\Data\Http\Controllers\RecordController;
use Illuminate\Support\Facades\Route;

// Runtime generik: setiap entity terbit langsung punya halaman tanpa deploy (docs/06 §3).
Route::middleware(['auth', 'verified', EnsureTwoFactorForPrivilegedRoles::class])
    ->prefix('apps')
    ->name('runtime.')
    ->where(['app' => '[a-z][a-z0-9_]{1,62}', 'entity' => '[a-z][a-z0-9_]{1,62}'])
    ->whereUuid(['record', 'file'])
    ->group(function (): void {
        Route::get('/', [RecordController::class, 'home'])->name('home');
        Route::get('{app}/{entity}', [RecordController::class, 'index'])->name('index');
        Route::get('{app}/{entity}/create', [RecordController::class, 'create'])->name('create');
        Route::post('{app}/{entity}', [RecordController::class, 'store'])->middleware('throttle:runtime-write')->name('store');
        Route::get('{app}/{entity}/lookup/{field}', [RecordController::class, 'lookup'])
            ->where('field', '[a-z][a-z0-9_]{1,62}')->middleware('throttle:runtime-lookup')->name('lookup');
        Route::get('{app}/{entity}/{record}', [RecordController::class, 'show'])->name('show');
        Route::get('{app}/{entity}/{record}/edit', [RecordController::class, 'edit'])->name('edit');
        Route::put('{app}/{entity}/{record}', [RecordController::class, 'update'])->middleware('throttle:runtime-write')->name('update');
        Route::delete('{app}/{entity}/{record}', [RecordController::class, 'destroy'])->middleware('throttle:runtime-write')->name('destroy');
        Route::get('{app}/{entity}/{record}/files/{file}', [RecordController::class, 'download'])->name('files.download');
    });
