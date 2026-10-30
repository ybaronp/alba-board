document.addEventListener('DOMContentLoaded', () => {
    const preview = document.getElementById('alba-support-preview');
    if (!preview) return;
    const issue = document.getElementById('alba-support-issue');
    const status = document.getElementById('alba-support-status');
    const versions = preview.value.split('\n').slice(0, 3).join('\n');
    issue.addEventListener('change', () => {
        preview.value = versions + '\nIssue: ' + issue.value;
        status.textContent = '';
    });
    document.getElementById('alba-support-copy').addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(preview.value);
            status.textContent = status.dataset.copied;
        } catch (_) {
            preview.focus();
            preview.select();
            try {
                status.textContent = document.execCommand('copy') ? status.dataset.copied : status.dataset.manual;
            } catch (_) {
                status.textContent = status.dataset.manual;
            }
        }
    });
});
