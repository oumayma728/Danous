(function () {
    const STORAGE_KEY = 'danous-theme';
    const root = document.documentElement;
    const sunIcon = '<circle cx="12" cy="12" r="4"></circle><path d="M12 2v2"></path><path d="M12 20v2"></path><path d="M4.93 4.93l1.41 1.41"></path><path d="M17.66 17.66l1.41 1.41"></path><path d="M2 12h2"></path><path d="M20 12h2"></path><path d="M6.34 17.66l-1.41 1.41"></path><path d="M19.07 4.93l-1.41 1.41"></path>';
    const moonIcon = '<path d="M21 12.8A8.5 8.5 0 1 1 11.2 3a6.5 6.5 0 0 0 9.8 9.8z"></path>';

    function getSavedTheme() {
        try {
            return localStorage.getItem(STORAGE_KEY);
        } catch {
            return null;
        }
    }

    function getCookieTheme() {
        const match = document.cookie.match(new RegExp('(?:^|; )' + STORAGE_KEY + '=([^;]+)'));
        return match ? decodeURIComponent(match[1]) : null;
    }

    function saveTheme(theme) {
        try {
            localStorage.setItem(STORAGE_KEY, theme);
        } catch {
            // Cookies keep the preference even when logout flows clear localStorage.
        }
        document.cookie = `${STORAGE_KEY}=${encodeURIComponent(theme)}; path=/; max-age=31536000; SameSite=Lax`;
    }

    function preferredTheme() {
        const saved = getSavedTheme() || getCookieTheme();
        if (saved === 'dark' || saved === 'light') return saved;
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function applyTheme(theme) {
        root.dataset.theme = theme;
        root.style.colorScheme = theme;
        saveTheme(theme);
        document.querySelectorAll('.theme-toggle').forEach((button) => updateButton(button, theme));
    }

    function updateButton(button, theme) {
        const nextTheme = theme === 'dark' ? 'light' : 'dark';
        button.setAttribute('aria-label', theme === 'dark' ? 'Activer le theme clair' : 'Activer le theme sombre');
        button.setAttribute('title', theme === 'dark' ? 'Theme clair' : 'Theme sombre');
        button.dataset.nextTheme = nextTheme;
        button.innerHTML = `<svg aria-hidden="true" viewBox="0 0 24 24">${theme === 'dark' ? sunIcon : moonIcon}</svg>`;
    }

    function createToggle(fixed) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = fixed ? 'theme-toggle theme-toggle-fixed' : 'theme-toggle';
        updateButton(button, root.dataset.theme || preferredTheme());
        button.addEventListener('click', () => {
            applyTheme(root.dataset.theme === 'dark' ? 'light' : 'dark');
        });
        return button;
    }

    function mountToggle() {
        if (document.querySelector('.theme-toggle')) return;

        const navHost = document.querySelector('.nav-user');
        if (navHost) {
            const logoutButton = navHost.querySelector('button');
            navHost.insertBefore(createToggle(false), logoutButton || null);
            return;
        }

        const userInfo = document.querySelector('.user-info');
        if (userInfo) {
            const logoutButton = userInfo.querySelector('button');
            userInfo.insertBefore(createToggle(false), logoutButton || null);
            return;
        }

        const navbarLinks = document.querySelector('.navbar .nav-links');
        if (navbarLinks) {
            navbarLinks.appendChild(createToggle(false));
            return;
        }

        document.body.appendChild(createToggle(true));
    }

    applyTheme(preferredTheme());

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mountToggle);
    } else {
        mountToggle();
    }
}());
