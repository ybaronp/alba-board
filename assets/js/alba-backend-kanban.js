// assets/js/alba-backend-kanban.js

document.addEventListener('DOMContentLoaded', () => {
    if (window.albaAdminKanbanInitialized) return;
    window.albaAdminKanbanInitialized = true;

    // Keep native creation forms to one submission while navigation is pending.
    const pendingCreationForms = new Set();
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('.alba-add-card-form')) return;
        if (pendingCreationForms.has(form)) {
            e.preventDefault();
            e.stopImmediatePropagation();
            return;
        }
        if (e.defaultPrevented || !form.checkValidity()) return;
        pendingCreationForms.add(form);
        form.setAttribute('aria-busy', 'true');
        form.querySelectorAll('input[type="submit"], button[type="submit"]').forEach(button => {
            button.disabled = true;
        });
    });
    window.addEventListener('pageshow', () => {
        pendingCreationForms.forEach(form => {
            form.removeAttribute('aria-busy');
            form.querySelectorAll('input[type="submit"], button[type="submit"]').forEach(button => {
                button.disabled = false;
            });
        });
        pendingCreationForms.clear();
    });
    
    // --- 0. AUTO-OPEN LINKED CARD (Zero friction linking) ---
    const urlParams = new URLSearchParams(window.location.search);
    const linkedCardId = urlParams.get('alba_card');
    if (linkedCardId) {
        const cardElement = document.querySelector('.alba-card[data-card-id="' + linkedCardId + '"]');
        if (cardElement) {
            setTimeout(() => cardElement.click(), 300);
        }
    }

    // Helper functions for clean URL mapping via History API
    function updateUrlWithCard(id) {
        const url = new URL(window.location);
        url.searchParams.set('alba_card', id);
        window.history.pushState({ path: url.href }, '', url.href);
    }
    function removeCardFromUrl() {
        const url = new URL(window.location);
        url.searchParams.delete('alba_card');
        window.history.replaceState({ path: url.href }, '', url.href);
    }

    // --- 1. SORTABLE CARDS ---
    const originalCardLocations = new WeakMap();
    if (albaBoard.can_move_cards) document.querySelectorAll('.alba-cards-container').forEach(list => {
        if (typeof Sortable !== "undefined") {
            new Sortable(list, {
                group: 'alba-cards',
                animation: 150,
                ghostClass: 'sortable-ghost',
                dragClass: 'sortable-drag',
                filter: '.alba-no-cards-msg',
                onStart: function (evt) {
                    originalCardLocations.set(evt.item, {
                        parent: evt.from,
                        nextSibling: evt.item.nextSibling
                    });
                },

                onEnd: function (evt) {
                    const card = evt.item;
                    const cardId = card.dataset.cardId;
                    const newListId = evt.to.dataset.listId;
                    const orderedCardIds = Array.from(evt.to.querySelectorAll('.alba-card')).map(el => el.dataset.cardId);

                    checkEmptyStates(); 

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
                            if (!response.success) {
                                const originalLocation = originalCardLocations.get(card);
                                if (originalLocation && originalLocation.parent) {
                                    originalLocation.parent.insertBefore(card, originalLocation.nextSibling && originalLocation.nextSibling.parentNode === originalLocation.parent ? originalLocation.nextSibling : null);
                                }
                                checkEmptyStates();
                                alert(response.data && response.data.message ? response.data.message : (albaBoard.move_error || 'Could not move the card.'));
                            }
                        })
                        .catch(() => {
                            const originalLocation = originalCardLocations.get(card);
                            if (originalLocation && originalLocation.parent) {
                                originalLocation.parent.insertBefore(card, originalLocation.nextSibling && originalLocation.nextSibling.parentNode === originalLocation.parent ? originalLocation.nextSibling : null);
                            }
                            checkEmptyStates();
                            alert(albaBoard.move_error || 'Could not move the card.');
                        });
                    }
                }
            });
        }
    });

    // --- 2. SORTABLE LISTS ---
    const boardWrapper = document.querySelector('.alba-board-wrapper');
    const originalListLocations = new WeakMap();
    if (albaBoard.can_move_lists && boardWrapper && typeof Sortable !== "undefined") {
        new Sortable(boardWrapper, {
            group: 'alba-lists-group',
            animation: 150,
            draggable: '.alba-list-scrollable', 
            handle: '.alba-list-header',        
            filter: '.alba-delete-list-btn, .alba-list-collapse-btn',
            ghostClass: 'sortable-ghost',
            onStart: function (evt) {
                originalListLocations.set(evt.item, {
                    parent: evt.from,
                    nextSibling: evt.item.nextSibling
                });
            },
            onEnd: function (evt) {
                const orderedListIds = Array.from(boardWrapper.querySelectorAll('.alba-list-scrollable')).map(el => el.dataset.listId);
                const params = new URLSearchParams();
                params.append('action', 'alba_move_list_action');
                params.append('nonce', albaBoard.move_list_nonce);
                orderedListIds.forEach((id, index) => { params.append(`order[${index}]`, id); });
                fetch(albaBoard.ajaxurl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params })
                    .then(res => res.json())
                    .then(response => {
                        if (!response.success) {
                            const originalLocation = originalListLocations.get(evt.item);
                            if (originalLocation && originalLocation.parent) {
                                originalLocation.parent.insertBefore(evt.item, originalLocation.nextSibling && originalLocation.nextSibling.parentNode === originalLocation.parent ? originalLocation.nextSibling : null);
                            }
                            alert(response.data && response.data.message ? response.data.message : (albaBoard.fetch_error || 'Could not save the list order.'));
                        }
                    })
                    .catch(() => {
                        const originalLocation = originalListLocations.get(evt.item);
                        if (originalLocation && originalLocation.parent) {
                            originalLocation.parent.insertBefore(evt.item, originalLocation.nextSibling && originalLocation.nextSibling.parentNode === originalLocation.parent ? originalLocation.nextSibling : null);
                        }
                        alert(albaBoard.fetch_error || 'Could not save the list order.');
                    });
            }
        });
    }

    // --- 3. REAL-TIME FILTER ENGINE ---
    const searchInput = document.getElementById('alba-filter-search');
    const userFilter  = document.getElementById('alba-filter-user');
    const tagFilter   = document.getElementById('alba-filter-tag');
    const cardFilterData = new WeakMap();
    const filterRoot = boardWrapper || document;
    let filterCards = Array.from(filterRoot.querySelectorAll('.alba-card'));
    let filterCardsChanged = false;
    let filterTimer;

    function getCardFilterData(card) {
        if (!cardFilterData.has(card)) {
            const titleEl = card.querySelector('.alba-card-title');
            cardFilterData.set(card, {
                title: titleEl ? titleEl.textContent.toLowerCase() : '',
                author: card.dataset.author || '',
                tags: Array.from(card.querySelectorAll('.alba-card-tag-chip')).map(tag => tag.textContent.toLowerCase().trim())
            });
        }
        return cardFilterData.get(card);
    }

    function applyFilters() {
        clearTimeout(filterTimer);
        if (filterCardsChanged) {
            filterCards = Array.from(filterRoot.querySelectorAll('.alba-card'));
            filterCardsChanged = false;
        }
        const searchVal = searchInput ? searchInput.value.toLowerCase() : '';
        const userVal   = userFilter ? userFilter.value : '';
        const tagVal    = tagFilter ? tagFilter.value.toLowerCase() : '';

        filterCards.forEach(card => {
            let show = true;
            const { title, author, tags } = getCardFilterData(card);

            if (searchVal && title.indexOf(searchVal) === -1) show = false;
            if (userVal && author !== userVal) show = false;
            if (tagVal && tags.indexOf(tagVal) === -1) show = false;

            if (show) {
                card.classList.remove('alba-is-hidden-by-filter');
                card.style.display = '';
            } else {
                card.classList.add('alba-is-hidden-by-filter');
                card.style.display = 'none';
            }
        });

        checkEmptyStates();
    }

    function checkEmptyStates() {
        document.querySelectorAll('.alba-cards-container').forEach(listContainer => {
            const visibleCards = listContainer.querySelectorAll('.alba-card:not(.alba-is-hidden-by-filter)').length;
            const noCardsMsg = listContainer.querySelector('.alba-no-cards-msg');
            if (noCardsMsg) {
                if (visibleCards > 0) {
                    noCardsMsg.classList.add('alba-is-hidden');
                } else {
                    noCardsMsg.classList.remove('alba-is-hidden');
                }
            }
        });
    }

    if (searchInput) searchInput.addEventListener('input', () => {
        clearTimeout(filterTimer);
        filterTimer = setTimeout(applyFilters, 150);
    });
    if (userFilter)  userFilter.addEventListener('change', applyFilters);
    if (tagFilter)   tagFilter.addEventListener('change', applyFilters);

    // Refresh only changed card data when the board or an add-on updates its markup.
    if (boardWrapper) {
        const filterObserver = new MutationObserver(mutations => {
            mutations.forEach(mutation => {
                const target = mutation.target.nodeType === Node.ELEMENT_NODE ? mutation.target : mutation.target.parentElement;
                const card = target ? target.closest('.alba-card') : null;
                if (card) {
                    cardFilterData.delete(card);
                } else if (mutation.type === 'childList') {
                    filterCardsChanged = true;
                }
            });
            clearTimeout(filterTimer);
            filterTimer = setTimeout(applyFilters, 150);
        });
        filterObserver.observe(boardWrapper, {
            childList: true,
            subtree: true,
            characterData: true,
            attributes: true,
            attributeFilter: ['data-author']
        });
    }
    applyFilters();

    // --- 4. UI INTERACTION ---
    const boardSelector = document.querySelector('.alba-auto-submit-select');
    if(boardSelector) boardSelector.addEventListener('change', function() { this.form.submit(); });

    document.addEventListener('click', function(e) {
        
        // --- 4.1 SHORTCODE COPY BUTTON (Event Delegation) ---
        const copyShortcodeBtn = e.target.closest('.alba-board-copy-shortcode');
        if (copyShortcodeBtn) {
            e.preventDefault();
            const shortcode = copyShortcodeBtn.getAttribute('data-shortcode');
            
            if (navigator && navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(shortcode);
            } else {
                const temp = document.createElement('textarea');
                temp.value = shortcode;
                document.body.appendChild(temp);
                temp.select();
                try { document.execCommand('copy'); } catch(err){}
                document.body.removeChild(temp);
            }
            
            const originalHTML = copyShortcodeBtn.innerHTML;
            copyShortcodeBtn.innerHTML = '<span class="dashicons dashicons-yes-alt" style="font-size:16px; width:16px; height:16px; margin-top:1px; color:#10b981;"></span> <span style="color:#10b981;">Copied!</span>';
            
            setTimeout(() => {
                copyShortcodeBtn.innerHTML = originalHTML;
            }, 1500);
            return;
        }

        if (e.target.id === 'alba-show-new-board-btn') {
            e.preventDefault();
            const form = document.getElementById('alba-new-board-form');
            if(form) { form.classList.remove('alba-is-hidden'); e.target.classList.add('alba-is-hidden'); const input = form.querySelector('input[type="text"]'); if (input) input.focus(); }
        }
        if (e.target.id === 'alba-cancel-new-board-btn') {
            e.preventDefault();
            const form = document.getElementById('alba-new-board-form'); const btn = document.getElementById('alba-show-new-board-btn');
            if(form && btn) { form.classList.add('alba-is-hidden'); btn.classList.remove('alba-is-hidden'); }
        }

        const showListBtn = e.target.closest('.alba-show-add-list-btn');
        if (showListBtn) {
            e.preventDefault(); const form = showListBtn.nextElementSibling;
            if(form) { form.classList.remove('alba-is-hidden'); showListBtn.classList.add('alba-is-hidden'); const input = form.querySelector('input[type="text"]'); if (input) input.focus(); }
        }
        const cancelListBtn = e.target.closest('.alba-cancel-new-list-btn');
        if (cancelListBtn) {
            e.preventDefault(); const form = cancelListBtn.closest('form');
            if(form) { form.classList.add('alba-is-hidden'); if(form.previousElementSibling) form.previousElementSibling.classList.remove('alba-is-hidden'); }
        }

        const showCardBtn = e.target.closest('.alba-show-add-card-btn');
        if (showCardBtn) {
            e.preventDefault(); const form = showCardBtn.nextElementSibling;
            if(form) { form.classList.remove('alba-is-hidden'); showCardBtn.classList.add('alba-is-hidden'); const input = form.querySelector('input[type="text"]'); if (input) input.focus(); }
        }
        const cancelCardBtn = e.target.closest('.alba-cancel-new-card-btn');
        if (cancelCardBtn) {
            e.preventDefault(); const form = cancelCardBtn.closest('form');
            if(form) { form.classList.add('alba-is-hidden'); if(form.previousElementSibling) form.previousElementSibling.classList.remove('alba-is-hidden'); }
        }

        const deleteListBtn = e.target.closest('.alba-delete-list-btn');
        if (deleteListBtn) {
            e.preventDefault();
            if (confirm('Delete this list and archive all of its cards?')) {
                const listId = deleteListBtn.dataset.listId;
                const listContainer = deleteListBtn.closest('.alba-list');
                deleteListBtn.style.opacity = '0.5'; deleteListBtn.style.pointerEvents = 'none';
                
                const formData = new URLSearchParams();
                formData.append('action', 'alba_delete_list_action'); 
                formData.append('list_id', listId); 
                formData.append('nonce', albaBoard.delete_list_nonce);
                
                fetch(albaBoard.ajaxurl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: formData })
                .then(res => res.json())
                .then(response => {
                    if (response.success) {
                        listContainer.style.transition = 'all 0.3s ease'; listContainer.style.opacity = '0'; listContainer.style.transform = 'scale(0.9)';
                        setTimeout(() => listContainer.remove(), 300);
                    } else { 
                        alert((response.data && response.data.message) ? response.data.message : 'Error deleting list.'); 
                        deleteListBtn.style.opacity = '1'; 
                        deleteListBtn.style.pointerEvents = 'auto'; 
                    }
                });
            }
        }
    });

    // --- 5. MODAL LOGIC ---
    const modalAdmin = document.getElementById('alba-card-modal-admin');
    const modalBody = document.getElementById('alba-modal-body-admin');
    let currentOpenedCard = null;

    document.querySelectorAll('.alba-card').forEach(card => {
        card.addEventListener('click', function(e) {
            if (e.target.closest('.alba-card-action-btn') || e.target.closest('a') || e.target.closest('button')) return;
            const cardId = card.dataset.cardId; currentOpenedCard = card;
            
            if(modalAdmin) { modalAdmin.classList.remove('alba-is-hidden'); modalAdmin.classList.add('active'); }
            if(modalBody) modalBody.innerHTML = albaBoard.loading || 'Loading...';

            // ZERO FRICTION UX: Update URL immediately 
            updateUrlWithCard(cardId);

            fetch(albaBoard.rest_url + 'alba-board/v1/card/' + cardId + '?context=admin', {
                method: 'GET',
                headers: { 'X-WP-Nonce': albaBoard.rest_nonce }
            })
            .then(res => res.json())
            .then(response => {
                if(modalBody && response.html) {
                    modalBody.innerHTML = response.html;
                    bindSaveButtonHandler(); 
                    bindAttachmentHandlers(); 
                    jQuery(document).trigger('alba_modal_loaded');
                }
            })
            .catch(() => { 
                if(modalBody) modalBody.innerHTML = albaBoard.fetch_error || 'Error loading card.'; 
            });
        });
    });

    const closeModal = () => { 
        if(modalAdmin) { 
            modalAdmin.classList.add('alba-is-hidden'); 
            modalAdmin.classList.remove('active'); 
        } 
        currentOpenedCard = null; 
        removeCardFromUrl(); // Revert URL natively
    };
    
    const closeBtn = document.getElementById('alba-modal-close-admin');
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    window.addEventListener('click', (e) => { if (e.target.id === 'alba-card-modal-admin') closeModal(); });

    document.addEventListener('keydown', (e) => {
        const isModalOpen = modalAdmin && !modalAdmin.classList.contains('alba-is-hidden');
        if (e.key === 'Escape') {
            if (isModalOpen) { closeModal(); return; }
            document.querySelectorAll('.alba-stacked-form:not(.alba-is-hidden), .alba-inline-form:not(.alba-is-hidden)').forEach(form => {
                form.classList.add('alba-is-hidden');
                if (form.previousElementSibling && form.previousElementSibling.tagName === 'BUTTON') form.previousElementSibling.classList.remove('alba-is-hidden');
            });
        }
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            if (isModalOpen) {
                e.preventDefault();
                if (e.repeat || e.isComposing) return;
                const saveBtn = document.getElementById('alba-card-save-btn');
                if (saveBtn && !saveBtn.disabled) { saveBtn.focus(); saveBtn.click(); }
                return;
            }
            const form = e.target.closest('.alba-add-card-form');
            if (form) {
                e.preventDefault();
                if (e.repeat || e.isComposing || pendingCreationForms.has(form)) return;
                form.requestSubmit();
            }
        }
    });

    // --- 6. FLATPICKR ---
    jQuery(document).on('alba_modal_loaded', function() {
        const dateInput = document.getElementById('alba-card-due-date');
        if (dateInput && typeof flatpickr !== 'undefined') {
            const fp = flatpickr(dateInput, { dateFormat: "Y-m-d", disableMobile: true });
            const clearBtn = document.getElementById('alba-clear-date');
            if (clearBtn) {
                clearBtn.addEventListener('click', function(e) { e.preventDefault(); fp.clear(); dateInput.value = ''; this.style.display = 'none'; });
            }
        }
    });

    // --- 7. SAVE HANDLER ---
    function bindSaveButtonHandler() {
        const saveBtn = document.getElementById('alba-card-save-btn');
        if (!saveBtn) return;
        saveBtn.onclick = function (e) {
            e.preventDefault(); 
            if (saveBtn.disabled) return;
            const form = document.getElementById('alba-card-details-form'); 
            if (!form) return;
            
            const formData = new FormData(form);
            formData.append('action', 'alba_save_card_details_admin'); 
            formData.append('nonce', albaBoard.save_card_details_nonce);
            
            const newCommentField = document.getElementById('alba-new-comment');
            if (newCommentField) formData.set('new_comment', newCommentField.value);
            
            saveBtn.textContent = 'Saving...'; saveBtn.style.opacity = '0.7';
            saveBtn.disabled = true;
            
            fetch(albaBoard.ajaxurl, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(response => {
                if (response && response.success) { location.reload(); } 
                else { saveBtn.textContent = 'Save Changes'; saveBtn.style.opacity = '1'; saveBtn.disabled = false; }
            })
            .catch(() => { saveBtn.textContent = 'Save Changes'; saveBtn.style.opacity = '1'; saveBtn.disabled = false; });
        }
    }

    document.addEventListener('click', function(e) {
        const archiveButton = e.target.closest('#alba-card-archive-btn');
        const restoreButton = e.target.closest('.alba-restore-card-btn');
        const button = archiveButton || restoreButton;
        if (!button || button.disabled) return;

        e.preventDefault();
        const cardId = button.dataset.cardId || document.getElementById('alba-current-card-id')?.value;
        if (!cardId) return;

        const action = restoreButton ? 'restore' : 'archive';
        const originalText = button.textContent;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        if (restoreButton) button.textContent = 'Restoring…';

        const body = new URLSearchParams();
        body.append('action', 'alba_manage_archived_card');
        body.append('archive_action', action);
        body.append('card_id', cardId);
        body.append('nonce', albaBoard.manage_card_archive_nonce);

        fetch(albaBoard.ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body
        })
        .then(res => res.json())
        .then(response => {
            if (response.success) {
                if (action === 'archive') removeCardFromUrl();
                location.reload();
                return;
            }
            const feedback = document.getElementById('alba-archive-feedback');
            if (feedback) feedback.textContent = response.data?.message || 'Could not update this card.';
            button.disabled = false;
            button.removeAttribute('aria-busy');
            if (restoreButton) button.textContent = originalText;
        })
        .catch(() => {
            const feedback = document.getElementById('alba-archive-feedback');
            if (feedback) feedback.textContent = 'Could not update this card.';
            button.disabled = false;
            button.removeAttribute('aria-busy');
            if (restoreButton) button.textContent = originalText;
        });
    });

    document.addEventListener('click', (e) => {
        const button = e.target.closest('#alba-card-trash-btn');
        if (!button) return;
        e.preventDefault();
        if (button.disabled) return;
        const cardId = document.getElementById('alba-current-card-id')?.value;
        if (!cardId || !window.confirm(albaBoard.trash_card_confirm)) return;

        const feedback = document.getElementById('alba-archive-feedback');
        if (feedback) feedback.textContent = '';
        button.disabled = true;
        const body = new URLSearchParams({ action: 'alba_trash_card', card_id: cardId, nonce: albaBoard.trash_card_nonce });
        fetch(albaBoard.ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body
        })
        .then(res => res.json())
        .then(response => {
            if (!response.success) throw new Error(response.data?.message || albaBoard.trash_card_error);
            document.querySelectorAll('.alba-card').forEach(card => {
                if (card.dataset.cardId === cardId) card.remove();
            });
            closeModal();
            checkEmptyStates();
        })
        .catch(error => {
            if (feedback) feedback.textContent = error.message || albaBoard.trash_card_error;
            button.disabled = false;
        });
    });

    // --- 8. ATTACHMENT HANDLERS ---
    function bindAttachmentHandlers() {
        const feedbackDiv = document.getElementById('alba-upload-feedback');
        
        jQuery(document).off('click', '#alba-trigger-upload-btn').on('click', '#alba-trigger-upload-btn', function(e) {
            e.preventDefault();
            jQuery('#alba-file-upload-input').trigger('click');
        });

        jQuery(document).off('change', '#alba-file-upload-input').on('change', '#alba-file-upload-input', function(e) {
            const file = this.files[0]; 
            const cardId = jQuery('#alba-current-card-id').val();
            
            if (!file || !cardId) return;
            
            if (feedbackDiv) {
                feedbackDiv.textContent = albaBoard.uploading || 'Uploading...'; 
                feedbackDiv.style.color = '#2271b1';
            }
            
            const formData = new FormData();
            formData.append('action', 'alba_upload_attachment');
            formData.append('nonce', albaBoard.upload_attachment_nonce);
            formData.append('card_id', cardId);
            formData.append('file', file);
            
            jQuery.ajax({
                url: albaBoard.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false, 
                contentType: false, 
                success: function(response) {
                    if (response.success) {
                        if (feedbackDiv) feedbackDiv.textContent = '';
                        const listDiv = document.getElementById('alba-attachments-list');
                        const noMsg = document.getElementById('alba-no-attachments-msg'); 
                        if (noMsg) noMsg.remove();
                        
                        const newItem = document.createElement('div');
                        newItem.className = 'alba-attachment-item'; 
                        newItem.id = 'alba-attachment-' + response.data.attachment_id;
                        newItem.style = 'display: flex; justify-content: space-between; align-items: center; background: var(--alba-card-bg); padding: 8px 14px; border-radius: 12px; box-shadow: 2px 2px 6px var(--alba-shadow-dark), -2px -2px 6px var(--alba-shadow-light); margin-top: 8px;';
                        newItem.innerHTML = `<a href="${response.data.file_url}" target="_blank" style="text-decoration: none; color: var(--alba-text-main); font-weight: 600; font-size: 0.95em;">📎 ${response.data.file_name}</a><button type="button" class="alba-delete-attachment-btn" data-attachment-id="${response.data.attachment_id}" style="background: none; border: none; color: var(--alba-danger); cursor: pointer; font-size: 1.2em; outline: none;">&times;</button>`;
                        
                        listDiv.appendChild(newItem); 
                    } else { 
                        if (feedbackDiv) {
                            feedbackDiv.textContent = response.data.message || 'Error uploading.'; 
                            feedbackDiv.style.color = 'var(--alba-danger)'; 
                        }
                    }
                    jQuery('#alba-file-upload-input').val('');
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    if (feedbackDiv) {
                        feedbackDiv.textContent = 'Server Error: ' + textStatus;
                        feedbackDiv.style.color = 'var(--alba-danger)';
                    }
                    console.error('Alba Board Upload Error:', errorThrown);
                    jQuery('#alba-file-upload-input').val('');
                }
            });
        });
        
        bindDeleteButtons();
    }

    function bindDeleteButtons() {
        jQuery(document).off('click', '.alba-delete-attachment-btn').on('click', '.alba-delete-attachment-btn', function(e) {
            e.preventDefault(); 
            
            const btn = jQuery(this);
            const attachmentId = btn.data('attachment-id'); 
            const cardId = jQuery('#alba-current-card-id').val(); 
            const itemDiv = jQuery('#alba-attachment-' + attachmentId);
            
            if (!attachmentId || !cardId) return;
            
            btn.text('...').prop('disabled', true);

            const formData = new FormData();
            formData.append('action', 'alba_delete_attachment');
            formData.append('card_id', cardId);
            formData.append('attachment_id', attachmentId);
            formData.append('nonce', albaBoard.delete_attachment_nonce);
            
            jQuery.ajax({
                url: albaBoard.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success && itemDiv.length) { 
                        itemDiv.remove(); 
                    } else { 
                        alert((response.data && response.data.message) ? response.data.message : 'Failed to delete.'); 
                        btn.text('✖').prop('disabled', false);
                    }
                },
                error: function(err) {
                    console.error('Alba Board Delete Error:', err);
                    btn.text('✖').prop('disabled', false);
                }
            });
        });
    }

    // --- 9. SELECT2 ---
    jQuery(document).on('DOMNodeInserted', function(e) {
        jQuery(e.target).find('.alba-select2:not(.alba-tags-select2)').each(function() {
            if (!jQuery(this).hasClass('select2-hidden-accessible')) {
                jQuery(this).select2({ width: '100%', dropdownParent: jQuery('#alba-card-modal-admin .alba-modal-content') });
            }
        });
    });

    // --- 10. LIST COLLAPSE ---
    function initListCollapse() {
        const storageKey = 'alba_collapsed_lists';
        let collapsedLists = JSON.parse(localStorage.getItem(storageKey)) || [];

        collapsedLists.forEach(listId => {
            const listContainer = document.querySelector(`.alba-cards[data-list-id="${listId}"], .alba-cards-container[data-list-id="${listId}"]`);
            if (listContainer) {
                const wrapper = listContainer.closest('.alba-list-column, .alba-list');
                if (wrapper) wrapper.classList.add('alba-list-collapsed');
            }
        });

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.alba-list-collapse-btn');
            if (!btn) return;
            e.preventDefault();

            const wrapper = btn.closest('.alba-list-column, .alba-list');
            if (!wrapper) return;

            const listContainer = wrapper.querySelector('.alba-cards, .alba-cards-container');
            const listId = listContainer ? listContainer.dataset.listId : null;

            if (listId) {
                wrapper.classList.toggle('alba-list-collapsed');
                
                if (wrapper.classList.contains('alba-list-collapsed')) {
                    if (!collapsedLists.includes(listId)) collapsedLists.push(listId);
                } else {
                    collapsedLists = collapsedLists.filter(id => id !== listId);
                }
                
                localStorage.setItem(storageKey, JSON.stringify(collapsedLists));
            }
        });
    }
    
    initListCollapse();
});
