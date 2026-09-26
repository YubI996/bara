import AxeBuilder from '@axe-core/playwright';
import { expect, type Page } from '@playwright/test';

export const admin = {
    email: 'admin@e2e.test',
    password: 'e2e-password-aman',
    recoveryCode: 'e2e-recovery-1',
};

export const adminStorageState = 'tests/e2e/.auth/admin.json';

/** Pemeriksaan WCAG 2.0/2.1/2.2 level A & AA. Gagal bila ada satu pelanggaran pun. */
export async function expectNoA11yViolations(page: Page): Promise<void> {
    // Periksa tampilan akhir, bukan frame di tengah animasi (mis. toast yang sedang fade-in).
    await page.waitForFunction(() =>
        document.getAnimations().every((a) => a.playState !== 'running'),
    );

    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
        .analyze();

    const summary = results.violations.map(
        (v) =>
            `${v.id} (${v.impact}): ${v.help} → ${v.nodes.map((n) => `${n.target.join(' ')} [${n.failureSummary ?? ''}]`).join(', ')}`,
    );
    expect(summary, summary.join('\n')).toEqual([]);
}

/**
 * Tekan Tab sampai `target` mendapat fokus (maks. `limit` kali). Membuktikan elemen bisa
 * dijangkau keyboard dalam urutan fokus, bukan hanya diisi lewat API.
 */
export async function tabTo(
    page: Page,
    target: import('@playwright/test').Locator,
    limit = 15,
): Promise<void> {
    for (let i = 0; i < limit; i++) {
        if (await target.evaluate((el) => el === document.activeElement)) {
            return;
        }
        await page.keyboard.press('Tab');
    }
    await expect(target).toBeFocused();
}
