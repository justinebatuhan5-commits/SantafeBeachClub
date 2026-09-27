(() => {
    const frame = document.getElementById('resort-map');
    const buttons = document.querySelectorAll('[data-map-type]');
    if (!frame || buttons.length === 0) return;

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
})();
