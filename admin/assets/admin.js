(() => {
    'use strict';

    const sidebar = document.getElementById('adminSidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    const trigger = document.getElementById('mobileMenuButton');

    const setSidebar = (open) => {
        if (!sidebar || !backdrop || !trigger) return;
        sidebar.classList.toggle('show', open);
        backdrop.classList.toggle('show', open);
        document.body.classList.toggle('sidebar-open', open);
        sidebar.setAttribute('aria-hidden', open ? 'false' : 'true');
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        trigger.setAttribute('aria-label', open ? 'Tutup menu navigasi' : 'Buka menu navigasi');
        const icon = trigger.querySelector('i');
        if (icon) icon.className = open ? 'bi bi-x-lg' : 'bi bi-list';
    };

    trigger?.addEventListener('click', () => {
        setSidebar(!sidebar?.classList.contains('show'));
    });
    backdrop?.addEventListener('click', () => setSidebar(false));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') setSidebar(false);
    });
    sidebar?.querySelectorAll('a.sidebar-link:not(.disabled)').forEach((link) => {
        link.addEventListener('click', () => setSidebar(false));
    });

    // Always start closed on both desktop and mobile.
    setSidebar(false);

    // Isi dan tampilkan modal edit peserta dari tombol pada tabel.
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-user-edit]');
        if (!button) return;

        const modalElement = document.getElementById('editUserModal');
        if (!modalElement || !window.bootstrap) return;

        const id = button.dataset.id || '';
        const name = button.dataset.name || '';
        const phone = button.dataset.phone || '';
        const idInput = document.getElementById('editUserId');
        const nameInput = document.getElementById('editUserName');
        const phoneInput = document.getElementById('editUserPhone');
        const idLabel = document.getElementById('editUserIdLabel');

        if (!idInput || !nameInput || !phoneInput) return;
        idInput.value = id;
        nameInput.value = name;
        phoneInput.value = phone;
        if (idLabel) idLabel.textContent = id;

        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    });

    const swalReady = () => typeof window.Swal !== 'undefined';
    const flash = window.BH_FLASH;
    if (flash && swalReady()) {
        const titles = { success: 'Berhasil', error: 'Terjadi kesalahan', warning: 'Perhatian', info: 'Informasi' };
        Swal.fire({
            icon: flash.type,
            title: titles[flash.type] || 'Informasi',
            text: flash.message,
            confirmButtonText: 'Mengerti',
            confirmButtonColor: '#315efb'
        });
    }

    document.addEventListener('submit', async (event) => {
        const form = event.target.closest('form[data-confirm]');
        if (!form) return;
        event.preventDefault();
        const message = form.dataset.confirm || 'Lanjutkan tindakan ini?';
        if (!swalReady()) {
            if (window.confirm(message)) form.submit();
            return;
        }
        const result = await Swal.fire({
            icon: 'warning',
            title: 'Konfirmasi tindakan',
            text: message,
            showCancelButton: true,
            confirmButtonText: 'Ya, lanjutkan',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            reverseButtons: true
        });
        if (result.isConfirmed) form.submit();
    });

    document.addEventListener('click', async (event) => {
        const link = event.target.closest('a[data-confirm]');
        if (!link) return;
        event.preventDefault();
        const message = link.dataset.confirm || 'Lanjutkan tindakan ini?';
        if (!swalReady()) {
            if (window.confirm(message)) window.location.href = link.href;
            return;
        }
        const result = await Swal.fire({
            icon: 'warning',
            title: 'Konfirmasi tindakan',
            text: message,
            showCancelButton: true,
            confirmButtonText: 'Ya, lanjutkan',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#dc3545',
            reverseButtons: true
        });
        if (result.isConfirmed) window.location.href = link.href;
    });
})();
