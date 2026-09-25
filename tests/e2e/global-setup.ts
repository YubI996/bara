import { execFileSync } from 'node:child_process';

/** Reset database E2E sebelum seluruh tes (kode pemulihan 2FA bersifat sekali pakai). */
export default function globalSetup(): void {
    execFileSync(
        'php',
        [
            'artisan',
            'migrate:fresh',
            '--seed',
            '--seeder=Database\\Seeders\\E2eSeeder',
            '--force',
        ],
        {
            stdio: 'inherit',
            env: {
                ...process.env,
                DB_DATABASE: process.env.E2E_DB_DATABASE ?? 'bara_e2e',
            },
        },
    );
}
