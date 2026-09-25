<?php

declare(strict_types=1);

test('pesan validasi tampil dalam Bahasa Indonesia', function (): void {
    $this->actingAs(userWithRole('platform_admin'))
        ->post(route('admin.organizations.store'), [])
        ->assertSessionHasErrors(['name' => 'Nama wajib diisi.', 'code' => 'Kode wajib diisi.']);
});

test('login gagal memakai pesan Bahasa Indonesia', function (): void {
    $this->post(route('login.store'), ['email' => 'tidak-ada@example.test', 'password' => 'salah-sekali'])
        ->assertSessionHasErrors(['email' => 'Email atau password tidak sesuai.']);
});
