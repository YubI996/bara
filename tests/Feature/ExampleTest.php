<?php

declare(strict_types=1);

test('beranda mengarahkan tamu ke halaman login', function (): void {
    $this->get(route('home'))->assertRedirect(route('dashboard'));
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('health check tersedia', function (): void {
    $this->get('/up')->assertOk();
});
