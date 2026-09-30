/**
 * Lingkungan E2E: database terpisah dan rahasia PII sintetis (bukan untuk produksi).
 * Dipakai global setup (seeder) dan web server agar enkripsi NIK konsisten.
 */
export const e2eEnv = {
    DB_DATABASE: process.env.E2E_DB_DATABASE ?? 'bara_e2e',
    BARA_NIK_PEPPER: 'e2e-pepper-0123456789abcdef0123456789abcdef',
    BARA_PII_KEY: 'base64:MDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDA=',
};
