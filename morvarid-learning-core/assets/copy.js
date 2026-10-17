/* Explicit click-only clipboard actions; no tokens stored or logged. */
document.addEventListener('click', async function (event) {
    const button = event.target.closest('[data-md-copy]');
    if (!button) return;
    const row = button.closest('.md-learning-copy-row');
    const field = row && row.querySelector('input[readonly]');
    if (!field) return;
    const original = button.textContent;
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(field.value);
        } else {
            field.focus();
            field.select();
            if (!document.execCommand('copy')) throw new Error('Copy unavailable');
        }
        button.textContent = 'کپی شد ✓';
    } catch (error) {
        field.focus();
        field.select();
        button.textContent = 'متن را انتخاب کنید';
    }
    button.setAttribute('aria-live', 'polite');
    window.setTimeout(() => { button.textContent = original; }, 2200);
});
