/** Fit card text to its content while preserving the original empty field height. */
document.addEventListener('DOMContentLoaded', function() {
    const selector = 'textarea[name="card_content"], textarea#alba-new-comment, textarea#alba-new-comment-text';
    const originalHeights = new WeakMap();
    document.querySelectorAll('#alba-modal-body, #alba-modal-body-admin').forEach(function(body) {
        let pending = false;
        let width = 0;
        function fit(textarea) {
            if (!textarea.getClientRects().length || !textarea.clientWidth) return;
            if (!originalHeights.has(textarea)) originalHeights.set(textarea, textarea.style.height);
            textarea.style.height = originalHeights.get(textarea);
            if (!textarea.value) return;
            const style = getComputedStyle(textarea);
            const padding = parseFloat(style.paddingTop) + parseFloat(style.paddingBottom);
            const borders = parseFloat(style.borderTopWidth) + parseFloat(style.borderBottomWidth);
            const height = Math.max(textarea.getBoundingClientRect().height, textarea.scrollHeight + borders);
            textarea.style.height = (style.boxSizing === 'border-box' ? height : height - padding - borders) + 'px';
        }
        function schedule() {
            if (pending) return;
            pending = true;
            requestAnimationFrame(function() {
                pending = false;
                body.querySelectorAll(selector).forEach(fit);
            });
        }
        body.addEventListener('input', function(event) {
            if (event.target instanceof HTMLTextAreaElement && event.target.matches(selector)) fit(event.target);
        });
        // Observe only modal content: also catches a submitted comment's programmatic clear.
        new MutationObserver(schedule).observe(body, {childList: true, subtree: true});
        if (typeof ResizeObserver !== 'undefined') {
            new ResizeObserver(function(entries) {
                const next = entries[0].contentRect.width;
                if (next === width) return;
                width = next;
                schedule();
            }).observe(body);
        } else {
            window.addEventListener('resize', schedule);
        }
        schedule();
    });
});
