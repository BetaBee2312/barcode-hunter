(() => {
    'use strict';
    const form = document.getElementById('registerForm');
    if (!form) return;

    const button = document.getElementById('startButton');
    const message = document.getElementById('formMessage');
    const config = window.BH_GAME_PAGE || { baseUrl: '', csrf: '' };

    function showMessage(text, type = 'error') {
        message.textContent = text;
        message.className = `notice notice-${type}`;
        message.hidden = false;
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        message.hidden = true;
        button.disabled = true;
        button.textContent = 'Memeriksa data...';

        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());

        try {
            const response = await fetch(`${config.baseUrl}/session.php?action=register`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': config.csrf
                },
                body: JSON.stringify(payload),
                credentials: 'same-origin'
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Tidak dapat memulai permainan.');
            }
            window.location.href = `${config.baseUrl}/game.php`;
        } catch (error) {
            showMessage(error.message || 'Koneksi bermasalah. Silakan coba lagi.');
            button.disabled = false;
            button.textContent = 'Mulai Permainan';
        }
    });
})();
