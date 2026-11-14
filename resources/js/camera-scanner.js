// Camera barcode scanning for <x-camera-scanner>.
//
// Uses the browser's own BarcodeDetector where it reads retail barcodes (Chrome on
// Android / macOS). Elsewhere (Safari, Firefox, Chrome on Windows) the barcode-detector
// ponyfill reads them with zxing-wasm; its .wasm file is bundled by Vite and served from
// this site, never from a CDN.
import zxingWasmUrl from 'zxing-wasm/reader/zxing_reader.wasm?url';

// ISBN and other retail barcodes are EAN/UPC; Code 128 covers catalogue numbers.
const FORMATS = ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128'];
const SCAN_INTERVAL_MS = 200;
// In batch mode the same code stays in view for a while; don't count it twice.
const REPEAT_IGNORE_MS = 2500;

let detectorPromise = null;

function getDetector() {
    detectorPromise ??= (async () => {
        if ('BarcodeDetector' in window) {
            try {
                const supported = await window.BarcodeDetector.getSupportedFormats();
                const formats = FORMATS.filter((format) => supported.includes(format));
                if (formats.length > 0) {
                    return new window.BarcodeDetector({ formats });
                }
            } catch {
                // Fall through to the ponyfill.
            }
        }

        const { BarcodeDetector, prepareZXingModule } = await import('barcode-detector/ponyfill');
        prepareZXingModule({
            overrides: {
                locateFile: (path, prefix) => (path.endsWith('.wasm') ? zxingWasmUrl : prefix + path),
            },
        });

        return new BarcodeDetector({ formats: FORMATS });
    })();

    return detectorPromise;
}

function cameraProblem() {
    if (!window.isSecureContext) {
        return 'The camera only works on a secure address. Open the site through its https:// address (or on localhost), or type the barcode instead.';
    }
    if (!navigator.mediaDevices?.getUserMedia) {
        return 'This browser cannot use the camera. Type the barcode instead.';
    }

    return null;
}

function setUp(root) {
    const openButton = root.querySelector('[data-camera-open]');
    const dialog = root.querySelector('[data-camera-dialog]');
    const video = root.querySelector('[data-camera-video]');
    const status = root.querySelector('[data-camera-status]');
    const target = document.getElementById(root.dataset.target);
    const batch = root.dataset.mode === 'batch';
    const found = root.querySelector('[data-camera-found]');
    const count = root.querySelector('[data-camera-count]');
    const submitButton = root.querySelector('[data-camera-submit]');

    const problem = cameraProblem();
    if (problem || !target) {
        openButton.disabled = true;
        openButton.classList.add('opacity-50');
        const note = root.querySelector('[data-camera-unavailable]');
        note.textContent = problem ?? '';
        note.hidden = !problem;

        return;
    }

    let stream = null;
    let timer = null;
    let busy = false;
    let codes = [];
    const lastSeen = new Map();

    function stop() {
        clearInterval(timer);
        timer = null;
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
        video.srcObject = null;
    }

    function addCode(code) {
        const now = Date.now();
        if (now - (lastSeen.get(code) ?? 0) < REPEAT_IGNORE_MS) {
            lastSeen.set(code, now);

            return;
        }
        lastSeen.set(code, now);
        navigator.vibrate?.(60);

        if (!batch) {
            stop();
            dialog.close();
            target.value = code;
            target.form.requestSubmit();

            return;
        }

        if (codes.includes(code)) {
            status.textContent = `${code} is already in this list.`;

            return;
        }

        codes.push(code);
        const item = document.createElement('li');
        item.textContent = code;
        found.prepend(item);
        count.textContent = String(codes.length);
        submitButton.disabled = false;
        status.textContent = `Scanned ${code}. Point at the next one.`;
    }

    async function scanFrame(detector) {
        if (busy || video.readyState < 2) {
            return;
        }
        busy = true;
        try {
            const barcodes = await detector.detect(video);
            const code = barcodes.find((barcode) => barcode.rawValue)?.rawValue.trim();
            if (code) {
                addCode(code);
            }
        } catch {
            // A frame that can't be read; try the next one.
        } finally {
            busy = false;
        }
    }

    async function start() {
        codes = [];
        lastSeen.clear();
        if (found) {
            found.replaceChildren();
            count.textContent = '0';
            submitButton.disabled = true;
        }
        status.textContent = 'Starting the camera…';
        dialog.showModal();

        try {
            const [detector, media] = await Promise.all([
                getDetector(),
                navigator.mediaDevices.getUserMedia({
                    audio: false,
                    video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } },
                }),
            ]);

            if (!dialog.open) {
                // Closed while the permission prompt was up.
                media.getTracks().forEach((track) => track.stop());

                return;
            }

            stream = media;
            video.srcObject = stream;
            await video.play();
            status.textContent = batch
                ? 'Point the camera at a barcode. Every new code is added below.'
                : 'Point the camera at the barcode.';
            timer = setInterval(() => scanFrame(detector), SCAN_INTERVAL_MS);
        } catch (error) {
            stop();
            status.textContent = error?.name === 'NotAllowedError'
                ? 'Camera access was blocked. Allow the camera for this site in the browser settings, then try again.'
                : error?.name === 'NotFoundError'
                    ? 'No camera was found on this device.'
                    : 'The camera could not be started. Type the barcode instead.';
        }
    }

    openButton.addEventListener('click', start);
    root.querySelectorAll('[data-camera-close]').forEach((button) => button.addEventListener('click', () => dialog.close()));
    // Esc, Close and Cancel all end here: always release the camera.
    dialog.addEventListener('close', stop);

    submitButton?.addEventListener('click', () => {
        if (codes.length === 0) {
            return;
        }
        const existing = target.value.trim();
        target.value = [existing, ...codes].filter(Boolean).join('\n');
        stop();
        dialog.close();
        target.closest('details')?.setAttribute('open', '');
        target.form.requestSubmit();
    });
}

document.querySelectorAll('[data-camera-scanner]').forEach(setUp);
