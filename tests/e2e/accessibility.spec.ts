import { expect, test } from '@playwright/test';
import { adminStorageState, expectNoA11yViolations } from './helpers';

test.describe('aksesibilitas halaman publik', () => {
    test('halaman login lolos WCAG 2.2 AA', async ({ page }) => {
        await page.goto('/login');
        await expect(page.locator('html')).toHaveAttribute('lang', 'id');
        await expect(
            page.getByRole('heading', { name: 'Masuk ke akun Anda' }),
        ).toBeVisible();
        await expectNoA11yViolations(page);
    });

    test('pesan error login dapat dibaca dan lolos WCAG', async ({ page }) => {
        await page.goto('/login');
        await page.getByLabel('Alamat email').fill('salah@e2e.test');
        await page.getByLabel('Password', { exact: true }).fill('salah-sekali');
        await page.getByRole('button', { name: 'Masuk' }).click();
        await expect(
            page.getByText('Email atau password tidak sesuai.'),
        ).toBeVisible();
        await expectNoA11yViolations(page);
    });
});

test.describe('mode gelap', () => {
    test.use({ colorScheme: 'dark' });

    test('login lolos kontras di mode gelap', async ({ page }) => {
        await page.goto('/login');
        await expectNoA11yViolations(page);
    });
});

test.describe('mode gelap (masuk)', () => {
    test.use({ colorScheme: 'dark', storageState: adminStorageState });

    test('daftar organisasi lolos kontras di mode gelap', async ({ page }) => {
        await page.goto('/admin/organizations');
        await expect(page.locator('html')).toHaveClass(/dark/);
        await expectNoA11yViolations(page);
    });
});

test.describe('navigasi', () => {
    test.use({ storageState: adminStorageState });

    test('menu Organisasi dapat dijangkau dari dasbor', async ({
        page,
        isMobile,
    }) => {
        await page.goto('/dashboard');

        if (isMobile) {
            await page
                .getByRole('button', { name: /buka\/tutup sidebar/i })
                .first()
                .click();
        }

        await page.getByRole('link', { name: 'Organisasi' }).first().click();
        await expect(
            page.getByRole('heading', { name: 'Struktur organisasi' }),
        ).toBeVisible();
    });
});

test.describe('manajemen organisasi', () => {
    test.use({ storageState: adminStorageState });

    test('daftar, form tambah, error, dan form ubah lolos WCAG 2.2 AA', async ({
        page,
    }, testInfo) => {
        // Kode unik per project agar desktop & mobile tidak bentrok di database yang sama.
        const suffix = testInfo.project.name.replace(/\W/g, '_');
        const unitName = `UPTD Puskesmas ${suffix}`;

        await page.goto('/admin/organizations');
        await expect(
            page.getByRole('heading', { name: 'Struktur organisasi' }),
        ).toBeVisible();
        await expect(
            page.getByRole('rowheader', { name: /Dinas Kesehatan/ }),
        ).toBeVisible();
        await expectNoA11yViolations(page);

        await page.getByRole('link', { name: 'Tambah unit' }).click();
        await expect(
            page.getByRole('heading', { name: 'Tambah unit organisasi' }),
        ).toBeVisible();
        await expectNoA11yViolations(page);

        // Kirim kosong → ringkasan error mendapat fokus dan menaut ke field.
        await page.getByRole('button', { name: 'Simpan unit' }).click();
        const summary = page.getByRole('alert').filter({
            hasText: 'Periksa kembali',
        });
        await expect(summary).toBeFocused();
        await expect(page.getByLabel(/Nama unit/)).toHaveAttribute(
            'aria-invalid',
            'true',
        );
        await expectNoA11yViolations(page);

        // Isi dengan benar → kembali ke daftar dengan unit baru.
        await page
            .getByLabel(/Unit induk/)
            .selectOption({ label: '— Dinas Kesehatan' });
        await page.getByLabel(/^Kode/).fill(`uptd_pkm_${suffix}`);
        await page.getByLabel(/Nama unit/).fill(unitName);
        await page.getByLabel(/Jenis unit/).selectOption('uptd');
        await page.getByRole('button', { name: 'Simpan unit' }).click();
        await expect(
            page.getByRole('rowheader', { name: new RegExp(unitName) }),
        ).toBeVisible();

        await page.getByRole('link', { name: `Ubah ${unitName}` }).click();
        await expect(
            page.getByRole('heading', { name: `Ubah unit: ${unitName}` }),
        ).toBeVisible();
        await expectNoA11yViolations(page);
    });

    test('seluruh alur dapat dijalankan dengan keyboard saja', async ({
        page,
    }) => {
        await page.goto('/admin/organizations');
        await page.getByRole('link', { name: 'Tambah unit' }).focus();
        await page.keyboard.press('Enter');
        await expect(
            page.getByRole('heading', { name: 'Tambah unit organisasi' }),
        ).toBeVisible();

        // Tab pertama di form jatuh pada field unit induk (urutan fokus logis).
        await page.getByLabel(/Unit induk/).focus();
        await page.keyboard.press('Tab');
        await expect(page.getByLabel(/^Kode/)).toBeFocused();
    });
});
