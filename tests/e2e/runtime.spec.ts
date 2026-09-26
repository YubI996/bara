import { expect, test } from '@playwright/test';
import { adminStorageState, expectNoA11yViolations, tabTo } from './helpers';

test.use({ storageState: adminStorageState });
test.setTimeout(180_000);

const base = '/apps/uji/semua_tipe';

test('form runtime dengan semua tipe field lolos WCAG 2.2 AA dan bisa diisi dengan keyboard', async ({
    page,
}, testInfo) => {
    const title = `Uji ${testInfo.project.name}`;
    const program = `Program ${testInfo.project.name}`;

    await page.goto('/apps');
    await expect(
        page.getByRole('link', { name: 'Contoh semua tipe' }),
    ).toBeVisible();
    await expectNoA11yViolations(page);

    // Data target relasi dibuat lewat form juga.
    await page.goto('/apps/uji/program/create');
    await page.getByLabel(/^Nama program/).fill(program);
    await page.getByRole('button', { name: 'Simpan' }).click();
    await expect(page.getByRole('heading', { name: program })).toBeVisible();

    await page.goto(base);
    await expect(
        page.getByRole('heading', { name: 'Contoh semua tipe' }),
    ).toBeVisible();
    await expectNoA11yViolations(page);

    await page
        .getByRole('link', { name: 'Tambah Contoh', exact: true })
        .click();
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

    // Setiap field dijangkau dengan Tab dan dioperasikan dengan keyboard (REQ-006).
    const kb = page.keyboard;
    await page.getByLabel(/^Unit pemilik/).focus();
    await tabTo(page, page.getByLabel(/^Judul/));
    await kb.type(title);
    await tabTo(page, page.getByLabel(/^Uraian/));
    await kb.type('Uraian panjang\nbaris kedua');
    await tabTo(page, page.getByLabel(/^Catatan berformat/));
    await kb.type('<p>Catatan <strong>penting</strong></p>');
    await tabTo(page, page.getByLabel(/^Jumlah peserta/));
    await kb.type('120');
    await tabTo(page, page.getByLabel(/^Volume/));
    await kb.type('12,5');
    await tabTo(page, page.getByLabel(/^Pagu/));
    await kb.type('1.500.000,50');
    await tabTo(page, page.getByLabel(/^Capaian/));
    await kb.type('75,5');
    await tabTo(page, page.getByLabel(/^Prioritas/));
    await kb.press('Space');
    await expect(page.getByLabel(/^Prioritas/)).toBeChecked();
    // Input tanggal native: format ketik bergantung locale browser, jadi nilai diisi lewat
    // fill setelah fokus dicapai dengan Tab.
    await tabTo(page, page.getByLabel(/^Tanggal mulai/));
    await page.getByLabel(/^Tanggal mulai/).fill('2026-10-01');
    await tabTo(page, page.getByLabel(/^Jadwal rapat/));
    await page.getByLabel(/^Jadwal rapat/).fill('2026-10-01T09:30');
    await tabTo(page, page.getByRole('radio', { name: 'Rencana' }));
    await kb.press('ArrowRight');
    await expect(page.getByRole('radio', { name: 'Selesai' })).toBeChecked();
    await tabTo(page, page.getByLabel(/^Kategori/));
    await page.getByLabel(/^Kategori/).selectOption('k3');
    await tabTo(page, page.getByRole('checkbox', { name: 'Lansia' }));
    await kb.press('Space');
    await expect(page.getByRole('checkbox', { name: 'Lansia' })).toBeChecked();
    await tabTo(page, page.getByLabel(/^Program/));
    await page.getByLabel(/^Program/).selectOption({ label: program });
    await tabTo(page, page.getByLabel(/^Lampiran|^Pilih berkas/));
    await page.getByLabel(/^Lampiran|^Pilih berkas/).setInputFiles({
        name: 'laporan.pdf',
        mimeType: 'application/pdf',
        buffer: Buffer.from('%PDF-1.4\n%%EOF'),
    });
    await tabTo(page, page.getByRole('button', { name: 'Simpan' }));
    await kb.press('Enter');

    await expect(page.getByRole('heading', { name: title })).toBeVisible();
    await expect(page.getByText('Rp 1.500.000,5')).toBeVisible();
    await expect(page.getByText(program)).toBeVisible();
    await expect(
        page.getByRole('link', { name: /Unduh laporan\.pdf/ }),
    ).toBeVisible();
    await expectNoA11yViolations(page);

    // Halaman ubah memuat nilai tersimpan dengan format Indonesia (UX-003).
    await page.getByRole('link', { name: 'Ubah' }).click();
    await expect(page.getByLabel(/^Judul/)).toHaveValue(title);
    await expect(page.getByLabel(/^Volume/)).toHaveValue('12,5');
    await expect(page.getByLabel(/^Jadwal rapat/)).toHaveValue(
        '2026-10-01T09:30',
    );
    await expectNoA11yViolations(page);

    // Simpan tanpa mengubah angka: nilai tidak boleh berubah 1000x.
    await page.getByRole('button', { name: 'Simpan' }).click();
    await expect(page.getByRole('heading', { name: title })).toBeVisible();
    await expect(page.getByText('12,5', { exact: true })).toBeVisible();
});

test('konflik lock_version: simpan kedua diblokir sampai data dimuat ulang', async ({
    page,
    context,
}, testInfo) => {
    const title = `Konflik ${testInfo.project.name}`;
    await page.goto(`${base}/create`);
    await page.getByLabel(/^Judul/).fill(title);
    await page.getByRole('button', { name: 'Simpan' }).click();
    await expect(page.getByRole('heading', { name: title })).toBeVisible();
    const editUrl = `${page.url()}/edit`;

    const other = await context.newPage();
    await page.goto(editUrl);
    await other.goto(editUrl);

    await other.getByLabel(/^Uraian/).fill('Diubah rekan');
    await other.getByRole('button', { name: 'Simpan' }).click();
    await expect(other.getByRole('heading', { name: title })).toBeVisible();

    await page.getByLabel(/^Uraian/).fill('Versi saya');
    await page.getByRole('button', { name: 'Simpan' }).click();
    await expect(
        page.getByRole('link', { name: 'Muat ulang data terbaru' }),
    ).toBeVisible();
    await expect(page.getByRole('button', { name: 'Simpan' })).toBeDisabled();
    await expectNoA11yViolations(page);

    await page.getByRole('link', { name: 'Muat ulang data terbaru' }).click();
    await expect(page.getByLabel(/^Uraian/)).toHaveValue('Diubah rekan');
    await expect(page.getByRole('button', { name: 'Simpan' })).toBeEnabled();
});

test('hapus record lewat dialog konfirmasi yang aksesibel', async ({
    page,
}, testInfo) => {
    const title = `Hapus ${testInfo.project.name}`;
    await page.goto(`${base}/create`);
    await page.getByLabel(/^Judul/).fill(title);
    await page.getByRole('button', { name: 'Simpan' }).click();
    await expect(page.getByRole('heading', { name: title })).toBeVisible();

    await page.getByRole('button', { name: 'Hapus…' }).click();
    const dialog = page.getByRole('dialog', { name: `Hapus “${title}”?` });
    await expect(dialog).toBeVisible();
    await expectNoA11yViolations(page);
    await dialog.getByRole('button', { name: 'Ya, hapus' }).click();

    await expect(
        page.getByRole('heading', { name: 'Contoh semua tipe' }),
    ).toBeVisible();
    await expect(page.getByRole('link', { name: title })).toHaveCount(0);
});
