/* Iksan Knalpot - perilaku global sisi peramban.
   Tanpa pustaka besar. Alpine disediakan Livewire. */

const THEME_KEY = 'iksan.theme';
const LANG_KEY = 'iksan.lang';

/** Terapkan tema sebelum first paint. Dipanggil inline di <head>. */
function applyStoredTheme() {
    const stored = localStorage.getItem(THEME_KEY);
    const theme =
        stored === 'light' || stored === 'dark'
            ? stored
            : window.matchMedia('(prefers-color-scheme: dark)').matches
              ? 'dark'
              : 'light';
    document.documentElement.setAttribute('data-theme', theme);
}

applyStoredTheme();

/** Ikuti perubahan preferensi sistem bila pengguna belum memilih manual. */
window
    .matchMedia('(prefers-color-scheme: dark)')
    .addEventListener('change', (event) => {
        if (!localStorage.getItem(THEME_KEY)) {
            document.documentElement.setAttribute(
                'data-theme',
                event.matches ? 'dark' : 'light',
            );
        }
    });

window.iksanTheme = {
    get current() {
        return document.documentElement.getAttribute('data-theme') || 'light';
    },
    set(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem(THEME_KEY, theme);
    },
    toggle() {
        this.set(this.current === 'dark' ? 'light' : 'dark');
    },
};

window.iksanLang = {
    get current() {
        return document.documentElement.getAttribute('lang') || 'id';
    },
    set(locale) {
        localStorage.setItem(LANG_KEY, locale);
        document.documentElement.setAttribute('lang', locale);
    },
};

/* Bar navigasi bawah disembunyikan saat mengetik, agar tidak menutupi keyboard. */
document.addEventListener(
    'focusin',
    (event) => {
        const target = event.target;
        const typing =
            target instanceof HTMLElement &&
            (target.tagName === 'INPUT' ||
                target.tagName === 'TEXTAREA' ||
                target.isContentEditable) &&
            target.dataset.hideBottomNav !== 'false';
        document.documentElement.toggleAttribute('data-typing', typing);
    },
    true,
);

document.addEventListener(
    'focusout',
    () => document.documentElement.removeAttribute('data-typing'),
    true,
);
