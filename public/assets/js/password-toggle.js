(() => {
    document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
        const field = document.getElementById(toggle.getAttribute('aria-controls'));

        if (!field) {
            return;
        }

        toggle.addEventListener('click', () => {
            const revealed = field.type === 'text';

            field.type = revealed ? 'password' : 'text';
            toggle.setAttribute('data-revealed', revealed ? 'false' : 'true');
            toggle.setAttribute('aria-label', revealed ? 'Tampilkan kata sandi' : 'Sembunyikan kata sandi');
        });
    });
})();
