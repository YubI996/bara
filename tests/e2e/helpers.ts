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
