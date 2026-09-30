import { expect, test } from '@playwright/test';
import { adminStorageState, expectNoA11yViolations } from './helpers';

test.use({ storageState: adminStorageState });

test('master data: wilayah, tahun anggaran, orang & NIK tersamar lolos WCAG 2.2 AA', async ({
    page,
}) => {
    await page.goto('/admin/master-data');
    await expect(
        page.getByRole('heading', { level: 1, name: 'Master data' }),
    ).toBeVisible();
    await expect(
        page.getByText('Sumber: Kepmendagri 300.2.2-2430 Tahun 2025'),
    ).toBeVisible();
    await expectNoA11yViolations(page);

    // Wilayah: jelajah per tingkat dengan keyboard, lalu cari.
    await page
        .getByRole('link', { name: /Wilayah/ })
        .first()
        .click();
    await page.getByRole('link', { name: 'Kalimantan Timur' }).focus();
    await page.keyboard.press('Enter');
    await expect(
        page.getByRole('heading', { level: 1, name: 'Kalimantan Timur' }),
    ).toBeVisible();
    await expectNoA11yViolations(page);
    await page.getByLabel('Cari nama atau kode wilayah').fill('makmur');
    await page.getByRole('button', { name: 'Cari' }).click();
    await expect(
        page.getByRole('rowheader', { name: 'Rawa Makmur' }),
    ).toBeVisible();

    await page.goto('/admin/master-data/fiscal-years');
    await expect(page.getByRole('rowheader', { name: '2026' })).toBeVisible();
    await expectNoA11yViolations(page);

    // Orang: daftar hanya menampilkan 4 digit terakhir NIK; cari NIK lewat POST (tidak masuk URL).
    await page.goto('/admin/master-data/persons');
    await expect(page.getByText('************0001')).toBeVisible();
    await expectNoA11yViolations(page);
    await page.getByLabel(/Cari nama, atau NIK/).fill('6472010101900001');
    await page.getByRole('button', { name: 'Cari' }).click();
    await expect(
        page.getByText('Hasil pencarian NIK: ditemukan.'),
    ).toBeVisible();
    expect(page.url()).not.toContain('6472010101900001');

    await page.getByRole('link', { name: 'Contoh Pegawai' }).click();
    await expect(
        page.getByRole('heading', { level: 1, name: 'Contoh Pegawai' }),
    ).toBeVisible();
    await expect(page.getByText('6472010101900001')).toHaveCount(0);
    await expect(
        page.getByRole('rowheader', { name: 'Dinas Kesehatan' }),
    ).toBeVisible();
    await expectNoA11yViolations(page);

    await page.getByRole('link', { name: 'Ubah' }).click();
    await expect(page.getByLabel(/^NIK/)).toHaveValue('');
    await expectNoA11yViolations(page);
});

test('admin mengajukan pemakaian, Walidata memutuskan lewat dialog aksesibel', async ({
    page,
}, testInfo) => {
    // Tiap project memakai entity berbeda agar tidak bergantung urutan eksekusi.
    const entity =
        testInfo.project.name === 'mobile' ? 'Pegawai' : 'Tahun anggaran';

    await page.goto('/admin/applications');
    await page.getByRole('link', { name: 'Aplikasi Uji' }).click();
    await expect(
        page.getByRole('heading', { name: 'Entity bersama yang dipakai' }),
    ).toBeVisible();
    await page.getByRole('button', { name: 'Kirim pengajuan' }).click();
    await expect(
        page.getByRole('alert').filter({ hasText: 'Periksa kembali' }),
    ).toBeFocused();
    await expectNoA11yViolations(page);
    await page
        .getByLabel(/^Entity yang dipakai/)
        .selectOption({ label: `${entity} — Core Platform` });
    await page
        .getByLabel(/^Alasan pemakaian/)
        .fill('Kegiatan uji merujuk master data bersama.');
    await page.getByRole('button', { name: 'Kirim pengajuan' }).click();
    await expect(
        page.getByRole('rowheader', { name: `${entity} — Core Platform` }),
    ).toBeVisible();

    await page.goto('/admin/consumers');
    await expect(
        page.getByRole('heading', {
            level: 1,
            name: 'Pemakaian entity bersama',
        }),
    ).toBeVisible();
    const row = page
        .getByRole('row')
        .filter({ hasText: `${entity} — Core Platform` });
    await expectNoA11yViolations(page);

    // Menolak tanpa alasan: error tampil di dalam dialog yang tetap terbuka.
    await row.getByRole('button', { name: 'Tolak…' }).click();
    const reject = page.getByRole('dialog', { name: /Tolak pengajuan/ });
    await reject.getByRole('button', { name: 'Ya, tolak' }).click();
    await expect(reject.getByRole('alert')).toContainText(
        'minimal 10 karakter',
    );
    await expectNoA11yViolations(page);
    await reject.getByRole('button', { name: 'Batal' }).click();

    await row.getByRole('button', { name: 'Setujui…' }).click();
    const approve = page.getByRole('dialog', { name: /Setujui/ });
    await approve
        .getByLabel(/Catatan keputusan/)
        .fill('Sesuai kebutuhan kegiatan.');
    await approve.getByRole('button', { name: 'Ya, setujui' }).click();
    await expect(
        page
            .getByRole('region', { name: 'Riwayat keputusan pemakaian' })
            .getByRole('row')
            .filter({ hasText: `${entity} — Core Platform` }),
    ).toContainText('Disetujui');
});
