/**
 * Light/dark switch of the lab's backend login (site_package Login layout).
 *
 * Sets data-color-scheme on <html>, which the Core backend CSS already
 * honours ([data-color-scheme=dark] { color-scheme: only dark }), and keeps
 * the choice for this browser. Without a stored choice the login follows the
 * operating system. The backend itself keeps the colour scheme from each
 * user's own settings. The button stays hidden without JavaScript.
 */
const storageKey = 'webcon-backend-login-color-scheme';
const root = document.documentElement;
const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');

const readStoredScheme = () => {
    try {
        const value = window.localStorage.getItem(storageKey);
        return value === 'dark' || value === 'light' ? value : null;
    } catch {
        return null;
    }
};

const storeScheme = (scheme) => {
    try {
        window.localStorage.setItem(storageKey, scheme);
    } catch {
        // Private mode or blocked storage: the switch still works for this page.
    }
};

const currentScheme = () => {
    const forced = root.dataset.colorScheme;
    if (forced === 'dark' || forced === 'light') {
        return forced;
    }
    return prefersDark.matches ? 'dark' : 'light';
};

const storedScheme = readStoredScheme();
if (storedScheme !== null) {
    root.dataset.colorScheme = storedScheme;
}

const initialize = () => {
    const button = document.querySelector('[data-webcon-login-color-scheme]');
    if (!(button instanceof HTMLButtonElement)) {
        return;
    }
    const sync = () => {
        const scheme = currentScheme();
        button.dataset.current = scheme;
        button.setAttribute('aria-label', scheme === 'dark' ? button.dataset.labelLight ?? '' : button.dataset.labelDark ?? '');
    };
    button.addEventListener('click', () => {
        const next = currentScheme() === 'dark' ? 'light' : 'dark';
        root.dataset.colorScheme = next;
        storeScheme(next);
        sync();
    });
    prefersDark.addEventListener('change', sync);
    sync();
    button.hidden = false;
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize, { once: true });
} else {
    initialize();
}
