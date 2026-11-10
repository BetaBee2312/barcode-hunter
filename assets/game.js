(() => {
    'use strict';

    const app = document.getElementById('gameApp');
    if (!app) return;

    const cfg = window.BH_GAME_PAGE || { baseUrl: '', csrf: '', inactivitySeconds: 10 };
    const apiBase = `${cfg.baseUrl}/session.php`;
    const inactivityMs = Math.max(5, Number(cfg.inactivitySeconds || 10)) * 1000;

    const timerValue = document.getElementById('timerValue');
    const progressText = document.getElementById('progressText');
    const progressBar = document.getElementById('progressBar');
    const hintText = document.getElementById('hintText');
    const startScannerButton = document.getElementById('startScanner');
    const scannerPlaceholder = document.getElementById('scannerPlaceholder');
    const scannerStatus = document.getElementById('scannerStatus');
    const pauseOverlay = document.getElementById('pauseOverlay');
    const completeOverlay = document.getElementById('completeOverlay');
    const resumeButton = document.getElementById('resumeButton');
    const pauseTitle = document.getElementById('pauseTitle');
    const pauseDescription = document.getElementById('pauseDescription');
    const finishButton = document.getElementById('finishButton');
    const toast = document.getElementById('gameToast');

    let scanner = null;
    let scannerRunning = false;
    let scanBusy = false;
    let lastHeartbeatAt = 0;
    let heartbeatInFlight = false;
    let inactivityTimer = null;
    let toastTimer = null;
    let currentStatus = 'active';

    // Timer lokal berjalan dengan jam monotonik browser. Server tetap menjadi sumber kebenaran.
    let timerAnchorRemaining = 0;
    let timerAnchorAt = performance.now();
    let timerInitialized = false;
    let lastRenderedSeconds = null;
    let timerFrameId = null;
    let stateRequestPromise = null;
    let expiryRefreshPending = false;

    async function request(action, payload = null, options = {}) {
        const url = `${apiBase}?action=${encodeURIComponent(action)}`;
        const isGet = options.method === 'GET';
        const response = await fetch(url, {
            method: isGet ? 'GET' : 'POST',
            credentials: 'same-origin',
            keepalive: Boolean(options.keepalive),
            headers: isGet ? {} : {
                'Content-Type': 'application/json',
                'X-CSRF-Token': cfg.csrf
            },
            body: isGet ? undefined : JSON.stringify({ ...(payload || {}), csrf_token: cfg.csrf })
        });

        let data;
        try {
            data = await response.json();
        } catch (_) {
            throw new Error(`Server mengirim respons tidak valid (HTTP ${response.status}).`);
        }

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Permintaan gagal.');
        }
        return data;
    }

    function showToast(text) {
        if (!toast) return;
        toast.textContent = text;
        toast.classList.add('is-visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('is-visible'), 2600);
    }

    function formatTime(seconds) {
        const safe = Math.max(0, Math.floor(Number(seconds) || 0));
        const minutes = Math.floor(safe / 60).toString().padStart(2, '0');
        const secs = (safe % 60).toString().padStart(2, '0');
        return `${minutes}:${secs}`;
    }

    function getRemainingAt(now = performance.now()) {
        if (!timerInitialized) return 0;
        if (currentStatus !== 'active') return Math.max(0, timerAnchorRemaining);
        const elapsedSeconds = Math.max(0, (now - timerAnchorAt) / 1000);
        return Math.max(0, timerAnchorRemaining - elapsedSeconds);
    }

    function updateTimerText(force = false) {
        if (!timerValue || !timerInitialized) return;
        const seconds = Math.max(0, Math.ceil(getRemainingAt()));
        if (force || seconds !== lastRenderedSeconds) {
            timerValue.textContent = formatTime(seconds);
            lastRenderedSeconds = seconds;
        }
    }

    function syncTimerFromServer(data, requestStartedAt, responseReceivedAt, previousStatus) {
        const serverRemaining = Math.max(0, Number(data.remaining) || 0);
        const requestDuration = Math.max(0, (responseReceivedAt - requestStartedAt) / 1000);

        if (data.status === 'active') {
            // Kurangi waktu perjalanan request supaya respons jaringan tidak menambah waktu pemain.
            const correctedRemaining = Math.max(0, serverRemaining - requestDuration);

            if (!timerInitialized || previousStatus !== 'active') {
                timerAnchorRemaining = correctedRemaining;
                timerAnchorAt = responseReceivedAt;
                timerInitialized = true;
            } else {
                const locallyPredicted = getRemainingAt(responseReceivedAt);
                const drift = correctedRemaining - locallyPredicted;

                // Abaikan selisih kecil akibat pembulatan detik MySQL. Hindari timer melompat tiap polling.
                if (Math.abs(drift) >= 1.75) {
                    timerAnchorRemaining = correctedRemaining;
                    timerAnchorAt = responseReceivedAt;
                }
            }
        } else {
            timerAnchorRemaining = serverRemaining;
            timerAnchorAt = responseReceivedAt;
            timerInitialized = true;
        }

        updateTimerText(true);
    }

    function renderState(data, requestStartedAt, responseReceivedAt) {
        const previousStatus = currentStatus;
        currentStatus = data.status;
        syncTimerFromServer(data, requestStartedAt, responseReceivedAt, previousStatus);

        const progress = Number(data.progress) || 0;
        const target = Number(data.target) || 0;
        progressText.textContent = `${progress}/${target}`;
        progressBar.style.width = target > 0 ? `${Math.min(100, (progress / target) * 100)}%` : '0%';
        hintText.textContent = data.hint || (data.status === 'completed' ? 'Semua petunjuk sudah ditemukan.' : 'Petunjuk belum tersedia.');

        if (data.status === 'paused') {
            pauseOverlay.hidden = false;
            completeOverlay.hidden = true;
            pauseTitle.textContent = 'Permainan dijeda';
            pauseDescription.textContent = 'Tekan tombol di bawah untuk melanjutkan permainan.';
            resumeButton.disabled = false;
            resumeButton.textContent = 'Lanjutkan Permainan';
            scannerStatus.textContent = 'Permainan dijeda';
            stopScanner();
            clearTimeout(inactivityTimer);
        } else if (data.status === 'completed') {
            pauseOverlay.hidden = true;
            completeOverlay.hidden = false;
            scannerStatus.textContent = 'Permainan selesai';
            document.getElementById('completeMessage').textContent = `Kamu berhasil mengumpulkan ${progress} dari ${target} barcode.`;
            stopScanner();
            clearTimeout(inactivityTimer);
        } else if (data.status === 'expired') {
            pauseOverlay.hidden = false;
            completeOverlay.hidden = true;
            pauseTitle.textContent = 'Waktu permainan habis';
            pauseDescription.textContent = 'Sesi ini sudah berakhir dan tidak dapat dilanjutkan.';
            resumeButton.disabled = true;
            resumeButton.textContent = 'Waktu Habis';
            scannerStatus.textContent = 'Waktu permainan habis';
            stopScanner();
            clearTimeout(inactivityTimer);
        } else {
            pauseOverlay.hidden = true;
            completeOverlay.hidden = true;
            resumeButton.disabled = false;
            resumeButton.textContent = 'Lanjutkan Permainan';
            if (!scannerRunning) scannerStatus.textContent = 'Siap memindai barcode';
        }
    }

    async function refreshState() {
        // Cegah request state bertumpuk yang dapat membuat timer maju-mundur.
        if (stateRequestPromise) return stateRequestPromise;

        const requestStartedAt = performance.now();
        stateRequestPromise = (async () => {
            try {
                const data = await request('state', null, { method: 'GET' });
                const responseReceivedAt = performance.now();
                renderState(data, requestStartedAt, responseReceivedAt);
                return data;
            } catch (error) {
                scannerStatus.textContent = error.message;
                return null;
            } finally {
                stateRequestPromise = null;
            }
        })();

        return stateRequestPromise;
    }

    async function stopScanner() {
        if (!scanner || !scannerRunning) return;
        try { await scanner.stop(); } catch (_) { /* Kamera mungkin sudah berhenti. */ }
        try { await scanner.clear(); } catch (_) { /* Elemen scanner mungkin sudah dibersihkan. */ }
        scannerRunning = false;
        scanner = null;
        scannerPlaceholder.hidden = false;
    }

    async function startScanner() {
        if (currentStatus !== 'active') return;
        if (typeof Html5Qrcode === 'undefined') {
            showToast('Library scanner tidak berhasil dimuat. Periksa koneksi internet.');
            return;
        }
        if (scannerRunning) return;

        startScannerButton.disabled = true;
        startScannerButton.textContent = 'Membuka kamera...';

        try {
            const formats = [];
            const supported = window.Html5QrcodeSupportedFormats;
            if (supported) {
                ['QR_CODE', 'CODE_128', 'CODE_39', 'EAN_13', 'EAN_8', 'UPC_A', 'UPC_E', 'ITF', 'CODABAR']
                    .forEach((key) => {
                        if (supported[key] !== undefined) formats.push(supported[key]);
                    });
            }

            scanner = new Html5Qrcode(
                'reader',
                formats.length ? { formatsToSupport: formats, verbose: false } : { verbose: false }
            );

            await scanner.start(
                { facingMode: 'environment' },
                {
                    fps: 10,
                    qrbox: (width, height) => {
                        const size = Math.floor(Math.min(width, height, 230) * 0.78);
                        return { width: size, height: size };
                    },
                    aspectRatio: 1.0,
                    disableFlip: false
                },
                handleDecoded,
                () => {}
            );

            scannerRunning = true;
            clearTimeout(inactivityTimer);
            scannerPlaceholder.hidden = true;
            scannerStatus.textContent = 'Kamera aktif. Arahkan ke barcode.';
            noteActivity();
        } catch (error) {
            const detail = error && error.message ? ` (${error.message})` : '';
            scannerStatus.textContent = 'Kamera tidak dapat dibuka. Izinkan akses kamera dan gunakan HTTPS atau localhost.';
            showToast(`Tidak bisa membuka kamera pada perangkat ini${detail}`);
            if (scanner) {
                try { await scanner.clear(); } catch (_) {}
            }
            scanner = null;
            scannerRunning = false;
        } finally {
            startScannerButton.disabled = false;
            startScannerButton.textContent = 'Buka Kamera';
        }
    }

    async function handleDecoded(decodedText) {
        if (scanBusy || currentStatus !== 'active') return;
        scanBusy = true;
        noteActivity();
        scannerStatus.textContent = 'Memvalidasi barcode...';

        try {
            const result = await request('scan', { serial: String(decodedText || '').trim() });
            showToast(result.message || 'Barcode berhasil dipindai.');
            await refreshState();
            if (!result.completed && currentStatus === 'active') {
                scannerStatus.textContent = 'Arahkan kamera ke barcode berikutnya.';
            }
        } catch (error) {
            showToast(error.message || 'Barcode tidak valid.');
            scannerStatus.textContent = 'Coba pindai barcode yang sesuai dengan hint.';
        } finally {
            setTimeout(() => { scanBusy = false; }, 900);
        }
    }

    function resetInactivityTimer() {
        clearTimeout(inactivityTimer);
        if (currentStatus !== 'active' || scannerRunning) return;
        inactivityTimer = setTimeout(
            () => pauseGame('Permainan dijeda karena tidak ada aktivitas selama 10 detik.'),
            inactivityMs
        );
    }

    async function sendHeartbeat() {
        if (currentStatus !== 'active' || heartbeatInFlight) return;
        heartbeatInFlight = true;
        try {
            await request('heartbeat');
        } catch (_) {
            // Server akan memeriksa last_activity_at pada request berikutnya.
        } finally {
            heartbeatInFlight = false;
        }
    }

    function noteActivity() {
        if (currentStatus !== 'active') return;
        resetInactivityTimer();

        // Batasi heartbeat supaya interaksi layar tidak membanjiri server dengan request.
        if (Date.now() - lastHeartbeatAt >= 5000) {
            lastHeartbeatAt = Date.now();
            sendHeartbeat();
        }
    }

    async function pauseGame(message = '') {
        if (currentStatus !== 'active') return;
        currentStatus = 'paused';
        clearTimeout(inactivityTimer);

        try {
            await request('pause', null, { keepalive: true });
            await stopScanner();
            await refreshState();
            if (message) showToast(message);
        } catch (error) {
            currentStatus = 'active';
            resetInactivityTimer();
            showToast(error.message || 'Gagal menjeda permainan.');
        }
    }

    async function resumeGame() {
        resumeButton.disabled = true;
        try {
            await request('resume');
            currentStatus = 'active';
            await refreshState();
            noteActivity();
            await startScanner();
        } catch (error) {
            showToast(error.message || 'Permainan tidak bisa dilanjutkan.');
            resumeButton.disabled = false;
            await refreshState();
        }
    }

    function timerLoop() {
        if (currentStatus === 'active' && timerInitialized) {
            updateTimerText();

            // Saat mencapai nol, minta server memfinalisasi status expired.
            if (getRemainingAt() <= 0 && !expiryRefreshPending) {
                expiryRefreshPending = true;
                refreshState().finally(() => {
                    expiryRefreshPending = false;
                    if (currentStatus === 'active' && getRemainingAt() <= 0) {
                        window.setTimeout(() => {
                            if (currentStatus === 'active' && getRemainingAt() <= 0) refreshState();
                        }, 2500);
                    }
                });
            }

            // Kamera aktif mempertahankan heartbeat walau pengguna sedang mencari label.
            if (scannerRunning && Date.now() - lastHeartbeatAt >= 5000) {
                lastHeartbeatAt = Date.now();
                sendHeartbeat();
            }
        }

        timerFrameId = window.requestAnimationFrame(timerLoop);
    }

    startScannerButton.addEventListener('click', () => {
        noteActivity();
        startScanner();
    });
    resumeButton.addEventListener('click', resumeGame);
    finishButton.addEventListener('click', () => {
        window.location.href = `${cfg.baseUrl}/index.php?cabang=${encodeURIComponent(cfg.branch || '')}`;
    });

    ['pointerdown', 'touchstart', 'keydown', 'click'].forEach((eventName) => {
        document.addEventListener(eventName, noteActivity, { passive: true });
    });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            pauseGame('Permainan dijeda karena halaman ditinggalkan.');
        } else {
            refreshState();
        }
    });

    window.addEventListener('pagehide', () => {
        if (currentStatus === 'active') {
            fetch(`${apiBase}?action=pause`, {
                method: 'POST',
                credentials: 'same-origin',
                keepalive: true,
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': cfg.csrf },
                body: JSON.stringify({ csrf_token: cfg.csrf })
            }).catch(() => {});
        }
    });

    refreshState().then(() => {
        if (currentStatus === 'active') resetInactivityTimer();
    });

    // Satu frame loop mengatur tampilan timer. Tidak ada polling state setiap 2 detik.
    timerFrameId = window.requestAnimationFrame(timerLoop);

    window.addEventListener('beforeunload', () => {
        if (timerFrameId !== null) window.cancelAnimationFrame(timerFrameId);
        clearTimeout(inactivityTimer);
        clearTimeout(toastTimer);
    });
})();
