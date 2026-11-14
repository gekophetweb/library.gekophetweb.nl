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
