/** In-page protection for card text. Drafts never leave the page or enter browser storage. */
document.addEventListener('DOMContentLoaded', () => {
    'use strict';
    const config = window.albaUnsavedDrafts;
    if (!config) return;
    const states = new WeakMap(), lastTextFocus = new WeakMap();
    const roots = [...document.querySelectorAll('#alba-card-modal-admin,#alba-card-modal')];
    const selector = '[name=card_title],textarea[name=card_content],#alba-new-comment,#alba-new-comment-text';
    let bypass = false, previousFocus, pendingAction, activeRoot;
    const values = root => [...root.querySelectorAll(selector)].map(field => [field.name || field.id, field.value]);
    function state(root) {
        const node = root.querySelector('#alba-card-details-form,.alba-modal-inner');
        if (!node) return null;
        let current = states.get(root);
        if (!current || current.node !== node) {
            current = {node, baseline: JSON.stringify(values(root)), busy: false};
            states.set(root, current);
        }
        return current;
    }
    const visible = root => root.getClientRects().length > 0;
    const dirty = root => {const current = state(root);return current && JSON.stringify(values(root)) !== current.baseline;};
    const dialog = document.createElement('dialog');
    dialog.className = 'alba-discard-dialog'; dialog.setAttribute('aria-labelledby','alba-discard-title');
    const title = document.createElement('h2');title.id = 'alba-discard-title';title.textContent = config.title;
    const text = document.createElement('p');text.id = 'alba-discard-message';text.textContent = config.message;dialog.setAttribute('aria-describedby',text.id);
    const keep = document.createElement('button');keep.type = 'button';keep.className = 'alba-keep-draft';keep.textContent = config.keep;
    const discard = document.createElement('button');discard.type = 'button';discard.className = 'alba-discard-draft';discard.textContent = config.discard;
    dialog.append(title,text,keep,discard);document.body.appendChild(dialog);
    const closeDialog = () => {dialog.close();pendingAction = null;previousFocus?.focus();};
    keep.addEventListener('click',closeDialog);
    dialog.addEventListener('cancel',event => {event.preventDefault();closeDialog();});
    discard.addEventListener('click',() => {
        const action = pendingAction;
        const root = activeRoot;
        dialog.close();pendingAction = null;
        if (root) {const current = state(root);if (current) current.baseline = JSON.stringify(values(root));}
        bypass = true;
        try {action?.();} finally {bypass = false;}
    });
    function guard(event,root,action) {
        const current = state(root);
        const busy = current?.busy || [...root.querySelectorAll('.alba-rich-controls')].some(el => el.dataset.busy === '1');
        if (!busy && !dirty(root)) return;
        event.preventDefault();event.stopImmediatePropagation();
        if (busy) {
            let status = root.querySelector('.alba-draft-status');
            if (!status) {status = document.createElement('p');status.className = 'alba-draft-status';status.setAttribute('role','status');root.querySelector('#alba-modal-body-admin,.alba-modal-inner')?.appendChild(status);}
            status.textContent = config.wait;
            return;
        }
        if (dialog.open) return;
        activeRoot = root;pendingAction = action;previousFocus = lastTextFocus.get(root) || document.activeElement;
        dialog.showModal();keep.focus();
    }
    roots.forEach(root => new MutationObserver(() => {if (visible(root)) state(root);}).observe(root,{childList:true,subtree:true}));
    window.addEventListener('click',event => {
        if (bypass || dialog.contains(event.target)) return;
        const root = roots.find(visible);if (!root) return;
        const close = event.target.closest('#alba-modal-close-admin,#alba-modal-close');
        const card = event.target.closest('.alba-card');
        if (close || event.target === root || card) {
            const target = close || card || root;
            guard(event,root,()=>target.click());
        }
    },true);
    window.addEventListener('keydown',event => {
        if (bypass) return;
        if (dialog.open) {
            if (event.key === 'Escape') {event.preventDefault();event.stopImmediatePropagation();closeDialog();}
            else if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {event.preventDefault();event.stopImmediatePropagation();}
            return;
        }
        if (event.key !== 'Escape') return;
        const root = roots.find(visible);if (!root) return;
        const close = root.querySelector('#alba-modal-close-admin,#alba-modal-close');
        guard(event,root,()=>close?.click());
    },true);
    window.addEventListener('focusin',event => {
        if (!event.target.matches(selector)) return;
        const root = roots.find(el => el.contains(event.target));if (root) lastTextFocus.set(root,event.target);
    });
    window.addEventListener('beforeunload',event => {
        if (roots.some(root => visible(root) && (dirty(root) || state(root)?.busy))) {event.preventDefault();event.returnValue = '';}
    });
    // Called by existing save handlers so a delayed response cannot overwrite a newer draft.
    window.albaDraftGuard = {
        begin(root) {
            const current = state(root);if (!current) return;
            current.busy = true;
            root.querySelectorAll(selector).forEach(field => {field.dataset.albaReadOnly = String(field.readOnly);field.readOnly = true;});
        },
        end(root,success) {
            const current = state(root);if (!current) return;
            current.busy = false;
            root.querySelectorAll(selector).forEach(field => {field.readOnly = field.dataset.albaReadOnly === 'true';delete field.dataset.albaReadOnly;});
            if (success) current.baseline = JSON.stringify(values(root));
            root.querySelector('.alba-draft-status')?.remove();
        }
    };
});
