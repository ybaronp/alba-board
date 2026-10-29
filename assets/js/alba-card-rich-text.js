document.addEventListener('DOMContentLoaded', () => {
    'use strict';
    const config = window.albaRichText;
    if (!config) return;
    async function request(action, control, extra = {}) {
        const body = new FormData();
        body.set('action', action); body.set('nonce', config.nonce); body.set('card_id', control.dataset.cardId);
        Object.entries(extra).forEach(([key, value]) => body.set(key, value));
        const response = await (window.albaBoardRequest?.fetch || window.fetch)(config.url, {method: 'POST', credentials: 'same-origin', body});
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.data?.message || config.error);
        return result.data;
    }
    function textarea(control) {
        return control.closest('form, .alba-add-comment-section')?.querySelector(control.dataset.selector);
    }
    async function run(control, work) {
        if (control.dataset.busy === '1') return;
        control.dataset.busy = '1';
        const status = control.querySelector('.alba-rich-feedback');
        const field = textarea(control);
        const buttons = [...control.querySelectorAll('button')];
        const scope = control.closest('form, .alba-add-comment-section');
        const pending = Number(scope.dataset.richPending || 0);
        const submitButtons = [...scope.querySelectorAll('[type=submit], #alba-add-comment-btn')];
        if (!pending) submitButtons.forEach(button => { button.dataset.richWasDisabled = String(button.disabled); button.disabled = true; });
        scope.dataset.richPending = String(pending + 1);
        // Keep the insertion range stable while the upload is in progress.
        if (field) field.readOnly = true;
        buttons.forEach(button => button.disabled = true);
        status.textContent = config.working;
        try { await work(status, field); }
        catch (error) { status.textContent = error.message || config.error; }
        finally {
            buttons.forEach(button => button.disabled = false);
            if (field) field.readOnly = false;
            control.dataset.busy = '0';
            scope.dataset.richPending = String(Number(scope.dataset.richPending) - 1);
            if (scope.dataset.richPending === '0') submitButtons.forEach(button => { button.disabled = button.dataset.richWasDisabled === 'true'; delete button.dataset.richWasDisabled; });
        }
    }
    document.addEventListener('click', event => {
        const button = event.target.closest('.alba-rich-controls button');
        if (!button) return;
        const control = button.closest('.alba-rich-controls');
        event.preventDefault();
        if (button.classList.contains('alba-rich-preview')) run(control, async (status, field) => {
            if (!field) return;
            const current = control.querySelector('.alba-rich-result');
            if (!current.hidden) {
                current.hidden = true; field.hidden = false; button.textContent = config.preview; button.setAttribute('aria-pressed', 'false');
                status.textContent = ''; field.focus(); return;
            }
            const result = await request('alba_preview_card_markdown', control, {text: field.value});
            const preview = control.querySelector('.alba-rich-result');
            // HTML is produced only by the server's safe Markdown renderer and strict allowlist.
            preview.innerHTML = result.html; preview.hidden = false; field.hidden = true; button.textContent = config.edit; button.setAttribute('aria-pressed', 'true'); status.textContent = '';
        });
    });
    function uploadImage(control, file) {
        return run(control, async (status, field) => {
            if (!field) return;
            field.hidden = false;
            control.querySelector('.alba-rich-result').hidden = true;
            control.querySelector('.alba-rich-preview').textContent = config.preview;
            control.querySelector('.alba-rich-preview').setAttribute('aria-pressed', 'false');
            const start = field.selectionStart, end = field.selectionEnd;
            const result = await request('alba_upload_card_image', control, {image: file, target: control.dataset.target});
            field.setRangeText('\n' + result.markdown + '\n', start, end, 'end');
            field.dispatchEvent(new Event('input', {bubbles: true}));
            field.focus(); status.textContent = config.inserted;
        });
    }
    // Ctrl+V / Cmd+V and native Paste use the card-scoped upload endpoint directly.
    document.addEventListener('paste', event => {
        if (!event.target.matches('textarea')) return;
        const scope = event.target.closest('form, .alba-add-comment-section');
        const control = scope && [...scope.querySelectorAll('.alba-rich-controls')].find(el => textarea(el) === event.target);
        const file = [...(event.clipboardData?.items || [])].find(item => item.kind === 'file' && item.type.startsWith('image/'))?.getAsFile()
            || [...(event.clipboardData?.files || [])].find(item => item.type.startsWith('image/'));
        if (!control || !file) return;
        event.preventDefault();
        if (control.dataset.busy !== '1') uploadImage(control, file);
    });
});
