<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

test('404 dirender sebagai halaman Inertia berbahasa Indonesia', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/halaman-yang-tidak-ada')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('errors/show')->where('status', 404));
});

test('403 dirender sebagai halaman Inertia', function (): void {
    $this->actingAs(userWithRole('auditor'))
        ->get(route('admin.applications.create'))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->component('errors/show')->where('status', 403));
});

test('419 (sesi halaman kedaluwarsa) kembali ke halaman sebelumnya dengan pesan', function (): void {
    Route::middleware('web')->post('/_uji-419', fn () => throw new TokenMismatchException);

    $this->actingAs(User::factory()->create())
        ->from('/settings/profile')
        ->post('/_uji-419')
        ->assertRedirect('/settings/profile');
});

test('halaman memakai lang="id" walau APP_LOCALE tidak diatur', function (): void {
    expect(config('app.locale'))->toBe('id');
    $this->get(route('login'))->assertOk()->assertSee('<html lang="id"', false);
});
