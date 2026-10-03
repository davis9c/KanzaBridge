<!--
    Tombol 3 state: Terang / Gelap / Otomatis (ikuti sistem).
    Memakai partial/theme-init.php sebagai sumber kebenaran.
-->
<li class="nav-item dropdown">
    <button class="btn btn-link nav-link dropdown-toggle" type="button" id="themeDropdown"
        data-bs-toggle="dropdown" aria-expanded="false" title="Ganti tema">
        <i class="fas fa-circle-half-stroke" id="themeToggleIcon" aria-hidden="true"></i>
        <span class="visually-hidden" id="themeToggleLabel">Ganti tema</span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="themeDropdown">
        <li>
            <button type="button" class="dropdown-item" data-theme-value="light">
                <i class="fas fa-sun me-2" aria-hidden="true"></i>Terang
            </button>
        </li>
        <li>
            <button type="button" class="dropdown-item" data-theme-value="dark">
                <i class="fas fa-moon me-2" aria-hidden="true"></i>Gelap
            </button>
        </li>
        <li>
            <button type="button" class="dropdown-item" data-theme-value="auto">
                <i class="fas fa-circle-half-stroke me-2" aria-hidden="true"></i>Otomatis
            </button>
        </li>
    </ul>
</li>

<script>
    (() => {
        'use strict';

        const api = window.ThemeToggle;
        if (!api) {
            return; // partial/theme-init.php belum termuat.
        }

        const ICONS = {
            light: 'fa-sun',
            dark: 'fa-moon',
            auto: 'fa-circle-half-stroke'
        };
        const LABELS = {
            light: 'Terang',
            dark: 'Gelap',
            auto: 'Otomatis (ikuti sistem)'
        };

        const icon = document.getElementById('themeToggleIcon');
        const label = document.getElementById('themeToggleLabel');
        const toggle = document.getElementById('themeDropdown');
        const items = document.querySelectorAll('[data-theme-value]');

        const paint = (theme) => {
            // Ikon tombol selalu menunjukkan mode yang benar-benar aktif.
            const active = api.resolve(theme);

            if (icon) {
                icon.classList.remove(...Object.values(ICONS));
                icon.classList.add(ICONS[active]);
            }
            if (label) {
                label.textContent = `Tema: ${LABELS[theme]}`;
            }
            if (toggle) {
                toggle.setAttribute('aria-label', `Tema aktif: ${LABELS[active]}`);
            }

            items.forEach((item) => {
                item.classList.toggle('active', item.dataset.themeValue === theme);
            });
        };

        const select = (theme) => {
            api.write(theme);
            api.apply(theme);
            paint(theme);
        };

        items.forEach((item) => {
            item.addEventListener('click', () => select(item.dataset.themeValue));
        });

        // Ganti tema OS saat mode 'auto' -> tombol ikut berubah tanpa reload.
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
            paint(api.current());
        });

        paint(api.current());
    })();
</script>