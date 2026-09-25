import { test as setup } from '@playwright/test';
import { admin, adminStorageState } from './helpers';

/** Login sekali (kode pemulihan 2FA sekali pakai), sesi dipakai ulang semua tes. */
setup('login sebagai admin', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Alamat email').fill(admin.email);
    await page.getByLabel('Password', { exact: true }).fill(admin.password);
    await page.getByRole('button', { name: 'Masuk' }).click();
    await page.waitForURL('**/two-factor-challenge');
    await page.getByRole('button', { name: /kode pemulihan/i }).click();
    await page.getByLabel('Kode pemulihan').fill(admin.recoveryCode);
    await page.getByRole('button', { name: 'Lanjut' }).click();
    await page.waitForURL('**/dashboard');
    await page.context().storageState({ path: adminStorageState });
});
