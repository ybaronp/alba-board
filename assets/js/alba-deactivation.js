/** Optional feedback before native WordPress deactivation. */
document.addEventListener('DOMContentLoaded', function() {
    if (!window.albaDeactivationData) return;
    var config = window.albaDeactivationData;
    var deactivateLink = document.querySelector('tr[data-plugin="' + config.pluginSlug + '"] .deactivate a');
    var overlay = document.getElementById('alba-feedback-overlay');
    if (!deactivateLink || !overlay) return;
    var modal = document.getElementById('alba-feedback-modal');
    var form = document.getElementById('alba-feedback-form');
    var skipBtn = document.getElementById('alba-skip-deactivate');
    var cancelBtn = document.getElementById('alba-cancel-deactivate');
    var submitBtn = document.getElementById('alba-submit-feedback');
    var detailsBox = document.getElementById('alba-feedback-details');
    var comment = document.getElementById('alba-feedback-comment');
    var prompt = document.getElementById('alba-feedback-prompt');
    var status = document.getElementById('alba-feedback-status');
    var radios = Array.from(form.querySelectorAll('input[name="alba_reason"]'));
    var deactivationUrl = '';
    var busy = false;
    var navigating = false;
    var previousOverflow = '';

    function close() {
        if (busy) return;
        overlay.hidden = true;
        document.body.style.overflow = previousOverflow;
        deactivateLink.focus();
    }
    function deactivate() {
        if (!deactivationUrl || navigating) return;
        navigating = true;
        window.location.assign(deactivationUrl);
    }
    deactivateLink.addEventListener('click', function(event) {
        event.preventDefault();
        if (!overlay.hidden || busy || navigating) return;
        deactivationUrl = deactivateLink.href;
        form.reset();
        comment.hidden = true;
        status.textContent = '';
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        overlay.hidden = false;
        radios[0].focus();
    });
    radios.forEach(function(radio) {
        radio.addEventListener('change', function() {
            comment.hidden = false;
            prompt.textContent = radio.dataset.prompt;
            detailsBox.placeholder = radio.dataset.prompt;
            status.textContent = '';
            if (radio.value === 'Other') detailsBox.focus();
        });
    });
    cancelBtn.addEventListener('click', close);
    overlay.addEventListener('click', function(event) {
        if (event.target === overlay) close();
    });
    document.addEventListener('keydown', function(event) {
        if (overlay.hidden) return;
        if (event.key === 'Escape') { event.preventDefault(); close(); }
        if (event.key !== 'Tab') return;
        // Keep focus in the dialog; only the checked radio is a Tab stop.
        var selected = form.querySelector('input[name="alba_reason"]:checked');
        var stops = Array.from(modal.querySelectorAll('a[href],button,input,textarea'))
            .filter(function(element) {
                return !element.disabled && element.getClientRects().length &&
                    (element.type !== 'radio' || element === (selected || radios[0]));
            });
        if (!stops.length) { event.preventDefault(); modal.focus(); return; }
        var first = stops[0], last = stops[stops.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    skipBtn.addEventListener('click', deactivate);
    form.addEventListener('submit', async function(event) {
        event.preventDefault();
        if (busy || navigating || overlay.hidden) return;
        var selected = form.querySelector('input[name="alba_reason"]:checked');
        if (!selected) {
            status.textContent = config.reasonRequired;
            radios[0].focus();
            return;
        }
        busy = true;
        modal.setAttribute('aria-busy', 'true');
        submitBtn.textContent = config.sendingText;
        [submitBtn, skipBtn, cancelBtn, detailsBox].concat(radios).forEach(function(control) { control.disabled = true; });
        var controller = new AbortController();
        var timeout;
        try {
            await Promise.race([
                fetch(config.feedbackUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    redirect: 'error',
                    body: JSON.stringify({reason: selected.value, details: detailsBox.value.trim()}),
                    signal: controller.signal
                }).then(function(response) {
                    if (!response.ok) throw new Error('Feedback request failed');
                    return response.json();
                }).then(function(result) {
                    if (!result || result.success !== true) throw new Error('Feedback was not acknowledged');
                }),
                new Promise(function(resolve, reject) {
                    timeout = window.setTimeout(function() {
                        controller.abort();
                        reject(new Error('Feedback request timed out'));
                    }, 5000);
                })
            ]);
        } catch (error) {
            // Feedback is optional: service failure must not prevent deactivation.
        } finally {
            window.clearTimeout(timeout);
            deactivate();
        }
    });
});
