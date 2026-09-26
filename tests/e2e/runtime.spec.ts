import { expect, test } from '@playwright/test';
import { adminStorageState, expectNoA11yViolations } from './helpers';

test.use({ storageState: adminStorageState });
test.setTimeout(120_000);

const base = '/apps/uji/semua_tipe';

test('form runtime dengan semua tipe field lolos WCAG 2.2 AA dan bisa diisi dengan keyboard', async ({
    page,
}, testInfo) => {
    const title = `Uji ${testInfo.project.name}`;

    await page.goto('/apps');
    await expect(
        page.getByRole('link', { name: 'Contoh semua tipe' }),
    ).toBeVisible();
    await expectNoA11yViolations(page);

    await page.goto(base);
    await expect(
        page.getByRole('heading', { name: 'Contoh semua tipe' }),
    ).toBeVisible();
    await expectNoA11yViolations(page);

    await page.getByRole('link', { name: 'Tambah Contoh' }).click();
    await expect(
        page.getByRole('heading', { name: 'Tambah Contoh' }),
    ).toBeVisible();
    await expectNoA11yViolations(page);

    // Kirim kosong: ringkasan error mendapat fokus.
    await page.getByRole('button', { name: 'Simpan' }).click();
    await expect(
        page.getByRole('alert').filter({ hasText: 'Periksa kembali' }),
    ).toBeFocused();
    await expectNoA11yViolations(page);

    // Isi dengan keyboard: fokus ke Judul lalu ketik.
    await page.getByLabel(/^Judul/).focus();
    await page.keyboard.type(title);
    await page.getByLabel(/^Jumlah peserta/).fill('120');
    await page.getByLabel(/^Pagu/).fill('1.500.000,50');
    await page.getByLabel(/^Capaian/).fill('75,5');
    await page.getByLabel(/^Prioritas/).check();
    await page.getByLabel(/^Tanggal mulai/).fill('2026-10-01');
    await page.getByLabel(/^Jadwal rapat/).fill('2026-10-01T09:30');
    await page.getByRole('radio', { name: 'Selesai' }).check();
    await page.getByLabel(/^Kategori/).selectOption('k3');
    await page.getByRole('checkbox', { name: 'Lansia' }).check();
    await page.getByLabel(/^Lampiran|^Pilih berkas/).setInputFiles({
        name: 'laporan.pdf',
        mimeType: 'application/pdf',
        buffer: Buffer.from('%PDF-1.4\n%%EOF'),
    });
    await page.getByRole('button', { name: 'Simpan' }).click();

    await expect(page.getByRole('heading', { name: title })).toBeVisible();
    await expect(page.getByText('Rp 1.500.000,5')).toBeVisible();
    await expect(
        page.getByRole('link', { name: /Unduh laporan\.pdf/ }),
    ).toBeVisible();
    await expectNoA11yViolations(page);

    // Halaman ubah juga aksesibel dan memuat nilai tersimpan.
    await page.getByRole('link', { name: 'Ubah' }).click();
    await expect(page.getByLabel(/^Judul/)).toHaveValue(title);
    await expect(page.getByLabel(/^Jadwal rapat/)).toHaveValue(
        '2026-10-01T09:30',
    );
    await expectNoA11yViolations(page);
});
