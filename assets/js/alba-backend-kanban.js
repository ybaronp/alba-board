// assets/js/alba-backend-kanban.js

document.addEventListener('DOMContentLoaded', () => {
    // --- Drag and drop (Sortable.js) ---
    document.querySelectorAll('.alba-list').forEach(list => {
        if (typeof Sortable !== "undefined") {
            new Sortable(list, {
                group: 'alba-cards',
                animation: 150,
                ghostClass: 'sortable-ghost',
                dragClass: 'sortable-drag',
                onEnd: function (evt) {
                    const card = evt.item;
                    const cardId = card.dataset.cardId;
                    const newListId = evt.to.dataset.listId;
                    const orderedCardIds = Array.from(evt.to.querySelectorAll('[data-card-id]'))
                        .map(el => el.dataset.cardId);

                    if (cardId && newListId) {
                        const params = new URLSearchParams();
                        params.append('action', 'alba_move_card');
                        params.append('card_id', cardId);
                        params.append('new_list_id', newListId);
                        params.append('nonce', albaBoard.nonce);

                        orderedCardIds.forEach((id, index) => {
                            params.append(`order[${index}]`, id);
                        });

                        fetch(albaBoard.ajaxurl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: params
                        })
                        .then(res => res.json())
                        .then(response => {
                            if (!response.success && response.data && response.data.message) {
                                showBackendMessage(response.data.message, 'error');
                            }
                        })
                        .catch(() => {
                            showBackendMessage('Connection error.', 'error');
                        });
                    }
                }
            });
        }
    });

    // --- Modal logic (card details) ---
    document.querySelectorAll('.alba-card').forEach(card => {
        card.addEventListener('click', function(e) {
            if (e.target.closest('.alba-card-action-btn')) return;
            const cardId = card.dataset.cardId;
            const modal = document.getElementById('alba-card-modal-admin');
            const modalBody = document.getElementById('alba-modal-body-admin');
            modal.style.display = 'flex';
            modal.classList.add('active');
            modalBody.innerHTML = albaBoard.loading || 'Loading...';

            // POST for security and data
            const formData = new FormData();
            formData.append('action', 'alba_get_card_details_admin');
            formData.append('card_id', cardId);
            formData.append('nonce', albaBoard.get_card_details_nonce);

            fetch(albaBoard.ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(res => res.text())
            .then(html => {
                modalBody.innerHTML = html;
                bindSaveButtonHandler();
            });
        });
    });

    // --- Close modal handlers ---
    document.getElementById('alba-modal-close-admin').addEventListener('click', () => {
        document.getElementById('alba-card-modal-admin').style.display = 'none';
    });
    window.addEventListener('click', (e) => {
        if (e.target.id === 'alba-card-modal-admin') {
            document.getElementById('alba-card-modal-admin').style.display = 'none';
        }
    });

    // --- Save button handler (save modal fields, including comments) ---
    function bindSaveButtonHandler() {
        const saveBtn = document.getElementById('alba-card-save-btn');
        if (!saveBtn) return;

        saveBtn.onclick = function (e) {
            e.preventDefault();
            const form = document.getElementById('alba-card-details-form');
            if (!form) return;

            const formData = new FormData(form);
            formData.append('action', 'alba_save_card_details_admin');
            const saveNonce = form.querySelector('input[name="save_card_details_nonce"]');
            formData.append('nonce', saveNonce ? saveNonce.value : albaBoard.save_card_details_nonce);

            // Always send new_comment, even if empty
            const newCommentField = document.getElementById('alba-new-comment');
            if (newCommentField) {
                formData.set('new_comment', newCommentField.value);
            }

            fetch(albaBoard.ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(response => {
                let msg = '';
                let type = 'success';

                if (response && response.success) {
                    document.getElementById('alba-modal-body-admin').innerHTML = response.data.html || '';
                    bindSaveButtonHandler();

                    msg = (response.data && response.data.message) ? response.data.message : 'Saved!';
                    if (msg && msg !== 'undefined') showBackendMessage(msg, type);
                } else {
                    type = 'error';
                    if (response && response.data && response.data.message) {
                        msg = response.data.message;
                    } else if (response && response.message) {
                        msg = response.message;
                    } else {
                        msg = 'Error saving.';
                    }
                    if (msg && msg !== 'undefined') showBackendMessage(msg, type);
                }
            })
            .catch(() => {
                showBackendMessage('Connection error.', 'error');
            });
        }
    }

    bindSaveButtonHandler();

    // Helper to show message under the comment box in the admin modal
    function showBackendMessage(msg, type) {
        let oldMsg = document.getElementById('alba-admin-feedback');
        if (oldMsg) oldMsg.remove();

        let refNode = document.getElementById('alba-new-comment');
        if (!refNode) refNode = document.getElementById('alba-card-details-form');
        if (!refNode) return;

        let messageEl = document.createElement('div');
        messageEl.id = 'alba-admin-feedback';
        messageEl.textContent = msg;
        messageEl.style.margin = '10px 0 0 0';
        messageEl.style.fontWeight = '600';
        messageEl.style.fontSize = '1em';
        messageEl.style.transition = 'opacity 0.2s';
        messageEl.style.opacity = 1;
        messageEl.style.color = (type === 'success') ? '#2a9d4b' : '#e05c4d';
        refNode.parentNode.insertBefore(messageEl, refNode.nextSibling);

        setTimeout(() => {
            if (messageEl) messageEl.style.opacity = 0.15;
        }, 3000);
        setTimeout(() => {
            if (messageEl && messageEl.parentNode) messageEl.remove();
        }, 5000);
    }

    // --- Select2 dynamic init for modal content ---
    jQuery(document).on('DOMNodeInserted', function(e) {
        jQuery(e.target).find('.alba-select2').each(function() {
            if (!jQuery(this).hasClass('select2-hidden-accessible')) {
                jQuery(this).select2({
                    width: '100%',
                    dropdownParent: jQuery('#alba-card-modal-admin .alba-modal-content')
                });
            }
        });
    });

    // Helper: You can add more AJAX handlers here for dynamic board reloads, etc.

    // --- Example: Helper to rebind modal and drag after AJAX reload (optional) ---
    function rebindAdminBoardEvents() {
        // Add drag & drop and modal re-initialization if you reload the board via AJAX
    }
});