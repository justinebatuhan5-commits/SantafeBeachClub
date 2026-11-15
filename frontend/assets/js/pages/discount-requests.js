(function () {
    const config = window.SFBC_DISCOUNT_REQUESTS_CONFIG || {};
    const extraAuditCount = Math.max(0, Number(config.extraAuditCount || 0));

    document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('copyLink')?.addEventListener('click', async function () {
            const field = document.getElementById('privateLink');
            if (!field) return;
            try {
                await navigator.clipboard.writeText(field.value);
                document.getElementById('copyResult').textContent = 'Link copied.';
            } catch (e) {
                field.select();
                document.getElementById('copyResult').textContent = 'Select and copy the link above.';
            }
        });

        const modal = document.getElementById('imageModal');
        const modalTitle = document.getElementById('imageModalTitle');
        const modalImage = document.getElementById('imageModalImage');
        const modalPdf = document.getElementById('imageModalPdf');
        const modalLoading = document.getElementById('imageModalLoading');
        const modalError = document.getElementById('imageModalError');
        const modalErrorDetail = document.getElementById('imageModalErrorDetail');
        const closeBtn = document.getElementById('closeImageModal');
        let documentObjectUrl = null;
        let documentRequest = null;

        const auditToggle = document.getElementById('auditToggle');
        auditToggle?.addEventListener('click', function () {
            const expanded = auditToggle.getAttribute('aria-expanded') === 'true';
            document.querySelectorAll('.audit-entry-extra').forEach(entry => {
                entry.hidden = expanded;
            });
            auditToggle.setAttribute('aria-expanded', String(!expanded));
            auditToggle.innerHTML = expanded
                ? `Show ${extraAuditCount} more events <span aria-hidden="true">&#8595;</span>`
                : 'Show less <span aria-hidden="true">&#8593;</span>';
        });

        document.querySelectorAll('.js-preview-doc').forEach(btn => {
            btn.addEventListener('click', async function () {
                const url = btn.getAttribute('data-url');
                const title = btn.getAttribute('data-title') || 'Document Preview';
                modalTitle.textContent = title;
                modalImage.hidden = true;
                modalPdf.hidden = true;
                modalError.hidden = true;
                modalLoading.hidden = false;
                modal.classList.add('active');
                modal.setAttribute('aria-hidden', 'false');

                documentRequest?.abort();
                documentRequest = new AbortController();
                const activeRequest = documentRequest;
                if (documentObjectUrl) URL.revokeObjectURL(documentObjectUrl);
                documentObjectUrl = null;

                try {
                    const response = await fetch(url, {
                        credentials: 'same-origin',
                        cache: 'no-store',
                        signal: activeRequest.signal
                    });
                    if (!response.ok) {
                        const serverMessage = (await response.text()).trim().slice(0, 160);
                        throw new Error(`Server returned HTTP ${response.status}${serverMessage ? `: ${serverMessage}` : ''}`);
                    }
                    const documentBlob = await response.blob();
                    if (!documentBlob.size) throw new Error('The server returned an empty document');
                    if (!documentBlob.type.startsWith('image/') && documentBlob.type !== 'application/pdf') {
                        throw new Error(`The server returned an unsupported document type (${documentBlob.type || 'unknown'})`);
                    }
                    if (documentBlob.type.startsWith('image/')) {
                        const imageDataUrl = await new Promise((resolve, reject) => {
                            const reader = new FileReader();
                            reader.onload = () => resolve(reader.result);
                            reader.onerror = () => reject(new Error('The downloaded image could not be read'));
                            reader.readAsDataURL(documentBlob);
                        });
                        await new Promise((resolve, reject) => {
                            modalImage.onload = resolve;
                            modalImage.onerror = () => reject(new Error('The image data is invalid'));
                            modalImage.src = imageDataUrl;
                        });
                        modalImage.hidden = false;
                    } else {
                        documentObjectUrl = URL.createObjectURL(documentBlob);
                        modalPdf.src = documentObjectUrl;
                        modalPdf.hidden = false;
                    }
                    modalLoading.hidden = true;
                } catch (error) {
                    if (error.name === 'AbortError' || documentRequest !== activeRequest) return;
                    modalLoading.hidden = true;
                    modalErrorDetail.textContent = error instanceof Error ? error.message : 'The document could not be loaded.';
                    modalError.hidden = false;
                }
            });
        });

        function closeModal() {
            if (!modal) return;
            documentRequest?.abort();
            documentRequest = null;
            modal.classList.remove('active');
            modal.setAttribute('aria-hidden', 'true');
            modalImage.removeAttribute('src');
            modalImage.hidden = true;
            modalPdf.src = 'about:blank';
            modalPdf.hidden = true;
            modalError.hidden = true;
            modalLoading.hidden = false;
            if (documentObjectUrl) URL.revokeObjectURL(documentObjectUrl);
            documentObjectUrl = null;
        }

        closeBtn?.addEventListener('click', closeModal);
        modal?.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal?.classList.contains('active')) closeModal();
        });
    });
})();
