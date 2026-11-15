(() => {
    const frame = document.getElementById('resort-map');
    const buttons = document.querySelectorAll('[data-map-type]');
    if (frame && buttons.length > 0) {
        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                if (button.getAttribute('aria-pressed') === 'true') return;

                const url = new URL(frame.src);
                const embedParameters = url.searchParams.get('pb') || '';
                const isSatellite = button.dataset.mapType === 'k';
                url.searchParams.set('pb', embedParameters.replace(/!5e[01]!/, isSatellite ? '!5e1!' : '!5e0!'));
                frame.src = url.toString();
                frame.title = `Santa Fe Beach Club ${isSatellite ? 'satellite' : 'road'} map`;
                buttons.forEach((item) => item.setAttribute('aria-pressed', String(item === button)));
            });
        });
    }

    const copyBtn = document.getElementById('copyLocationBtn');
    const copyStatus = document.getElementById('copyLocationStatus');
    let resetTimer = null;

    if (copyBtn && copyStatus) {
        copyBtn.addEventListener('click', async () => {
            const text = copyBtn.getAttribute('data-copy-text') || '';
            if (!text) return;

            let copied = false;
            if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                try {
                    await navigator.clipboard.writeText(text);
                    copied = true;
                } catch (_) {
                    copied = false;
                }
            }

            if (!copied) {
                const helper = document.createElement('textarea');
                helper.value = text;
                helper.setAttribute('readonly', '');
                helper.style.position = 'fixed';
                helper.style.left = '-9999px';
                document.body.appendChild(helper);
                helper.select();
                try {
                    copied = document.execCommand('copy');
                } catch (_) {
                    copied = false;
                }
                document.body.removeChild(helper);
            }

            copyBtn.textContent = copied ? 'Copied' : 'Copy';
            copyStatus.textContent = copied
                ? 'Address and coordinates copied to clipboard.'
                : 'Copy unavailable. Use Pantalan, Santa Fe, 6047 Cebu.';

            clearTimeout(resetTimer);
            resetTimer = window.setTimeout(() => {
                copyBtn.textContent = 'Copy';
                copyStatus.textContent = '';
            }, 2400);
        });
    }
})();
