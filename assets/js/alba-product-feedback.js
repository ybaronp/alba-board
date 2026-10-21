/** Dismiss optional prompts in place, retaining native forms as a fallback. */
function albaFeedbackLocalUrl(value) {
    try {
        var url = new URL(value, window.location.href);
        if (url.hostname !== window.location.hostname || !/^https?:$/.test(url.protocol) || url.username || url.password) return null;
        // WordPress may advertise its configured port while Local uses another one.
        url.protocol = window.location.protocol;
        url.host = window.location.host;
        return url.origin === window.location.origin ? url.href : null;
    } catch (error) { return null; }
}
document.querySelectorAll('.alba-product-feedback-notice form').forEach(function(form) {
    var action = albaFeedbackLocalUrl(form.getAttribute('action'));
    if (action) form.setAttribute('action', action);
});
document.addEventListener('submit', async function(event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    var notice = form.closest('.alba-product-feedback-notice');
    var button = event.submitter;
    if (!notice || !button || !/^(review|survey)_(later|dismiss)$/.test(button.value)) return;
    event.preventDefault();
    var ajaxUrl = albaFeedbackLocalUrl(notice.dataset.ajaxUrl);
    var action = albaFeedbackLocalUrl(form.getAttribute('action'));
    if (!ajaxUrl || !action) return;
    form.setAttribute('action', action);
    if (notice.dataset.pending) return;
    notice.dataset.pending = '1';
    var data = new FormData(form);
    data.set('action', 'alba_board_dismiss_feedback');
    data.set('choice', button.value);
    var buttons = Array.from(notice.querySelectorAll('button'));
    buttons.forEach(function(control) { control.disabled = true; });
    var controller = new AbortController();
    var timer = window.setTimeout(function() { controller.abort(); }, 8000);
    try {
        var response = await fetch(ajaxUrl, {
            method: 'POST', credentials: 'same-origin', redirect: 'error', body: data,
            headers: { Accept: 'application/json' }, signal: controller.signal
        });
        if (!response.ok || (await response.json()).success !== true) throw new Error('Prompt preference was not saved');
        notice.remove();
    } catch (error) {
        // Native submission keeps the original nonce and originating admin page.
        var choice = document.createElement('input');
        choice.type = 'hidden'; choice.name = 'choice'; choice.value = button.value;
        form.appendChild(choice);
        HTMLFormElement.prototype.submit.call(form);
    } finally {
        window.clearTimeout(timer);
        delete notice.dataset.pending;
        buttons.forEach(function(control) { control.disabled = false; });
    }
});
