document.addEventListener('DOMContentLoaded', () => {
    const guide = document.getElementById('alba-role-guide');
    if (!guide || typeof guide.showModal !== 'function') return;
    let dismissed = false;
    const dismiss = () => {
        if (dismissed) return;
        dismissed = true;
        const data = new URLSearchParams({action: 'alba_dismiss_role_guide', nonce: guide.dataset.nonce});
        fetch(guide.dataset.url, {method: 'POST', body: data, credentials: 'same-origin', keepalive: true}).catch(() => {});
    };
    guide.querySelectorAll('button, a').forEach(button => button.addEventListener('click', () => guide.close()));
    guide.addEventListener('close', dismiss);
    guide.showModal();
});
