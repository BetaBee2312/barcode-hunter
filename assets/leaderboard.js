(() => {
    'use strict';
    const config = window.BH_LEADERBOARD_CONFIG || {};
    const list = document.getElementById('leaderboardList');
    const total = document.getElementById('participantTotal');
    const updated = document.getElementById('lastUpdated');
    const branchName = document.querySelector('.branch-name');
    if (!list || !config.cabang) return;

    const endpoint = (config.baseUrl || '') + '/leaderboard.php?cabang=' + encodeURIComponent(config.cabang) + '&format=json';
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[char]));

    function rankBadge(rank) {
        const colors = { 1: 'gold', 2: 'silver', 3: 'bronze' };
        if (rank > 3) return '<span class="rank-number">' + rank + '</span>';
        return '<span class="rank-number rank-trophy-' + colors[rank] + '" aria-label="Peringkat ' + rank + '">' +
            '<svg class="rank-trophy" viewBox="0 0 24 24" aria-hidden="true">' +
            '<path d="M7 3h10v3h4v3a5 5 0 0 1-5 5h-.7a5.5 5.5 0 0 1-2.3 2.4V19h4v2H7v-2h4v-2.6A5.5 5.5 0 0 1 8.7 14H8a5 5 0 0 1-5-5V6h4V3Zm0 5H5v1a3 3 0 0 0 2.5 2.96A7.6 7.6 0 0 1 7 8Zm10 0a7.6 7.6 0 0 1-.5 3.96A3 3 0 0 0 19 9V8h-2Z"/></svg>' +
            '</span>';
    }

    let inFlight = false;
    let lastPayload = '';

    function render(data) {
        const rows = Array.isArray(data.data) ? data.data.slice(0, 5) : [];
        if (total) total.textContent = Number(data.total_peserta || rows.length).toLocaleString('id-ID');
        if (branchName && data.nama_cabang) branchName.textContent = data.nama_cabang;

        const markup = rows.length ? rows.map((row) => {
            const rank = Number(row.rank) || 0;
            const rankClass = rank <= 3 ? ' rank-' + rank : '';
            return '<article class="leaderboard-row' + rankClass + '">' +
                '<div class="rank-cell">' + rankBadge(rank) + '</div>' +
                '<div class="player-cell"><div class="player-name">' + escapeHtml(row.nama) + '</div>' +
                '<div class="player-phone">' + escapeHtml(row.telepon) + '</div></div>' +
                '<div class="point-cell"><span class="point-pill">' + escapeHtml(row.point) + '</span></div></article>';
        }).join('') : '<div class="leaderboard-message"><strong>Belum ada peserta yang mencetak poin</strong><span>Hasil scan akan muncul otomatis di layar ini.</span></div>';

        if (markup !== lastPayload) {
            list.innerHTML = markup;
            lastPayload = markup;
        }
        list.setAttribute('aria-busy', 'false');
        if (updated) {
            updated.textContent = 'Update ' + new Intl.DateTimeFormat('id-ID', {
                hour: '2-digit', minute: '2-digit', second: '2-digit'
            }).format(new Date());
        }
    }

    async function refresh() {
        if (inFlight || document.hidden) return;
        inFlight = true;
        try {
            const response = await fetch(endpoint, {
                method: 'GET', cache: 'no-store', credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            });
            const payload = await response.json();
            if (!response.ok || !payload.success) throw new Error(payload.message || 'Gagal memperbarui leaderboard.');
            render(payload);
        } catch (_) {
            if (updated) updated.textContent = 'Koneksi terputus, mencoba kembali...';
            list.setAttribute('aria-busy', 'false');
        } finally {
            inFlight = false;
        }
    }

    refresh();
    window.setInterval(refresh, 5000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refresh();
    });
})();
