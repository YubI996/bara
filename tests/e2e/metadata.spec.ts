import { expect, test, type Page } from '@playwright/test';
import { adminStorageState, expectNoA11yViolations } from './helpers';

test.use({ storageState: adminStorageState });

// Alur panjang (±25 langkah + 12 pemeriksaan axe); emulasi mobile lebih lambat.
test.setTimeout(120_000);

async function addField(page: Page, label: string, code: string, type: string) {
    await page.getByRole('link', { name: 'Tambah field' }).click();
    await expect(
        page.getByRole('heading', { name: 'Tambah field' }),
    ).toBeVisible();
    await page.getByLabel(/^Label/).fill(label);
    await page.getByLabel(/^Kode/).fill(code);
    await page.getByLabel(/^Tipe/).selectOption(type);
}

test('membangun entity dari metadata sampai terbit, dengan setiap halaman lolos WCAG 2.2 AA', async ({
    page,
}, testInfo) => {
    const suffix = testInfo.project.name.replace(/\W/g, '_');
    const appCode = `monev_${suffix}`;

    await page.goto('/admin/applications');
    await expect(
        page.getByRole('heading', { name: 'Aplikasi', exact: true }),
    ).toBeVisible();
    await expectNoA11yViolations(page);

    // Buat aplikasi
    await page.getByRole('link', { name: 'Buat aplikasi' }).click();
    await expectNoA11yViolations(page);
    await page.getByRole('button', { name: 'Buat aplikasi' }).click();
    await expect(
        page.getByRole('alert').filter({ hasText: 'Periksa kembali' }),
    ).toBeFocused();
    await expectNoA11yViolations(page);

    await page.getByLabel(/^Kode aplikasi/).fill(appCode);
    await page
        .getByLabel(/^Nama aplikasi/)
        .fill(`Monitoring Program ${suffix}`);
    await page.getByLabel(/^Unit pemilik/).selectOption({ index: 1 });
    await page.getByLabel(/^Status/).selectOption('active');
    await page.getByRole('button', { name: 'Buat aplikasi' }).click();
    await expect(
        page.getByRole('heading', { name: `Monitoring Program ${suffix}` }),
    ).toBeVisible();
    await expectNoA11yViolations(page);

    // Buat entity
    await page.getByRole('link', { name: 'Tambah entity' }).click();
    await page.getByLabel(/^Kode entity/).fill('realisasi');
    await page.getByLabel(/^Nama \(wajib\)/).fill('Realisasi');
    await page.getByLabel(/^Nama jamak/).fill('Realisasi');
    await page.getByLabel(/^Template judul/).fill('{uraian}');
    await expectNoA11yViolations(page);
    await page.getByRole('button', { name: 'Buat entity' }).click();
    await expect(
        page.getByRole('heading', { name: 'Realisasi', exact: true }),
    ).toBeVisible();
    await expectNoA11yViolations(page);

    // Field teks dengan pola berbahaya → error, lalu diperbaiki
    await addField(page, 'Uraian', 'uraian', 'string');
    await page.getByLabel(/^Pola/).fill('(a+)+');
    await page.getByLabel(/^Wajib diisi/).check();
    await page.getByRole('button', { name: 'Tambah ke draft' }).click();
    await expect(
        page.getByRole('alert').filter({ hasText: 'Periksa kembali' }),
    ).toBeFocused();
    await expectNoA11yViolations(page);
    await page.getByLabel(/^Pola/).fill('');
    await page.getByRole('button', { name: 'Tambah ke draft' }).click();
    await expect(page.getByRole('rowheader', { name: 'Uraian' })).toBeVisible();

    // Field pilihan tunggal dengan editor opsi
    await addField(page, 'Status kegiatan', 'status', 'enum');
    await page.getByLabel(/^Nilai opsi 1/).fill('berjalan');
    await page.getByLabel(/^Label opsi 1/).fill('Berjalan');
    await page.getByRole('button', { name: 'Tambah opsi' }).click();
    await page.getByLabel(/^Nilai opsi 2/).fill('selesai');
    await page.getByLabel(/^Label opsi 2/).fill('Selesai');
    await expectNoA11yViolations(page);
    await page.getByRole('button', { name: 'Tambah ke draft' }).click();
    await expect(
        page.getByRole('rowheader', { name: 'Status kegiatan' }),
    ).toBeVisible();

    // Form tipe relasi & berkas juga diperiksa aksesibilitasnya
    await page.getByRole('link', { name: 'Tambah field' }).click();
    await page.getByLabel(/^Tipe/).selectOption('relationship');
    await expect(page.getByLabel(/^Entity target/)).toBeVisible();
    await expectNoA11yViolations(page);
    await page.getByLabel(/^Tipe/).selectOption('file');
    await expect(
        page.getByRole('group', { name: /Jenis berkas/ }),
    ).toBeVisible();
    await expectNoA11yViolations(page);
    await page.goBack();

    // Urutan bisa diubah dengan keyboard (tombol, bukan drag)
    await page.getByRole('button', { name: 'Naikkan Status kegiatan' }).focus();
    await page.keyboard.press('Enter');
    await expect(page.getByRole('rowheader').first()).toHaveText(
        'Status kegiatan',
    );

    // Publikasi
    await expect(page.getByText('Draft siap dipublikasikan.')).toBeVisible();
    await expectNoA11yViolations(page);
    await page.getByLabel(/^Catatan rilis/).fill('Rilis awal');
    await page.getByRole('button', { name: 'Publikasikan versi 1' }).click();
    await expect(
        page.getByRole('heading', { name: 'Field versi terbit v1' }),
    ).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'Buat draft baru' }),
    ).toBeVisible();
    await expectNoA11yViolations(page);
});
