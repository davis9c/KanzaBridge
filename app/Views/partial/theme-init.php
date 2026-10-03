<!--
    Menentukan color mode SEBELUM CSS digambar.

    Bootstrap 5.3 hanya memicu dark mode lewat atribut `data-bs-theme`
    (bukan `prefers-color-scheme`, dan tidak ada JS API-nya), jadi atribut ini
    harus dipasang sedini mungkin. Kalau ditunda sampai body, user akan melihat
    kedipan putih sebelum tema gelap aktif (FOUC).

    Nilai yang disimpan di localStorage:
      - 'light' / 'dark' -> pilihan eksplisit user
      - 'auto' atau kosong -> ikut preferensi tema sistem operasi
-->
<script>
    (() => {
        'use strict';

        const STORAGE_KEY = 'theme';
        const DARK_QUERY = '(prefers-color-scheme: dark)';

        const read = () => {
            try {
                return window.localStorage.getItem(STORAGE_KEY);
            } catch (e) {
                // Privy mode / cookie diblokir -> jatuh ke mode auto.
                return null;
            }
        };

        const write = (theme) => {
            try {
                window.localStorage.setItem(STORAGE_KEY, theme);
            } catch (e) {
                // Diabaikan: tema tetap berlaku untuk halaman ini saja.
            }
        };

        const prefersDark = () => window.matchMedia(DARK_QUERY).matches;

        const resolve = (theme) => (theme === 'light' || theme === 'dark')
            ? theme
            : (prefersDark() ? 'dark' : 'light');

        const apply = (theme) => {
            document.documentElement.setAttribute('data-bs-theme', resolve(theme));
        };

        // Dipakai ulang oleh partial/theme-toggle.php.
        window.ThemeToggle = {
            read,
            write,
            resolve,
            apply,
            current: () => read() ?? 'auto',
            stored: read(),
            storedIsExplicit: () => {
                const theme = read();
                return theme === 'light' || theme === 'dark';
            },
            clear: () => write('auto')
        };

        apply(read());

        // Mode 'auto' harus tetap sinkron ketika user mengganti tema OS
        // tanpa me-reload halaman.
        window.matchMedia(DARK_QUERY).addEventListener('change', () => {
            if (window.ThemeToggle.storedIsExplicit()) {
                return;
            }
            apply('auto');
        });
    })();
</script>