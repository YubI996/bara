/** Tautan pertama di halaman untuk melewati navigasi sidebar (WCAG 2.4.1, A11Y-008). */
export function SkipLink() {
    return (
        <a
            href="#main-content"
            className="sr-only z-50 rounded-md bg-background px-4 py-3 font-medium text-foreground shadow-lg focus:not-sr-only focus:fixed focus:top-3 focus:left-3"
        >
            Langsung ke konten utama
        </a>
    );
}
