// Camera scanning is only loaded on pages that have a scanner.
if (document.querySelector('[data-camera-scanner]')) {
    import('./camera-scanner.js');
}

// Copy buttons (<x-copy-button>): copy data-copy, then show "Copied" for a moment.
// navigator.clipboard only exists on https/localhost; over plain http (LAN access) fall
// back to a hidden textarea + execCommand, and if that fails too, say how to copy.
const COPY_FEEDBACK_MS = 2000;

async function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch {
            // Permission denied or document not focused: try the fallback.
        }
    }

    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.append(textarea);
    textarea.select();

    let copied = false;
    try {
        copied = document.execCommand('copy');
    } catch {
        copied = false;
    }
    textarea.remove();

    return copied;
}

document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy]');
    if (!button) {
        return;
    }

    const label = button.querySelector('[data-copy-label]');
    const copied = await copyText(button.dataset.copy);

    button.dataset.state = copied ? 'copied' : 'failed';
    if (label) {
        label.textContent = copied ? 'Copied!' : 'Select and press Ctrl+C';
    }

    clearTimeout(Number(button.dataset.copyTimer));
    button.dataset.copyTimer = String(setTimeout(() => {
        button.dataset.state = 'idle';
        if (label) {
            label.textContent = label.dataset.idleText;
        }
    }, copied ? COPY_FEEDBACK_MS : COPY_FEEDBACK_MS * 2));
});

// SSO provider form: show only the setup guide for the selected provider type.
// Without JavaScript every guide stays visible.
const ssoDriverSelect = document.querySelector('[data-sso-driver-select]');

if (ssoDriverSelect) {
    const showGuide = () => {
        document.querySelectorAll('[data-sso-guide]').forEach((guide) => {
            guide.hidden = guide.dataset.ssoGuide !== ssoDriverSelect.value;
        });
    };

    ssoDriverSelect.addEventListener('change', showGuide);
    showGuide();
}

// Themes (plan redesign-themes). <html data-theme> is light or dark; data-theme-mode is
// what the user chose (system, light or dark). The inline script in the layout sets the
// first value; from here on "same as device" follows the device while the page is open.
const root = document.documentElement;
const darkQuery = window.matchMedia('(prefers-color-scheme: dark)');
const GUEST_THEME_KEY = 'library-theme';

function guestTheme() {
    try {
        return localStorage.getItem(GUEST_THEME_KEY);
    } catch {
        return null;
    }
}

function effectiveMode() {
    return (root.hasAttribute('data-guest') && guestTheme()) || root.dataset.themeMode;
}

function applyTheme() {
    const mode = effectiveMode();
    root.dataset.theme = mode === 'light' || mode === 'dark' ? mode : (darkQuery.matches ? 'dark' : 'light');
}

darkQuery.addEventListener('change', applyTheme);

// The sun/moon button. Signed in: a form that saves the choice (works without JS too);
// here it flips at once and saves in the background. Guests: remembered in this browser.
document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-theme-toggle]');
    if (!toggle) {
        return;
    }

    const form = toggle.closest('form[data-theme-toggle]');
    const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
    event.preventDefault();

    if (!form) {
        try {
            localStorage.setItem(GUEST_THEME_KEY, next);
        } catch {
            // Private mode: it still switches for this page.
        }
        root.dataset.theme = next;
        return;
    }

    root.dataset.themeMode = next;
    applyTheme();
    form.elements.mode.value = next;

    fetch(form.action, {
        method: 'POST',
        headers: { Accept: 'application/json' },
        body: new FormData(form),
        credentials: 'same-origin',
    }).catch(() => form.submit());

    // Keep Settings › Appearance in step when the button is used there.
    const modeRadio = document.querySelector(`[data-appearance-form] input[name="mode"][value="${next}"]`);
    if (modeRadio) {
        modeRadio.checked = true;
    }
});

// Settings › Appearance: the whole page previews each pick before it is saved.
const appearanceForm = document.querySelector('[data-appearance-form]');

if (appearanceForm) {
    const photoFields = appearanceForm.querySelector('[data-photo-fields]');
    const veilOutput = appearanceForm.querySelector('[data-veil-output]');
    const previews = document.querySelectorAll('[data-preview]');
    let objectUrl = null;

    const picked = (name) => appearanceForm.querySelector(`input[name="${name}"]:checked`)?.value;

    const preview = () => {
        const mode = picked('mode');
        if (mode) {
            root.dataset.themeMode = mode;
            applyTheme();
        }

        const accent = picked('accent');
        if (accent) {
            root.dataset.accent = accent;
            previews.forEach((figure) => (figure.dataset.accent = accent));
        }

        const background = picked('background');
        if (background) {
            root.dataset.bg = background;
            previews.forEach((figure) => (figure.dataset.bg = background));
            photoFields.hidden = background !== 'photo';
        }

        if (picked('headingFont')) {
            root.dataset.font = picked('headingFont');
        }

        if (picked('density')) {
            root.dataset.density = picked('density');
        }

        const veil = appearanceForm.elements.veil.value;
        root.style.setProperty('--veil', `${veil}%`);
        veilOutput.textContent = `${veil}%`;
    };

    appearanceForm.addEventListener('input', preview);

    // Show a chosen photo straight away (it is only stored on save).
    appearanceForm.elements.photo.addEventListener('change', (event) => {
        const file = event.target.files[0];
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }
        if (file && file.type.startsWith('image/')) {
            objectUrl = URL.createObjectURL(file);
            root.style.setProperty('--photo', `url("${objectUrl}")`);
        }
    });

    photoFields.hidden = picked('background') !== 'photo';
}
