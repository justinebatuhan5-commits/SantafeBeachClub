(() => {
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const drawer = document.getElementById('navigationDrawer');
        mobileMenuBtn.addEventListener('click', () => {
            drawer.showModal();
            mobileMenuBtn.setAttribute('aria-expanded', 'true');
            document.body.classList.add('navigation-open');
        });
        document.getElementById('drawerClose').addEventListener('click', () => drawer.close());
        drawer.addEventListener('click', event => {
            const r = drawer.getBoundingClientRect();
            if (event.target === drawer && (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom)) drawer.close();
        });
        drawer.addEventListener('close', () => {
            mobileMenuBtn.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('navigation-open');
            mobileMenuBtn.focus();
        });
})();
