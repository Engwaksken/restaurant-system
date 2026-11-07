{{--
    Camera barcode scanner shared by admin pages.
    Usage from JS: BarcodeScanner.open(function (code) { ... });
    Buttons with data-scan-target="#inputId" fill that input with the scanned code.
    Works with phone/laptop cameras (HTTPS required) and with USB scanners or
    typing via the manual entry box.
--}}
@once
<div class="modal fade" id="barcodeScannerModal" tabindex="-1" aria-labelledby="barcodeScannerLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="barcodeScannerLabel"><i class="fas fa-barcode me-2 text-primary"></i>Scan barcode</h5>
                    <small class="text-muted">Point the camera at the barcode, or type it below.</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="barcodeScannerView" class="barcode-view"></div>
                <div id="barcodeScannerStatus" class="small text-muted mt-2" role="status">Starting camera...</div>
                <form id="barcodeManualForm" class="input-group mt-3" autocomplete="off">
                    <span class="input-group-text"><i class="fas fa-keyboard"></i></span>
                    <input type="text" class="form-control font-monospace" id="barcodeManualInput" placeholder="Type or scan with a USB scanner" maxlength="64" aria-label="Barcode">
                    <button class="btn btn-primary" type="submit">Use code</button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .barcode-view { position: relative; overflow: hidden; min-height: 220px; border-radius: 12px; background: #111827; }
    .barcode-view video { width: 100% !important; border-radius: 12px; }
    .barcode-view::after { content: ""; position: absolute; left: 12%; right: 12%; top: 50%; height: 2px; background: rgba(var(--brand-rgb), .9); box-shadow: 0 0 12px rgba(var(--brand-rgb), .9); pointer-events: none; }
</style>
@endpush

@push('scripts')
<script>
window.BarcodeScanner = (function () {
    const LIB_URL = 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js';
    const modalEl = document.getElementById('barcodeScannerModal');
    const statusEl = document.getElementById('barcodeScannerStatus');
    const manualForm = document.getElementById('barcodeManualForm');
    const manualInput = document.getElementById('barcodeManualInput');
    let scanner = null;
    let onCode = null;
    let libPromise = null;

    function loadLibrary() {
        if (window.Html5Qrcode) { return Promise.resolve(); }
        if (!libPromise) {
            libPromise = new Promise(function (resolve, reject) {
                const script = document.createElement('script');
                script.src = LIB_URL;
                script.onload = resolve;
                script.onerror = function () { libPromise = null; reject(new Error('Scanner library failed to load')); };
                document.head.appendChild(script);
            });
        }
        return libPromise;
    }

    function stopCamera() {
        if (scanner) {
            const current = scanner;
            scanner = null;
            current.stop().catch(function () {}).finally(function () { try { current.clear(); } catch (e) {} });
        }
    }

    function deliver(code) {
        code = String(code || '').trim();
        if (!code) { return; }
        const callback = onCode;
        onCode = null;
        if (navigator.vibrate) { navigator.vibrate(60); }
        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        if (callback) { callback(code); }
    }

    function startCamera() {
        if (!window.isSecureContext || !navigator.mediaDevices) {
            statusEl.textContent = 'Camera scanning needs a secure (https) connection. Type the code below or use a USB scanner.';
            return;
        }
        statusEl.textContent = 'Starting camera...';
        loadLibrary().then(function () {
            const formats = window.Html5QrcodeSupportedFormats;
            scanner = new Html5Qrcode('barcodeScannerView', {
                verbose: false,
                formatsToSupport: formats ? [formats.EAN_13, formats.EAN_8, formats.UPC_A, formats.UPC_E, formats.CODE_128, formats.CODE_39, formats.ITF, formats.QR_CODE] : undefined,
                experimentalFeatures: { useBarCodeDetectorIfSupported: true },
            });
            return scanner.start(
                { facingMode: 'environment' },
                { fps: 12, qrbox: function (w, h) { return { width: Math.floor(w * 0.8), height: Math.floor(Math.min(h, w) * 0.45) }; } },
                function (decoded) { stopCamera(); deliver(decoded); },
                function () { /* no barcode in this frame */ }
            ).then(function () {
                statusEl.textContent = 'Hold the barcode inside the frame.';
            });
        }).catch(function (error) {
            statusEl.textContent = (error && /Permission|NotAllowed/i.test(String(error)))
                ? 'Camera permission was denied. Allow camera access, or type the code below.'
                : 'Camera unavailable. Type the code below or use a USB scanner.';
        });
    }

    modalEl.addEventListener('shown.bs.modal', function () {
        manualInput.value = '';
        startCamera();
        if (!('ontouchstart' in window)) { manualInput.focus(); }
    });
    modalEl.addEventListener('hidden.bs.modal', stopCamera);
    manualForm.addEventListener('submit', function (event) {
        event.preventDefault();
        stopCamera();
        deliver(manualInput.value);
    });

    document.addEventListener('click', function (event) {
        const button = event.target.closest('[data-scan-target]');
        if (!button) { return; }
        event.preventDefault();
        const input = document.querySelector(button.dataset.scanTarget);
        open(function (code) {
            if (input) { input.value = code; input.dispatchEvent(new Event('input', { bubbles: true })); }
        });
    });

    function open(callback) {
        onCode = callback;
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    return { open: open };
})();
</script>
@endpush
@endonce
