(function () {
    const dropZone = document.getElementById('dropZone');
    const input = document.getElementById('photoInput');
    const grid = document.getElementById('previewGrid');
    const badge = document.getElementById('fileCountBadge');
    const uploadBtn = document.getElementById('uploadBtn');
    const addModal = document.getElementById('addModal');
    const browseBtn = document.querySelector('[data-browse-gallery]');
    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    const maxBytes = 2 * 1024 * 1024;

    let selectedFiles = [];

    function syncInputFiles() {
        const dt = new DataTransfer();
        selectedFiles.forEach((f) => dt.items.add(f));
        input.files = dt.files;
    }

    function renderPreviews() {
        grid.innerHTML = '';
        if (selectedFiles.length === 0) {
            grid.style.display = 'none';
            badge.style.display = 'none';
            uploadBtn.disabled = true;
            return;
        }
        grid.style.display = 'grid';
        badge.style.display = 'block';
        badge.textContent = selectedFiles.length + ' file(s) ready to upload';
        uploadBtn.disabled = false;

        selectedFiles.forEach((file, idx) => {
            const wrap = document.createElement('div');
            wrap.className = 'thumb-wrap';

            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.onload = () => URL.revokeObjectURL(img.src);

            const btn = document.createElement('button');
            btn.className = 'thumb-remove';
            btn.type = 'button';
            btn.textContent = '×';
            btn.title = 'Remove ' + file.name;
            btn.addEventListener('click', () => {
                selectedFiles.splice(idx, 1);
                syncInputFiles();
                renderPreviews();
            });

            wrap.appendChild(img);
            wrap.appendChild(btn);
            grid.appendChild(wrap);
        });
    }

    function addFiles(fileList) {
        Array.from(fileList).forEach((f) => {
            if (!allowedTypes.includes(f.type)) {
                SiteDialog.alert(f.name + ' is not a supported format.');
                return;
            }
            if (f.size > maxBytes) {
                SiteDialog.alert(f.name + ' exceeds the 2 MB limit.');
                return;
            }
            selectedFiles.push(f);
        });
        syncInputFiles();
        renderPreviews();
    }

    function closeGalleryModal() {
        if (addModal) {
            addModal.classList.remove('open');
        }
        selectedFiles = [];
        if (grid && badge && uploadBtn) {
            renderPreviews();
        }
    }

    window.closeGalleryModal = closeGalleryModal;

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-open-gallery-modal]')) {
            if (addModal) {
                addModal.classList.add('open');
            }
            return;
        }
        if (event.target.closest('[data-close-gallery-modal]')) {
            closeGalleryModal();
            return;
        }
        if (event.target.closest('[data-browse-gallery]') && input) {
            input.click();
        }
    });

    document.querySelectorAll('form.js-confirm-only').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
        });
    });

    if (dropZone && input) {
        dropZone.addEventListener('click', (e) => {
            if (e.target !== browseBtn) {
                input.click();
            }
        });
        input.addEventListener('change', () => addFiles(input.files));

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('drag-over');
        });
        dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
            addFiles(e.dataTransfer.files);
        });
    }
})();
