function updateCharCount() {
    const textarea = document.getElementById('reason');
    const charCount = document.getElementById('charCount');
    if (!textarea || !charCount) return;

    const count = textarea.value.length;
    const maxCount = 500;
    charCount.textContent = String(Math.min(count, maxCount));

    if (count > maxCount) {
        textarea.value = textarea.value.substring(0, maxCount);
        charCount.textContent = String(maxCount);
    }
}

window.updateCharCount = updateCharCount;

document.addEventListener('DOMContentLoaded', () => {
    const textarea = document.getElementById('reason');
    if (textarea) {
        textarea.addEventListener('input', updateCharCount);
        textarea.addEventListener('keyup', updateCharCount);
        updateCharCount();
    }
});
