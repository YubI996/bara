// Cek kontras token warna (docs/11 §3.3). Axe tidak memeriksa kontras non-teks & indikator fokus,
// jadi pasangan token kritis diverifikasi di sini dan dijalankan di CI (`npm run check:contrast`).
import { readFileSync } from 'node:fs';

const css = readFileSync(
    new URL('../resources/css/app.css', import.meta.url),
    'utf8',
);

function tokens(selector) {
    const block = css.match(
        new RegExp(`${selector.replace('.', '\\.')}\\s*\\{([^}]*)\\}`),
    );
    if (!block) throw new Error(`Blok ${selector} tidak ditemukan`);
    const out = {};
    for (const m of block[1].matchAll(/--([\w-]+):\s*oklch\(([^)]+)\)/g)) {
        out[m[1]] = m[2].trim().split(/\s+/).map(Number);
    }
    return out;
}

// OKLCH → sRGB linear (Björn Ottosson), lalu luminans relatif WCAG.
function luminance([l, c, h]) {
    const a = c * Math.cos((h * Math.PI) / 180);
    const b = c * Math.sin((h * Math.PI) / 180);
    const l_ = (l + 0.3963377774 * a + 0.2158037573 * b) ** 3;
    const m_ = (l - 0.1055613458 * a - 0.0638541728 * b) ** 3;
    const s_ = (l - 0.0894841775 * a - 1.291485548 * b) ** 3;
    const rgb = [
        4.0767416621 * l_ - 3.3077115913 * m_ + 0.2309699292 * s_,
        -1.2684380046 * l_ + 2.6097574011 * m_ - 0.3413193965 * s_,
        -0.0041960863 * l_ - 0.7034186147 * m_ + 1.707614701 * s_,
    ].map((v) => Math.min(1, Math.max(0, v)));
    return 0.2126 * rgb[0] + 0.7152 * rgb[1] + 0.0722 * rgb[2];
}

const ratio = (x, y) => {
    const [hi, lo] = [luminance(x), luminance(y)].sort((p, q) => q - p);
    return (hi + 0.05) / (lo + 0.05);
};

// [foreground, background, minimum, alasan]
const pairs = [
    ['foreground', 'background', 4.5, 'teks utama'],
    ['muted-foreground', 'background', 4.5, 'teks sekunder'],
    ['destructive-foreground', 'background', 4.5, 'teks error/alert'],
    ['primary-foreground', 'primary', 4.5, 'tombol utama'],
    ['input', 'background', 3, 'batas kolom form (1.4.11)'],
    ['focus', 'background', 3, 'indikator fokus (2.4.7)'],
    ['focus', 'sidebar', 3, 'fokus di sidebar'],
    ['focus', 'sidebar-accent', 3, 'fokus di item sidebar aktif'],
    ['sidebar-foreground', 'sidebar', 4.5, 'teks sidebar'],
];

let failed = 0;
for (const mode of [':root', '.dark']) {
    const t = tokens(mode);
    for (const [fg, bg, min, why] of pairs) {
        if (!t[fg] || !t[bg]) {
            console.error(`✗ ${mode} token --${fg} / --${bg} tidak ada`);
            failed++;
            continue;
        }
        const r = ratio(t[fg], t[bg]);
        const ok = r >= min;
        if (!ok) failed++;
        console.log(
            `${ok ? '✓' : '✗'} ${mode.padEnd(6)} ${`--${fg} / --${bg}`.padEnd(44)} ${r.toFixed(2)}:1 (min ${min}) ${why}`,
        );
    }
}

process.exit(failed === 0 ? 0 : 1);
