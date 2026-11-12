(() => {
    'use strict';
    const cfg = window.BH_BARCODE_CONFIG || {};
    const api = `${String(cfg.apiBase || '').replace(/\/$/, '')}/barcode`;
    const branchApi = `${String(cfg.apiBase || '').replace(/\/$/, '')}/cabang/index.php`;
    const byId = (id) => document.getElementById(id);
    const form = byId('barcodeForm');
    if (!form) return;

    let branches = [];
    let barcodeRows = [];
    let editRecord = null;
    let downloadContext = null; // null = cetak daftar; object = unduh satu barcode

    const assignModalElement = byId('assignBarcodeModal');
    const assignModal = window.bootstrap ? new bootstrap.Modal(assignModalElement) : null;
    const downloadModalElement = byId('downloadBarcodeModal');
    const downloadModal = window.bootstrap ? new bootstrap.Modal(downloadModalElement) : null;

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[char]));

    const safeFilePart = (value) => String(value ?? '').normalize('NFKD').replace(/[^a-zA-Z0-9_-]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 70) || 'barcode';

    const alertBox = (icon, title, text) => {
        if (window.Swal) return Swal.fire({ icon, title, text, confirmButtonText: 'Mengerti', confirmButtonColor: '#315efb' });
        window.alert(`${title}\n${text}`);
        return Promise.resolve();
    };

    const request = async (url, options = {}) => {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers: {
                'Accept': 'application/json',
                ...(options.body ? { 'Content-Type': 'application/json', 'X-CSRF-Token': cfg.csrf || '' } : {}),
                ...(options.headers || {})
            }
        });
        let payload;
        try { payload = await response.json(); }
        catch (_) { throw new Error('Server tidak mengirim respons JSON yang valid.'); }
        if (!response.ok || !payload.success) throw new Error(payload.message || `Permintaan gagal (${response.status}).`);
        return payload;
    };

    const branchName = (row) => row.nama_cabang || `Cabang ${row.cabang}`;

    const branchOptionHtml = (includeAll = false) => {
        const first = includeAll ? '<option value="">Semua cabang</option>' : '<option value="">Pilih cabang</option>';
        return first + branches.map((branch) => `<option value="${escapeHtml(branch.kode_cabang)}">${escapeHtml(branch.nama_cabang)} (${escapeHtml(branch.kode_cabang)})</option>`).join('');
    };

    const loadBranches = async () => {
        const result = await request(branchApi);
        branches = Array.isArray(result.data) ? result.data : [];
        byId('barcodeBranch').innerHTML = branchOptionHtml();
        byId('filterBranch').innerHTML = branchOptionHtml(true);
        byId('assignBranch').innerHTML = branchOptionHtml();
        if (cfg.initialBranch) {
            byId('filterBranch').value = String(cfg.initialBranch);
            byId('barcodeBranch').value = String(cfg.initialBranch);
        }
        if (!branches.length) {
            byId('barcodeBranch').innerHTML = '<option value="">Tambahkan master cabang terlebih dahulu</option>';
            byId('saveBarcodeButton').disabled = true;
        }
        updateBulkPrintButton();
    };

    const updateBulkPrintButton = () => {
        const button = byId('printBranchLabels');
        if (!button) return;
        const hasBranch = Boolean(byId('filterBranch').value);
        button.disabled = !hasBranch || barcodeRows.length === 0;
        button.title = hasBranch ? 'Cetak label barcode yang sedang tampil pada cabang ini' : 'Pilih satu cabang terlebih dahulu';
    };

    const renderRows = (rows) => {
        const body = byId('barcodeTableBody');
        byId('barcodeTotal').textContent = Number(rows.length).toLocaleString('id-ID');
        if (!rows.length) {
            body.innerHTML = '<tr><td colspan="5"><div class="module-empty"><i class="bi bi-upc"></i><strong>Barcode tidak ditemukan</strong><span>Ubah filter atau tambahkan barcode baru.</span></div></td></tr>';
            updateBulkPrintButton();
            return;
        }
        body.innerHTML = rows.map((row) => `
            <tr>
                <td><span class="barcode-serial">${escapeHtml(row.barcode_serial)}</span><small class="text-secondary">${escapeHtml(row.id_brc)}</small></td>
                <td><span class="barcode-hint">${escapeHtml(row.hint || 'Belum ada hint')}</span></td>
                <td>${escapeHtml(branchName(row))}<small class="d-block text-secondary">${escapeHtml(row.cabang)}</small></td>
                <td><span class="number-pill">${Number(row.number_barcode).toLocaleString('id-ID')}</span></td>
                <td><div class="barcode-actions">
                    <span class="barcode-row-preview" title="Pratinjau CODE128 tanpa angka">
                        <svg class="barcode-row-preview-svg" data-serial="${escapeHtml(row.barcode_serial)}" role="img" aria-label="Pratinjau barcode"></svg>
                    </span>
                    <button class="btn btn-sm btn-outline-success" type="button" data-action="download" data-id="${escapeHtml(row.id_brc)}" title="Unduh barcode"><i class="bi bi-download"></i></button>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-action="edit" data-id="${escapeHtml(row.id_brc)}" title="Edit"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-outline-primary btn-transfer" type="button" data-action="assign" data-id="${escapeHtml(row.id_brc)}"><i class="bi bi-arrow-left-right me-1"></i>Pindah</button>
                    <button class="btn btn-sm btn-outline-danger" type="button" data-action="delete" data-id="${escapeHtml(row.id_brc)}" title="Hapus"><i class="bi bi-trash3"></i></button>
                </div></td>
            </tr>`).join('');
        renderBarcodePreviews();
        updateBulkPrintButton();
    };

    const renderBarcodePreviews = () => {
        const previews = document.querySelectorAll('.barcode-row-preview-svg[data-serial]');
        previews.forEach((svg) => {
            const serial = svg.dataset.serial || '';
            if (!serial || typeof window.JsBarcode !== 'function') {
                svg.closest('.barcode-row-preview')?.classList.add('is-unavailable');
                return;
            }
            try {
                window.JsBarcode(svg, serial, {
                    format: 'CODE128',
                    displayValue: false,
                    margin: 1,
                    width: 1.25,
                    height: 30,
                    lineColor: '#111111',
                    background: '#ffffff'
                });
            } catch (_) {
                svg.closest('.barcode-row-preview')?.classList.add('is-unavailable');
            }
        });
    };

    const loadBarcodes = async () => {
        const body = byId('barcodeTableBody');
        body.innerHTML = '<tr><td colspan="5"><div class="barcode-loading"><span class="spinner-border spinner-border-sm me-2"></span>Memuat barcode...</div></td></tr>';
        const params = new URLSearchParams();
        const branch = byId('filterBranch').value;
        const q = byId('barcodeSearch').value.trim();
        if (branch) params.set('cabang', branch);
        if (q) params.set('q', q);
        try {
            const result = await request(`${api}/index.php?${params.toString()}`);
            barcodeRows = Array.isArray(result.data) ? result.data : [];
            renderRows(barcodeRows);
        } catch (error) {
            barcodeRows = [];
            body.innerHTML = `<tr><td colspan="5"><div class="module-empty"><i class="bi bi-exclamation-circle"></i><strong>Gagal memuat barcode</strong><span>${escapeHtml(error.message)}</span></div></td></tr>`;
            byId('barcodeTotal').textContent = '0';
            updateBulkPrintButton();
        }
    };

    const resetForm = () => {
        editRecord = null;
        form.reset();
        byId('barcodeId').value = '';
        byId('barcodeBranch').disabled = false;
        byId('barcodeBranch').value = cfg.initialBranch ? String(cfg.initialBranch) : '';
        byId('formTitle').textContent = 'Tambah barcode';
        byId('formSubtitle').textContent = 'Masukkan serial, cabang, nomor barcode, dan petunjuk produk.';
        byId('saveBarcodeButton').innerHTML = '<i class="bi bi-plus-circle me-1"></i> Simpan barcode';
        byId('cancelEditButton').classList.add('d-none');
        byId('branchHelp').textContent = 'Barcode akan menjadi milik cabang yang dipilih.';
    };

    // Membuat gambar kode saja. Nilai yang dienkode selalu barcode_serial dari database.
    const makeCodeImage = async (row, format) => {
        if (format === 'linear') {
            if (typeof window.JsBarcode !== 'function') throw new Error('Library barcode belum termuat. Periksa koneksi internet/CDN.');
            const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            window.JsBarcode(svg, String(row.barcode_serial), {
                format: 'CODE128', displayValue: false, margin: 8,
                width: 2.4, height: 150, lineColor: '#111111', background: '#ffffff'
            });
            const svgText = new XMLSerializer().serializeToString(svg);
            const svgBlob = new Blob([svgText], { type: 'image/svg+xml;charset=utf-8' });
            const objectUrl = URL.createObjectURL(svgBlob);
            try {
                const image = new Image();
                image.src = objectUrl;
                await image.decode();
                const canvas = document.createElement('canvas');
                canvas.width = 1200;
                canvas.height = 330;
                const context = canvas.getContext('2d');
                context.fillStyle = '#ffffff';
                context.fillRect(0, 0, canvas.width, canvas.height);
                const scale = Math.min(1080 / image.width, 270 / image.height);
                const width = image.width * scale;
                const height = image.height * scale;
                context.drawImage(image, (canvas.width - width) / 2, (canvas.height - height) / 2, width, height);
                return canvas.toDataURL('image/png');
            } finally {
                URL.revokeObjectURL(objectUrl);
            }
        }

        if (format === 'qr') {
            if (typeof window.QRCode !== 'function') throw new Error('Library QR Code belum termuat. Periksa koneksi internet/CDN.');
            const holder = document.createElement('div');
            holder.style.cssText = 'position:fixed;left:-10000px;top:0;width:640px;height:640px;background:#fff;';
            document.body.appendChild(holder);
            try {
                new window.QRCode(holder, {
                    text: String(row.barcode_serial), width: 600, height: 600,
                    colorDark: '#000000', colorLight: '#ffffff',
                    correctLevel: window.QRCode.CorrectLevel.M
                });
                await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
                const sourceCanvas = holder.querySelector('canvas');
                const sourceImage = holder.querySelector('img');
                const canvas = document.createElement('canvas');
                canvas.width = 640;
                canvas.height = 640;
                const context = canvas.getContext('2d');
                context.fillStyle = '#ffffff';
                context.fillRect(0, 0, canvas.width, canvas.height);
                if (sourceCanvas) {
                    context.drawImage(sourceCanvas, 20, 20, 600, 600);
                } else if (sourceImage && sourceImage.complete) {
                    context.drawImage(sourceImage, 20, 20, 600, 600);
                } else {
                    throw new Error('QR Code gagal dibuat.');
                }
                return canvas.toDataURL('image/png');
            } finally {
                holder.remove();
            }
        }
        throw new Error('Format barcode tidak dikenal.');
    };

    // PNG unduhan hanya berisi simbol barcode. Tidak ada serial, ID, nomor urut,
    // nama cabang, atau teks lain yang digambar ke dalam file gambar.
    const makeDownloadLabel = async (row, format) => makeCodeImage(row, format);

    const downloadDataUrl = (dataUrl, filename) => {
        const link = document.createElement('a');
        link.href = dataUrl;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
    };

    const openDownloadModal = (row = null) => {
        downloadContext = row;
        byId('downloadBarcodeTitle').textContent = row ? 'Unduh barcode' : 'Cetak label satu cabang';
        byId('downloadBarcodeDescription').textContent = row
            ? `Barcode dari ${branchName(row)}.`
            : `${barcodeRows.length} barcode sesuai filter cabang saat ini.`;
        byId('downloadBarcodeNote').textContent = row
            ? 'PNG hanya berisi pola barcode. Serial, ID, nomor urut, dan nama cabang tidak dicetak sebagai tulisan.'
            : 'Setiap label cetak hanya berisi pola barcode tanpa serial, ID, nomor urut, atau nama cabang.';
        if (downloadModal) downloadModal.show();
    };

    const printLabelSheet = async (rows, format) => {
        // Buka tab secara sinkron agar tidak diblokir oleh popup blocker.
        const printWindow = window.open('', '_blank');
        if (!printWindow) throw new Error('Popup diblokir browser. Izinkan popup untuk halaman admin ini lalu coba lagi.');
        printWindow.document.open();
        printWindow.document.write('<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Menyiapkan label barcode...</title></head><body style="font-family:Arial,sans-serif;padding:24px">Menyiapkan label barcode...</body></html>');
        printWindow.document.close();

        try {
            const labels = [];
            for (const row of rows) {
                const codeUrl = await makeCodeImage(row, format);
                const image = format === 'linear'
                    ? `<img class="linear-code" src="${codeUrl}" alt="Simbol barcode linear">`
                    : `<img class="qr-code" src="${codeUrl}" alt="Simbol QR Code">`;
                // Label fisik juga hanya menampilkan simbol agar nomor/serial tidak
                // membocorkan urutan atau jawaban permainan yang diacak.
                labels.push(`<article class="label">${image}</article>`);
            }

            const titleFormat = format === 'linear' ? 'Barcode Linear CODE128' : 'QR Code';
            printWindow.document.open();
            printWindow.document.write(`<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Cetak ${titleFormat}</title>
<style>
@page{size:A4;margin:8mm}*{box-sizing:border-box}body{font-family:Arial,sans-serif;margin:0;color:#111;background:#fff}.toolbar{position:sticky;top:0;padding:12px;background:#f1f5f9;border-bottom:1px solid #cbd5e1;display:flex;justify-content:space-between;align-items:center;gap:12px}.toolbar button{border:0;border-radius:7px;padding:10px 16px;background:#1d4ed8;color:#fff;font-weight:700;cursor:pointer}.sheet{display:grid;grid-template-columns:repeat(3,60mm);grid-auto-rows:40mm;gap:3mm;justify-content:center;padding:5mm 0}.label{width:60mm;height:40mm;border:.25mm dashed #9ca3af;padding:2.5mm;display:flex;align-items:center;justify-content:center;overflow:hidden;break-inside:avoid}.linear-code{display:block;width:54mm;height:25mm;object-fit:contain}.qr-code{display:block;width:27mm;height:27mm;object-fit:contain}@media print{.toolbar{display:none}.sheet{padding:0;gap:3mm}}
</style></head><body>
<div class="toolbar"><span><strong>${rows.length} label</strong> · ${titleFormat}. Gunakan kertas label/stiker A4 atau kertas biasa lalu potong.</span><button onclick="window.print()">Cetak / Simpan PDF</button></div>
<main class="sheet">${labels.join('')}</main>
<script>window.addEventListener('load',()=>{setTimeout(()=>window.print(),500)});<\/script>
</body></html>`);
            printWindow.document.close();
        } catch (error) {
            printWindow.close();
            throw error;
        }
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const serial = byId('barcodeSerial').value.trim();
        const number = Number(byId('barcodeNumber').value);
        const hint = byId('barcodeHint').value.trim();
        if (!serial || !Number.isInteger(number) || number < 1 || !hint) {
            await alertBox('warning', 'Data belum lengkap', 'Isi serial, nomor barcode, dan petunjuk produk dengan benar.');
            return;
        }
        const button = byId('saveBarcodeButton');
        button.disabled = true;
        try {
            if (editRecord) {
                await request(`${api}/update.php`, {
                    method: 'POST',
                    body: JSON.stringify({ id_brc: editRecord.id_brc, barcode_serial: serial, number_barcode: number, hint })
                });
                await alertBox('success', 'Berhasil', 'Data barcode berhasil diperbarui.');
            } else {
                const branch = Number(byId('barcodeBranch').value);
                if (!Number.isInteger(branch) || branch < 1) throw new Error('Pilih cabang terlebih dahulu.');
                await request(`${api}/create.php`, {
                    method: 'POST',
                    body: JSON.stringify({ barcode_serial: serial, cabang: branch, number_barcode: number, hint })
                });
                await alertBox('success', 'Berhasil', 'Barcode berhasil ditambahkan ke cabang.');
            }
            resetForm();
            await loadBarcodes();
        } catch (error) {
            await alertBox('error', 'Gagal menyimpan', error.message);
        } finally {
            button.disabled = false;
        }
    });

    byId('cancelEditButton').addEventListener('click', resetForm);
    byId('barcodeFilterForm').addEventListener('submit', loadBarcodes);
    byId('filterBranch').addEventListener('change', loadBarcodes);
    byId('resetBarcodeFilter').addEventListener('click', () => {
        byId('barcodeSearch').value = '';
        byId('filterBranch').value = '';
        loadBarcodes();
    });
    byId('printBranchLabels').addEventListener('click', () => {
        if (!byId('filterBranch').value) {
            alertBox('info', 'Pilih cabang', 'Pilih satu cabang terlebih dahulu sebelum mencetak seluruh labelnya.');
            return;
        }
        if (!barcodeRows.length) {
            alertBox('info', 'Tidak ada barcode', 'Cabang ini belum memiliki barcode untuk dicetak.');
            return;
        }
        openDownloadModal(null);
    });

    byId('downloadBarcodeModal').addEventListener('click', async (event) => {
        const button = event.target.closest('button[data-format]');
        if (!button) return;
        const format = button.dataset.format;
        const isBulk = downloadContext === null;
        const rows = isBulk ? barcodeRows.slice() : [downloadContext];
        if (isBulk && !byId('filterBranch').value) {
            await alertBox('warning', 'Pilih cabang', 'Pilih satu cabang terlebih dahulu.');
            return;
        }
        button.disabled = true;
        const originalHtml = button.innerHTML;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyiapkan...';
        try {
            if (isBulk) {
                await printLabelSheet(rows, format);
                if (downloadModal) downloadModal.hide();
                await alertBox('success', 'Label siap dicetak', `Sebanyak ${rows.length} label berhasil disiapkan. Gunakan dialog cetak untuk mencetak atau menyimpan sebagai PDF.`);
            } else {
                const row = rows[0];
                const png = await makeDownloadLabel(row, format);
                const suffix = format === 'linear' ? 'linear-code128' : 'qrcode';
                downloadDataUrl(png, `${safeFilePart(row.id_brc)}-${safeFilePart(row.barcode_serial)}-${suffix}.png`);
                if (downloadModal) downloadModal.hide();
                await alertBox('success', 'Download dimulai', `Label ${format === 'linear' ? 'barcode linear CODE128' : 'QR Code'} berhasil dibuat dalam format PNG.`);
            }
        } catch (error) {
            await alertBox('error', 'Gagal membuat barcode', error.message);
        } finally {
            button.disabled = false;
            button.innerHTML = originalHtml;
        }
    });

    byId('barcodeTableBody').addEventListener('click', async (event) => {
        const button = event.target.closest('button[data-action]');
        if (!button) return;
        const row = barcodeRows.find((item) => item.id_brc === button.dataset.id);
        if (!row) return;
        const action = button.dataset.action;
        if (action === 'download') {
            openDownloadModal(row);
            return;
        }
        if (action === 'edit') {
            editRecord = row;
            byId('barcodeId').value = row.id_brc;
            byId('barcodeSerial').value = row.barcode_serial;
            byId('barcodeNumber').value = row.number_barcode;
            byId('barcodeHint').value = row.hint || '';
            byId('barcodeBranch').value = String(row.cabang);
            byId('barcodeBranch').disabled = true;
            byId('formTitle').textContent = 'Ubah barcode';
            byId('formSubtitle').textContent = 'Ubah serial, nomor, atau petunjuk. Untuk pindah cabang gunakan tombol Pindah.';
            byId('saveBarcodeButton').innerHTML = '<i class="bi bi-check2-circle me-1"></i> Simpan perubahan';
            byId('cancelEditButton').classList.remove('d-none');
            byId('branchHelp').textContent = `Saat ini ditempatkan di cabang ${row.cabang}.`;
            byId('barcodeSerial').focus();
            return;
        }
        if (action === 'assign') {
            byId('assignBarcodeId').value = row.id_brc;
            byId('assignBarcodeSerial').textContent = `${row.barcode_serial} · ${row.id_brc}`;
            byId('assignBranch').value = String(row.cabang);
            byId('assignNumber').value = row.number_barcode;
            if (assignModal) assignModal.show();
            return;
        }
        if (action === 'delete') {
            let confirmed = false;
            if (window.Swal) {
                const result = await Swal.fire({
                    icon: 'warning', title: 'Hapus barcode?',
                    text: 'Barcode yang sudah dialokasikan ke sesi permainan tidak dapat dihapus.',
                    showCancelButton: true, confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal',
                    confirmButtonColor: '#dc3545', reverseButtons: true
                });
                confirmed = result.isConfirmed;
            } else confirmed = window.confirm('Hapus barcode ini?');
            if (!confirmed) return;
            try {
                await request(`${api}/delete.php`, { method: 'POST', body: JSON.stringify({ id_brc: row.id_brc }) });
                await alertBox('success', 'Terhapus', 'Barcode berhasil dihapus.');
                await loadBarcodes();
                if (editRecord && editRecord.id_brc === row.id_brc) resetForm();
            } catch (error) {
                await alertBox('error', 'Tidak dapat menghapus', error.message);
            }
        }
    });

    byId('assignBarcodeForm').addEventListener('submit', async (event) => {
        event.preventDefault();
        const payload = {
            id_brc: byId('assignBarcodeId').value,
            cabang: Number(byId('assignBranch').value),
            number_barcode: Number(byId('assignNumber').value)
        };
        if (!payload.cabang || !Number.isInteger(payload.number_barcode) || payload.number_barcode < 1) {
            await alertBox('warning', 'Data belum lengkap', 'Pilih cabang dan nomor barcode tujuan.');
            return;
        }
        try {
            await request(`${api}/assign.php`, { method: 'POST', body: JSON.stringify(payload) });
            if (assignModal) assignModal.hide();
            await alertBox('success', 'Berhasil', 'Barcode berhasil dipindahkan ke cabang tujuan.');
            await loadBarcodes();
        } catch (error) {
            await alertBox('error', 'Gagal memindahkan', error.message);
        }
    });

    (async () => {
        try {
            await loadBranches();
            await loadBarcodes();
        } catch (error) {
            await alertBox('error', 'Gagal memuat cabang', error.message);
        }
    })();
})();
