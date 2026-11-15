(function () {
    const subjectInput = document.getElementById('subject');
    const topicBtns = Array.from(document.querySelectorAll('.contact-topic-btn[data-topic]'));
    if (!subjectInput || topicBtns.length === 0) return;

    function syncActiveTopic() {
        const current = subjectInput.value.trim();
        topicBtns.forEach(function (btn) {
            const topic = btn.getAttribute('data-topic') || '';
            btn.setAttribute('aria-pressed', current === topic ? 'true' : 'false');
        });
    }

    topicBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            subjectInput.value = btn.getAttribute('data-topic') || '';
            syncActiveTopic();
            subjectInput.focus();
        });
    });

    subjectInput.addEventListener('input', syncActiveTopic);
    syncActiveTopic();
})();
